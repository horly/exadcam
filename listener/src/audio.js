import net from 'node:net';
import {randomBytes,randomUUID} from 'node:crypto';
import {pathToFileURL} from 'node:url';
import {WebSocketServer,WebSocket} from 'ws';
import {Frames1078,decodeBcd} from './protocol.js';
import {AudioFrames,AdtsFrames,adtsFormat,audioTranscoder,encodeAudioPacket,SUPPORTED_AUDIO} from './audio-codec.js';
import {registry,post,log,internalServer,reply,safeEqual} from './common.js';

export function startAudio({transcoder=audioTranscoder}={}) {
    const sessions=new Map(), devices=new Map(), sockets=new Set();
    const gps=(route,data)=>post(`${process.env.GPS_API_URL||'http://127.0.0.1:3001'}${route}`,data);
    const video=(route,session)=>post(`${process.env.VIDEO_API_URL||'http://127.0.0.1:3002'}/audio/${route}`,{device_id:session.device.id,lease_id:session.id,mode:session.mode});
    const access=session=>post(`${process.env.LARAVEL_LISTENER_URL||'http://127.0.0.1:8081/api/internal/listener'}/audio-access`,{grant:session.grant,device_id:session.device.id});
    const send=(session,data)=>{if(session.ws?.readyState===WebSocket.OPEN)session.ws.send(JSON.stringify(data));};
    const stop=(session,reason='closed')=>{
        if(session.closing)return session.closing;
        session.closed=true;
        session.decoder?.close();session.encoder?.close();session.socket?.destroy();
        send(session,{state:'closed',reason});session.ws?.close(1000,'Audio closed');
        session.closing=(async()=>{
            await session.activation?.catch(()=>{});
            if(session.requested){
                if(session.mode==='talk')await gps('/audio/stop',{device_id:session.device.id,channel:1,mode:session.mode}).catch(()=>{});
                await video('detach',session).catch(()=>{});
            }
            if(devices.get(session.device.id)===session)devices.delete(session.device.id);
            sessions.delete(session.id);
            log('audio_stopped',{device_id:session.device.id,mode:session.mode,reason,received_frames:session.received,sent_frames:session.sent});
        })();
        return session.closing;
    };
    const fail=(session,error)=>{log('audio_error',{device_id:session.device.id,reason:error.message});void stop(session,'unavailable');};
    const activate=async session=>{
        try {
            await access(session);if(session.closed)return;
            session.requested=true;
            await video(session.mode==='talk'?'talk-start':'attach',session);
            if(session.closed)return;
            if(session.mode==='talk')await gps('/audio/start',{device_id:session.device.id,channel:1,mode:session.mode});
            if(!session.closed){session.acknowledged=true;send(session,{state:'connecting'});}
        } catch(error){fail(session,error);}
    };
    const beginEncoder=session=>{
        let pending=Buffer.alloc(0),sequence=0,timestamp=0;
        const adts=new AdtsFrames();
        const transmit=payload=>{
            if(session.closed||!session.socket||session.socket.destroyed)return;
            if(session.socket.writableLength>8192)throw Error('Audio return too slow');
            const packet=encodeAudioPacket({terminal:session.device.video_terminal_id,channel:1,codec:session.codec,sequence:sequence++,timestamp,payload});
            timestamp+=(session.codec===19?1024:payload.length)/session.rate*1000;
            session.socket.write(packet);session.sent++;
        };
        session.encoder=transcoder({codec:session.codec,rate:session.rate,encode:true,onError:error=>fail(session,error),onData:data=>{
            try {
                if(session.codec===19){for(const frame of adts.push(data))transmit(frame);}
                else {pending=Buffer.concat([pending,data]);const size=Math.round(session.rate*0.02);while(pending.length>=size){transmit(pending.subarray(0,size));pending=pending.subarray(size);}}
            }catch(error){fail(session,error);}
        }});
    };
    const consume=(session,codec,frame)=>{
        if(!SUPPORTED_AUDIO.includes(codec))throw Error('Unsupported audio codec');
        session.lastPacket=Date.now();session.received++;
        if(!session.decoder){
            session.codec=codec;
            session.rate=codec===19?adtsFormat(frame).rate:session.capabilities.sample_rate;
            if(!session.rate)throw Error('Unknown sample rate');
            session.decoder=transcoder({codec,rate:session.rate,onError:error=>fail(session,error),onData:pcm=>{
                if(session.closed||session.ws?.readyState!==WebSocket.OPEN)return;
                if(session.ws.bufferedAmount>32000)return fail(session,Error('Audio browser too slow'));
                if(!session.ready){session.ready=true;send(session,{state:'ready',mode:session.mode,sample_rate:16000});}
                session.pcm=Buffer.concat([session.pcm||Buffer.alloc(0),pcm]);
                while(session.pcm.length>=640){session.ws.send(session.pcm.subarray(0,640),{binary:true});session.pcm=session.pcm.subarray(640);}
            }});
            if(session.mode==='talk')beginEncoder(session);
            log('audio_source',{device_id:session.device.id,mode:session.mode,codec,rate:session.rate});
        }
        if(codec!==session.codec)throw Error('Audio codec changed');
        session.decoder.write(frame);
    };
    const server=net.createServer(socket=>{
        if(sockets.size>=32)return socket.destroy();
        sockets.add(socket);socket.setNoDelay(true);socket.setTimeout(10000,()=>socket.destroy());
        let session=null;
        const parser=new Frames1078(header=>{
            const matches=[];
            for(const bytes of [6,10]){
                try{const terminal=decodeBcd(header.subarray(8,8+bytes));if([...sessions.values()].some(s=>s.requested&&!s.closed&&s.mode==='talk'&&s.device.video_terminal_id===terminal&&header[8+bytes]===1))matches.push(bytes);}catch{}
            }
            if(matches.length!==1)throw Error('Unrequested audio connection');return matches[0];
        });
        socket.on('error',()=>{});
        socket.on('data',chunk=>{
            try {
                for(const packet of parser.push(chunk)){
                    if(!session){
                        session=[...sessions.values()].find(s=>s.requested&&!s.closed&&s.mode==='talk'&&s.device.video_terminal_id===packet.terminal&&packet.channel===1);
                        if(!session||session.socket||!session.ws)throw Error('Audio session unavailable');
                        session.socket=socket;
                    }
                    if(session.closed||session.mode!=='talk'||packet.terminal!==session.device.video_terminal_id||packet.channel!==1)throw Error('Audio identity changed');
                    const frame=session.frames.push(packet);if(!frame)continue;
                    consume(session,packet.payloadType,frame);
                }
            }catch(error){socket.destroy();if(session?.socket===socket)fail(session,error);}
        });
        socket.on('close',()=>{sockets.delete(socket);if(session?.socket===socket&&!session.closed)void stop(session,'source_disconnected');});
    });
    server.listen(Number(process.env.AUDIO_PORT||1080),process.env.LISTENER_BIND||'0.0.0.0');
    const api=internalServer(Number(process.env.AUDIO_API_PORT||3003),async(_req,res,url,data)=>{
        if(url==='/ingest'){
            const session=sessions.get(data.lease_id);
            if(!session||session.closed||session.mode!=='listen'||!session.requested||session.device.id!==data.device_id)return reply(res,404,{error:'No audio listener'});
            if(typeof data.payload!=='string'||data.payload.length>5500)return reply(res,400,{error:'Audio too large'});
            try{consume(session,Number(data.codec),Buffer.from(data.payload,'base64'));return reply(res,200,{received:true});}
            catch(error){fail(session,error);return reply(res,422,{error:'Unsupported audio'});}
        }
        if(url==='/revoke'){
            await Promise.all([...sessions.values()].filter(s=>s.device.id===Number(data.device_id)).map(s=>stop(s,'revoked')));return reply(res,200,{revoked:true});
        }
        if(url==='/sessions'){
            if(!['listen','talk'].includes(data.mode)||!/^[a-f0-9]{64}$/.test(data.grant||''))return reply(res,400,{error:'Invalid audio request'});
            const device=await registry('id',Number(data.device_id));
            if(devices.has(device.id))return reply(res,409,{error:'Audio busy',code:'busy'});
            if(sessions.size>=8)return reply(res,409,{error:'Audio capacity',code:'busy'});
            const session={id:randomUUID(),device,mode:data.mode,grant:data.grant,token:randomBytes(32).toString('hex'),created:Date.now(),expires:Date.now()+20000,frames:new AudioFrames(),received:0,sent:0,closed:false,lastPacket:Date.now()};
            devices.set(device.id,session);sessions.set(session.id,session);
            try {
                await access(session);
                const capabilities=await gps('/audio/capabilities',{device_id:device.id});
                if(!SUPPORTED_AUDIO.includes(capabilities.codec)||capabilities.channels!==1||!capabilities.sample_rate)throw Object.assign(Error('Unsupported device audio'),{status:422});
                if(data.mode==='talk'&&!capabilities.output)throw Object.assign(Error('Device has no audio output'),{status:422});
                session.capabilities=capabilities;
                if(session.closed)throw Error('Audio session expired');
                return reply(res,201,{lease_id:session.id,token:session.token,socket_path:`/audio-live/${session.id}`,sample_rate:16000,mode:session.mode});
            }catch(error){await stop(session,'start_failed');return reply(res,error.status===409?409:error.status===403?403:error.status===422?422:503,{error:'Audio unavailable',code:error.status===409?'offline':error.status===422?'unsupported':'unavailable'});}
        }
        const match=url.match(/^\/sessions\/([a-f0-9-]{36})\/(keepalive|stop)$/),session=match&&sessions.get(match[1]);
        if(!session)return reply(res,404,{error:'Audio session expired'});
        if(match[2]==='stop'){await stop(session);return reply(res,200,{stopped:true});}
        if(session.closed)return reply(res,404,{error:'Audio session expired'});
        await access(session);session.expires=Date.now()+20000;
        return reply(res,200,{status:session.ready?'ready':'connecting'});
    });
    const wss=new WebSocketServer({noServer:true,maxPayload:4096,perMessageDeflate:false,handleProtocols:()=> 'exadcam-audio'});
    api.on('upgrade',(req,socket,head)=>{
        const match=req.url?.match(/^\/audio-live\/([a-f0-9-]{36})$/),session=match&&sessions.get(match[1]);
        const protocols=String(req.headers['sec-websocket-protocol']||'').split(',').map(p=>p.trim());
        const credential=protocols.find(p=>p.startsWith('token.'))?.slice(6);
        const origins=(process.env.AUDIO_ALLOWED_ORIGINS||'https://exadcam.app').split(',');
        if(!session||session.closed||session.ws||Date.now()>session.expires||!origins.includes(req.headers.origin)||!protocols.includes('exadcam-audio')||!safeEqual(credential,session.token)){
            socket.write('HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n');socket.destroy();return;
        }
        session.token=null;
        wss.handleUpgrade(req,socket,head,ws=>{
            session.ws=ws;let windowAt=Date.now(),bytes=0;
            ws.on('error',()=>{});
            ws.on('message',(data,binary)=>{
                if(session.closed)return;
                if(Date.now()-windowAt>=1000){windowAt=Date.now();bytes=0;}
                bytes+=data.length;
                if(!binary||session.mode!=='talk'||data.length%2||bytes>48000){void stop(session,'invalid_audio');return;}
                if(session.encoder&&session.ready){try{session.encoder.write(data);}catch(error){fail(session,error);}}
            });
            ws.on('close',()=>{void stop(session,'browser_closed');});
            send(session,{state:'connecting'});session.activation=activate(session);
        });
    });
    let checking=false;
    const timer=setInterval(async()=>{
        if(checking)return;checking=true;
        try{
            await Promise.allSettled([...sessions.values()].map(async session=>{
                if(session.expires<Date.now()||(session.requested&&Date.now()-session.lastPacket>15000)){void stop(session,'expired');return;}
                try{await access(session);if(session.acknowledged)await video('keepalive',session);}catch{void stop(session,'revoked');}
            }));
        }finally{checking=false;}
    },3000);
    const close=()=>{clearInterval(timer);for(const session of sessions.values())void stop(session,'service_shutdown');for(const socket of sockets)socket.destroy();wss.close();api.close();server.close();};
    log('audio_listening',{port:Number(process.env.AUDIO_PORT||1080)});
    return {server,api,close};
}
if(process.argv[1]&&import.meta.url===pathToFileURL(process.argv[1]).href){const service=startAudio();for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>service.close());}
