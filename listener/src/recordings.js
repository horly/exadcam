import net from 'node:net';
import fs from 'node:fs';
import path from 'node:path';
import {randomUUID} from 'node:crypto';
import {spawn} from 'node:child_process';
import {pathToFileURL} from 'node:url';
import {Frames1078,MediaFrames,decodeBcd} from './protocol.js';
import {AudioFrames,adtsFormat} from './audio-codec.js';
import {registry,post,log,internalServer,reply} from './common.js';
import {recordingQuery} from './recording-protocol.js';

const TTL=6*3600000,MAX_BYTES=512*1024*1024;
export function recordingMuxerArgs(job,directory){
    const duration=job.lastTimestamp>job.firstTimestamp?Number(job.lastTimestamp-job.firstTimestamp)/1000:0;
    const measured=duration>0?(job.framesCount-1)/duration:0;
    const fps=measured>=1&&measured<=60?measured:Number(job.device.frame_rate||15);
    const args=['-hide_banner','-loglevel','error','-nostdin','-y','-fflags','+genpts','-r',String(fps),'-f','h264','-i',path.join(directory,'video.h264')];
    if(job.audioBytes){
        args.push('-f',{6:'alaw',7:'mulaw',19:'aac'}[job.audioCodec]);
        if(job.audioCodec!==19)args.push('-ar',String(job.audioRate||8000),'-ac','1');
        args.push('-i',path.join(directory,'audio.raw'),'-map','0:v:0','-map','1:a:0','-c:a','aac','-b:a','64k','-af','apad');
    }else args.push('-map','0:v:0','-an');
    args.push('-t',String(job.framesCount/fps),'-c:v','copy','-movflags','+faststart',path.join(directory,'recording.part.mp4'));
    return args;
}
export function startRecordings(){
    const root=path.resolve(process.env.RECORDING_STORAGE||'/var/lib/exadcam-recordings');
    fs.mkdirSync(root,{recursive:true,mode:0o750});
    const jobs=new Map(),active=new Map(),sockets=new Set();
    const gps=(route,data)=>post(`${process.env.GPS_API_URL||'http://127.0.0.1:3001'}${route}`,data);
    const release=job=>{
        job.socket?.destroy();
        for(const key of ['videoFd','audioFd'])if(job[key]!=null){try{fs.closeSync(job[key]);}catch{}job[key]=null;}
    };
    // Keep the device reserved until its stop command settles. A delayed stop
    // must never interrupt a newly requested replay on the same channel.
    const unreserve=job=>{if(active.get(job.device.id)===job)active.delete(job.device.id);};
    const cancelCamera=job=>{
        job.stopPromise??=gps('/recordings/stop',{device_id:job.device.id,channel:job.record.channel}).catch(()=>{}).finally(()=>unreserve(job));
        return job.stopPromise;
    };
    const fail=(job,reason,stop=true)=>{
        if(['ready','failed','cancelled'].includes(job.status))return;
        job.status='failed';job.error=reason;job.process?.kill('SIGTERM');release(job);
        if(stop)void cancelCamera(job);else unreserve(job);
        log('recording_failed',{device_id:job.device.id,job_id:job.id,reason});
    };
    const finish=job=>{
        if(!['waiting','receiving'].includes(job.status))return;
        if(job.framesCount<2)return fail(job,'incomplete');
        job.status='processing';release(job);cancelCamera(job);
        const dir=path.join(root,job.id);
        const child=spawn(process.env.FFMPEG_PATH||'/usr/bin/ffmpeg',recordingMuxerArgs(job,dir),{stdio:['ignore','ignore','pipe']});job.process=child;
        let diagnostic='';child.stderr.on('data',b=>{diagnostic=(diagnostic+b.toString()).slice(-500);});
        const timer=setTimeout(()=>{child.kill('SIGKILL');fail(job,'conversion_timeout');},120000);timer.unref();
        child.on('error',()=>{clearTimeout(timer);fail(job,'conversion_failed');});
        child.on('close',code=>{
            clearTimeout(timer);if(job.status!=='processing')return;
            const output=path.join(dir,'recording.part.mp4');
            if(code!==0||!fs.existsSync(output)||fs.statSync(output).size<256){log('recording_conversion_failed',{job_id:job.id,diagnostic});return fail(job,'conversion_failed');}
            fs.renameSync(output,path.join(dir,'recording.mp4'));job.status='ready';job.completed=Date.now();job.fileSize=fs.statSync(path.join(dir,'recording.mp4')).size;
            // Persist completed downloads so a receiver restart does not invalidate
            // files that are still within their advertised retention period.
            const metadata={id:job.id,device:{id:job.device.id},record:job.record,status:'ready',created:job.created,fileSize:job.fileSize,audioBytes:job.audioBytes};
            fs.writeFileSync(path.join(dir,'ready.json'),JSON.stringify(metadata),{mode:0o640});
            for(const name of ['video.h264','audio.raw'])try{fs.unlinkSync(path.join(dir,name));}catch{}
            log('recording_ready',{device_id:job.device.id,job_id:job.id,size:job.fileSize,audio:!!job.audioBytes,audio_bytes:job.audioBytes,audio_codec:job.audioCodec,frames:job.framesCount,seconds:job.receivedSeconds});
        });
    };
    const server=net.createServer({allowHalfOpen:true},socket=>{
        if(sockets.size>=32)return socket.destroy();sockets.add(socket);socket.setTimeout(15000);socket.setKeepAlive(true,30000);
        let job=null,chain=Promise.resolve();
        const parser=new Frames1078(header=>{
            const candidates=[];
            for(const length of [6,10])try{
                const terminal=decodeBcd(header.subarray(8,8+length)),channel=header[8+length];
                if([...active.values()].some(j=>j.device.video_terminal_id===terminal&&j.record.channel===channel&&['waiting','receiving'].includes(j.status)))candidates.push(length);
            }catch{}
            if(candidates.length!==1)throw Error('Unrequested recording source');return candidates[0];
        });
        socket.on('data',chunk=>{
            socket.pause();chain=chain.then(async()=>{
                for(const packet of parser.push(chunk)){
                    if(socket.destroyed)break;
                    if(!job){
                        job=[...active.values()].find(j=>j.device.video_terminal_id===packet.terminal&&j.record.channel===packet.channel);
                        if(!job||job.socket)throw Error('Recording source busy');
                        const device=await registry('video',packet.terminal);if(device.id!==job.device.id)throw Error('Recording identity mismatch');
                        job.socket=socket;job.checked=Date.now();job.status='receiving';socket.setTimeout(45000);
                    }
                    if(job.device.video_terminal_id!==packet.terminal||job.record.channel!==packet.channel)throw Error('Recording identity changed');
                    job.lastPacket=Date.now();job.bytes+=packet.payload.length;
                    if(job.bytes>MAX_BYTES)throw Error('Recording size limit');
                    if(packet.type===3){
                        if(!job.framesCount)continue;
                        const audio=job.audioFrames.push(packet);if(!audio)continue;
                        if(job.audioCodec&&job.audioCodec!==packet.payloadType)throw Error('Audio format changed');
                        job.audioCodec=packet.payloadType;
                        if(packet.payloadType===19)job.audioRate=adtsFormat(audio).rate;
                        job.audioFd??=fs.openSync(path.join(root,job.id,'audio.raw'),'w',0o640);
                        fs.writeSync(job.audioFd,audio);job.audioBytes+=audio.length;continue;
                    }
                    if(packet.type>2)continue;
                    if(job.endSeen)continue;
                    const frame=job.videoFrames.push(packet);if(!frame)continue;
                    if(!job.framesCount&&packet.type!==0)continue;
                    job.videoFd??=fs.openSync(path.join(root,job.id,'video.h264'),'w',0o640);
                    fs.writeSync(job.videoFd,frame);job.framesCount++;
                    job.firstTimestamp??=packet.timestamp;job.lastTimestamp=packet.timestamp;
                    job.receivedSeconds=Math.max(0,Number(job.lastTimestamp-job.firstTimestamp)/1000);
                    // Audio may follow the last video packet in the same transfer.
                    // Drain it before stopping the camera and finalizing the MP4.
                    if(job.receivedSeconds>=job.duration)job.endSeen=Date.now();
                }
            }).catch(error=>{socket.destroy();if(job?.socket===socket)fail(job,'transfer_failed');log('recording_source_rejected',{reason:error.message});}).finally(()=>{if(!socket.destroyed)socket.resume();});
        });
        // A terminal may send FIN immediately after its final media packet.
        // Keep our half open until the buffered packet/authorization work ends.
        socket.on('end',()=>{void chain.finally(()=>socket.end());});
        socket.on('timeout',()=>socket.destroy());socket.on('error',()=>{});
        socket.on('close',()=>{sockets.delete(socket);void chain.finally(()=>{
            if(job?.socket!==socket||!['waiting','receiving'].includes(job.status))return;
            if(job.receivedSeconds>=Math.max(1,job.duration-3))finish(job);else fail(job,'incomplete');
        });});
    });
    server.listen(Number(process.env.RECORDING_PORT||1081),process.env.LISTENER_BIND||'0.0.0.0');
    const api=internalServer(Number(process.env.RECORDING_API_PORT||3004),async(_req,res,url,data)=>{
        const id=Number(data.device_id),device=await registry('id',id);
        if(url==='/jobs'){
            const record=data.record;recordingQuery(record,Number(device.gps_timezone_minutes??60));
            const duration=(Date.parse(record.end)-Date.parse(record.start))/1000;
            if(record.channel<1||record.channel>device.channels||duration>1800)return reply(res,422,{error:'Invalid recording duration'});
            if(active.has(id)||active.size>=4)return reply(res,409,{error:'Recording already transferring'});
            await gps('/status',{device_id:id});
            // Recheck after await to prevent two requests claiming one device.
            if(active.has(id)||active.size>=4)return reply(res,409,{error:'Recording already transferring'});
            const job={id:randomUUID(),device,record,duration,status:'waiting',created:Date.now(),checked:Date.now(),lastPacket:Date.now(),bytes:0,framesCount:0,audioBytes:0,receivedSeconds:0,videoFrames:new MediaFrames(),audioFrames:new AudioFrames()};
            fs.mkdirSync(path.join(root,job.id),{mode:0o750});jobs.set(job.id,job);active.set(id,job);
            void gps('/recordings/start',{device_id:id,...record}).catch(error=>{
                if(job.status!=='waiting')return;
                if(error.status===422)fail(job,'unsupported',false);
                else if(error.status!==503&&!['TimeoutError','AbortError'].includes(error.name))fail(job,'unavailable',false);
            });
            return reply(res,201,{job_id:job.id,status:job.status});
        }
        const match=url.match(/^\/jobs\/([a-f0-9-]{36})(\/cancel)?$/),job=match&&jobs.get(match[1]);
        if(!job||job.device.id!==id||Date.now()-job.created>TTL)return reply(res,404,{error:'Recording expired'});
        if(match[2]){if(['waiting','receiving','processing'].includes(job.status)){fail(job,'cancelled');job.status='cancelled';}return reply(res,200,{status:job.status});}
        return reply(res,200,{job_id:job.id,status:job.status,error:job.error||null,progress:job.status==='ready'?100:Math.min(99,Math.floor(job.receivedSeconds/job.duration*100)),size:job.fileSize||0,audio:!!job.audioBytes});
    });
    const timer=setInterval(()=>{
        for(const job of jobs.values()){
            if(['waiting','receiving'].includes(job.status)){
                if(job.endSeen&&(Date.now()-job.lastPacket>2000||Date.now()-job.endSeen>10000)){finish(job);continue;}
                if(Date.now()-job.lastPacket>45000||Date.now()-job.created>(job.duration*2+120)*1000){fail(job,job.framesCount?'incomplete':'no_stream');continue;}
                if(Date.now()-job.checked>5000&&!job.checking){job.checking=true;registry('id',job.device.id).then(()=>{job.checked=Date.now();}).catch(error=>{if([403,404].includes(error.status))fail(job,'forbidden');}).finally(()=>job.checking=false);}
            }
            if(Date.now()-job.created>TTL){jobs.delete(job.id);void fs.promises.rm(path.join(root,job.id),{recursive:true,force:true,maxRetries:3}).catch(()=>{});}
        }
    },1000);timer.unref();
    // Reclaim only expired directories created by this service.
    for(const entry of fs.readdirSync(root,{withFileTypes:true}))if(entry.isDirectory()&&/^[a-f0-9-]{36}$/.test(entry.name)){
        const folder=path.join(root,entry.name);
        if(Date.now()-fs.statSync(folder).mtimeMs>TTL){void fs.promises.rm(folder,{recursive:true,force:true,maxRetries:3}).catch(()=>{});continue;}
        try{
            const saved=JSON.parse(fs.readFileSync(path.join(folder,'ready.json'),'utf8'));
            if(saved.id===entry.name&&saved.status==='ready'&&Number.isInteger(saved.device?.id)&&Date.now()-saved.created<TTL&&fs.statSync(path.join(folder,'recording.mp4')).isFile())jobs.set(saved.id,saved);
        }catch{}
    }
    const close=()=>{clearInterval(timer);for(const job of jobs.values())fail(job,'service_shutdown');for(const s of sockets)s.destroy();api.close();server.close();};
    log('recordings_listening',{port:Number(process.env.RECORDING_PORT||1081)});return {server,api,close};
}
if(process.argv[1]&&import.meta.url===pathToFileURL(process.argv[1]).href){const service=startRecordings();for(const signal of ['SIGINT','SIGTERM'])process.on(signal,service.close);}
