import {spawn} from 'node:child_process';

export const SUPPORTED_AUDIO = [6,7,19]; // G.711 A-law, µ-law, AAC/ADTS.

export function encodeAudioPacket({terminal,channel,codec,sequence,timestamp,payload}) {
    if (!/^\d{12}$|^\d{20}$/.test(terminal) || !SUPPORTED_AUDIO.includes(codec) || !Number.isInteger(channel) || channel < 1 || channel > 8 || !payload.length || payload.length > 8192) throw Error('Invalid audio packet');
    const offset = terminal.length / 2 - 6, packet = Buffer.alloc(26+offset+payload.length);
    packet.writeUInt32BE(0x30316364); packet[4]=0x81; packet[5]=0x80|codec; packet.writeUInt16BE(sequence&65535,6);
    Buffer.from(terminal,'hex').copy(packet,8); packet[14+offset]=channel; packet[15+offset]=0x30;
    packet.writeBigUInt64BE(BigInt(Math.max(0,Math.round(timestamp))),16+offset); packet.writeUInt16BE(payload.length,24+offset);
    payload.copy(packet,26+offset); return packet;
}

// G.711 is one encoded byte per sample. Use the terminal's advertised frame
// length (ES500: 80 bytes / 10 ms), not a universal 20 ms packet.
export function g711FrameSize(capabilities, rate) {
    const size = capabilities.frame_length;
    return Number.isInteger(size) && size >= rate * 0.005 && size <= rate * 0.12
        ? size : Math.round(rate * 0.02);
}

export class AudioFrames {
    current = null;
    push(packet) {
        if (packet.type !== 3 || !SUPPORTED_AUDIO.includes(packet.payloadType)) throw Error('Unsupported audio codec');
        if(packet.payload.length>8192)throw Error('Audio frame too large');
        if (packet.fragment === 0) {this.current=null;return packet.payload;}
        if (packet.fragment === 1) {this.current={timestamp:packet.timestamp,sequence:packet.sequence,codec:packet.payloadType,parts:[packet.payload],size:packet.payload.length};return null;}
        const frame=this.current;
        if (!frame || frame.timestamp!==packet.timestamp || frame.codec!==packet.payloadType || ((frame.sequence+1)&65535)!==packet.sequence) throw Error('Incomplete audio frame');
        frame.sequence=packet.sequence;frame.size+=packet.payload.length;
        if(frame.size>8192)throw Error('Audio frame too large');
        frame.parts.push(packet.payload);
        if(packet.fragment===2){this.current=null;return Buffer.concat(frame.parts,frame.size);}
        return null;
    }
}

export class AdtsFrames {
    pending=Buffer.alloc(0);
    push(chunk) {
        this.pending=Buffer.concat([this.pending,chunk]);
        if(this.pending.length>32768)throw Error('Audio buffer exceeded');
        const frames=[];
        while(this.pending.length>=7){
            const p=this.pending;
            if(p[0]!==255||(p[1]&0xf6)!==0xf0)throw Error('Invalid ADTS audio');
            const size=((p[3]&3)<<11)|(p[4]<<3)|(p[5]>>5);
            if(size<7||size>8192)throw Error('Invalid ADTS frame size');
            if(p.length<size)break;
            frames.push(Buffer.from(p.subarray(0,size)));this.pending=p.subarray(size);
        }
        return frames;
    }
}

export function adtsFormat(frame) {
    if(frame.length<7||frame[0]!==255||(frame[1]&0xf6)!==0xf0)throw Error('AAC header missing');
    const rate=[96000,88200,64000,48000,44100,32000,24000,22050,16000,12000,11025,8000,7350][(frame[2]>>2)&15];
    const channels=((frame[2]&1)<<2)|(frame[3]>>6);
    if(!rate||rate<8000||rate>48000||channels!==1)throw Error('Unsupported AAC audio format');
    return {rate,channels};
}

// Separate from HLS: conversations must not inherit its fifteen-second reserve.
export function audioTranscoder({codec,rate=8000,encode=false,gain=1,onData,onError}) {
    if(!SUPPORTED_AUDIO.includes(codec)||![8000,11025,12000,16000,22050,24000,32000,44100,48000].includes(rate))throw Error('Unsupported audio format');
    if(![1,2,4].includes(gain))throw Error('Invalid audio gain');
    const boost=encode&&gain>1?['-af',`volume=${gain},alimiter=limit=0.95:level=false:latency=true`]:[];
    const format={6:'alaw',7:'mulaw',19:'aac'}[codec];
    const input=encode?['-f','s16le','-ar','16000','-ac','1']:['-f',format,...(codec===19?[]:['-ar',String(rate),'-ac','1'])];
    const output=encode?['-ac','1','-ar',String(rate),'-c:a',codec===19?'aac':codec===6?'pcm_alaw':'pcm_mulaw',...(codec===19?['-b:a','24k','-profile:a','aac_low']:[]),'-f',codec===19?'adts':format]:['-ac','1','-ar','16000','-f','s16le'];
    const child=spawn(process.env.FFMPEG_PATH||'/usr/bin/ffmpeg',['-hide_banner','-loglevel','error','-nostdin','-probesize','1024','-analyzeduration','0',...input,'-i','pipe:0',...boost,...output,'-flush_packets','1','pipe:1'],{stdio:['pipe','pipe','pipe']});
    let closed=false, diagnostic='';
    const fail=()=>{if(!closed)onError(new Error('Audio transcoder failed'));};
    child.stdout.on('data',data=>{if(!closed)onData(data);});
    child.stderr.on('data',data=>{diagnostic=(diagnostic+data.toString()).slice(-500);});
    child.on('error',fail);child.stdin.on('error',fail);
    child.on('close',code=>{if(!closed){closed=true;onError(new Error(`Audio transcoder exited (${code})`));}});
    return {
        write(data){if(closed||child.stdin.writableLength>32768)throw Error('Audio connection too slow');if(!child.stdin.destroyed)child.stdin.write(data);},
        close(){if(closed)return;closed=true;child.stdin.destroy();child.kill('SIGTERM');const timer=setTimeout(()=>{if(child.exitCode===null)child.kill('SIGKILL');},1500);timer.unref();},
    };
}
