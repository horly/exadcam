import test from 'node:test';
import assert from 'node:assert/strict';
import net from 'node:net';
import { once } from 'node:events';
import { execFileSync } from 'node:child_process';
import { gpsKeepAliveOptions } from '../src/transport-policy.js';

test('older runtimes retain the safe five-minute policy and JK114 keeps its existing policy', () => {
    assert.deepEqual(gpsKeepAliveOptions('ES500-603','24.15.0'),[true,300000]);
    assert.deepEqual(gpsKeepAliveOptions('ES500-603','24.19.0'),[true,60000,30000,10]);
    assert.deepEqual(gpsKeepAliveOptions('JK114','24.21.0'),[true,30000]);
});

test('Linux kernel receives the intended timers and the socket remains usable', {skip:process.platform!=='linux',timeout:5000}, async t => {
    const server=net.createServer();server.listen(0,'127.0.0.1');await once(server,'listening');
    const accepted=once(server,'connection');
    const client=net.connect(server.address().port,'127.0.0.1');
    const connected=once(client,'connect');
    const [socket]=await accepted;await connected;
    t.after(()=>{socket.destroy();client.destroy();server.close();});
    const options=gpsKeepAliveOptions('ES500-603');socket.setKeepAlive(...options);
    const code='import socket,json;s=socket.socket(fileno=3);print(json.dumps([s.getsockopt(socket.IPPROTO_TCP,k) for k in [socket.TCP_KEEPIDLE,socket.TCP_KEEPINTVL,socket.TCP_KEEPCNT]]))';
    const actual=JSON.parse(execFileSync('python3',['-c',code],{stdio:['ignore','pipe','pipe',socket._handle.fd],encoding:'utf8'}));
    assert.deepEqual(actual,options.length===4?[60,30,10]:[300,1,10]);
    const received=once(socket,'data');client.write('synthetic heartbeat');
    assert.equal((await received)[0].toString(),'synthetic heartbeat');
});
