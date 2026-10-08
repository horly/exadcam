import {decodeBcd} from './protocol.js';

const invalid = message => Object.assign(Error(message), {status:422});
export function recordingTime(value, offset = 60) {
    if(typeof value!=='string'||!Number.isFinite(Date.parse(value))||!Number.isInteger(offset)||Math.abs(offset)>840)throw invalid('Invalid recording time');
    const date=new Date(Date.parse(value)+offset*60000);
    if(date.getUTCFullYear()<2000||date.getUTCFullYear()>2099)throw invalid('Recording year out of range');
    return Buffer.from(date.toISOString().slice(2,19).replace(/\D/g,''),'hex');
}
export function decodeRecordingTime(bytes,offset=60){
    const text=decodeBcd(bytes),parts=text.match(/../g).map(Number);
    const [year,month,day,hour,minute,second]=parts;
    const d=new Date(Date.UTC(2000+year,month-1,day,hour,minute,second));
    if(month<1||month>12||day<1||d.getUTCDate()!==day||hour>23||minute>59||second>59)throw invalid('Invalid recorded timestamp');
    return new Date(d.getTime()-offset*60000).toISOString();
}
export function recordingQuery({channel=0,start,end},offset=60){
    if(!Number.isInteger(channel)||channel<0||channel>8||Date.parse(end)<=Date.parse(start)||Date.parse(end)-Date.parse(start)>86400000)throw invalid('Invalid recording range');
    const body=Buffer.alloc(24);body[0]=channel;
    recordingTime(start,offset).copy(body,1);recordingTime(end,offset).copy(body,7);
    body[21]=3; // Video, with or without audio; all alarms, streams and storage.
    return body;
}
export function recordingResources(body,offset=60){
    if(body.length<6)throw invalid('Short recording list');
    const serial=body.readUInt16BE(0),count=body.readUInt32BE(2);
    if(count>9000||body.length!==6+count*28)throw invalid('Invalid recording list size');
    const records=[];
    for(let n=0;n<count;n++){
        const b=body.subarray(6+n*28,34+n*28),channel=b[0];
        const start=decodeRecordingTime(b.subarray(1,7),offset),end=decodeRecordingTime(b.subarray(7,13),offset);
        if(channel<1||channel>8||end<=start||b[21]>2||b[22]>2||b[23]>2)throw invalid('Invalid recording entry');
        records.push({channel,start,end,media_type:b[21],stream_type:b[22],storage_type:b[23],size:b.readUInt32BE(24)});
    }
    return {serial,records};
}
export function recordingPlayback(host,port,record,offset=60){
    recordingQuery(record,offset);
    if(!Number.isInteger(record.channel)||record.channel<1||!Number.isInteger(port)||port<1||port>65535||!host||Buffer.byteLength(host)>255)throw invalid('Invalid recording destination');
    for(const field of ['media_type','stream_type','storage_type'])if(!Number.isInteger(record[field])||record[field]<0||record[field]>2)throw invalid('Invalid recording type');
    const address=Buffer.from(host),body=Buffer.alloc(address.length+23),n=address.length;
    body[0]=n;address.copy(body,1);body.writeUInt16BE(port,n+1);
    body[n+5]=record.channel;body[n+6]=record.media_type;body[n+7]=record.stream_type;body[n+8]=record.storage_type;
    recordingTime(record.start,offset).copy(body,n+11);recordingTime(record.end,offset).copy(body,n+17);
    return body;
}
export function recordingStop(channel){const b=Buffer.alloc(9);b[0]=channel;b[1]=2;return b;}

export class RecordingFragments {
    current=null;
    clear(){this.current=null;}
    push(message){
        if(!message.fragmented)return message.body;
        const {packetTotal:total,packetIndex:index}=message;
        if(!Number.isInteger(total)||total<1||total>256||!Number.isInteger(index)||index<1||index>total)throw invalid('Invalid recording fragment count');
        if(this.current&&this.current.expires<Date.now())this.clear();
        // Both commissioned firmwares increment the JT808 serial between
        // fragments. Also accept the standard shared serial. The response body
        // is still correlated to the sole pending 0x9205 request by its serial.
        if(!this.current)this.current={serial:message.serial,base:(message.serial-index)&65535,fixed:true,incremental:true,total,parts:new Map(),size:0,expires:Date.now()+35000};
        const group=this.current;
        group.fixed&&=group.serial===message.serial;
        group.incremental&&=group.base===((message.serial-index)&65535);
        if((!group.fixed&&!group.incremental)||group.total!==total)throw invalid('Mismatched recording fragments');
        if(group.parts.has(index)){
            if(!group.parts.get(index).equals(message.body))throw invalid('Conflicting recording fragment');
        }else{
            group.size+=message.body.length;
            if(group.size>252006)throw invalid('Recording list exceeds limit');
            group.parts.set(index,Buffer.from(message.body));
        }
        if(group.parts.size!==total)return null;
        const body=Buffer.concat(Array.from({length:total},(_,i)=>group.parts.get(i+1)),group.size);
        this.clear();return body;
    }
}
