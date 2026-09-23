import { test } from 'node:test';
import assert from 'node:assert/strict';
import { encode808, decode808, Frames808, position808, Frames1078, MediaFrames, liveRequest } from '../src/protocol.js';

test('2013/2019 framing survives split TCP, coalescing and escaped bytes', () => {
    for (const version of ['2013', '2019']) {
        const terminal = version === '2013' ? '456789012345' : '00000123456789012345';
        const payload = Buffer.from([0x7e, 0x7d, 0, 0xff]);
        const encoded = encode808({ id: 0x0200, version, terminal, serial: 65535, body: payload });
        const decoder = new Frames808();
        assert.deepEqual(decoder.push(encoded.subarray(0, 4)), []);
        const result = decoder.push(Buffer.concat([encoded.subarray(4), encoded]));
        assert.equal(result.length, 2); assert.equal(result[0].terminal, terminal);
        assert.equal(result[0].version, version); assert.equal(result[0].serial, 65535);
        assert.deepEqual(result[0].body, payload);
    }
});

test('bad checksum, BCD, escape and overlong buffers are rejected', () => {
    const encoded = encode808({ id: 2, terminal: '456789012345' });
    encoded[3] ^= 1;
    assert.throws(() => decode808(encoded), /checksum/);
    assert.throws(() => encode808({ id: 2, terminal: 'not-an-imei!' }));
    assert.throws(() => decode808(Buffer.from([0x7e, 0x7d, 0x03, 0x7e])), /escape/);
    assert.throws(() => new Frames808().push(Buffer.alloc(65537)), /limit/);
});

test('position uses hemisphere bits, fix status and configured timezone', () => {
    const body = Buffer.alloc(28);
    body.writeUInt32BE(2 | 4, 4); body.writeUInt32BE(4321000, 8); body.writeUInt32BE(15321000, 12);
    body.writeUInt16BE(452, 18); Buffer.from('260921123456', 'hex').copy(body, 22);
    const result = position808(body, 480);
    assert.equal(result.latitude, -4.321); assert.equal(result.longitude, 15.321); assert.equal(result.speed, 45.2);
    assert.equal(result.recorded_at, '2026-09-21T04:34:56.000Z');
    body.writeUInt32BE(0, 4); assert.equal(position808(body), null);
    body.writeUInt32BE(2, 4); body[23] = 0x13; assert.throws(() => position808(body), /time/);
});

function mediaPacket({ sequence = 1, fragment = 0, timestamp = 1000n, payload = Buffer.from([0, 0, 0, 1, 0x65]) } = {}) {
    const header = Buffer.alloc(30);
    header.writeUInt32BE(0x30316364); header[4] = 0x81; header[5] = 98;
    header.writeUInt16BE(sequence, 6); Buffer.from('456789012345', 'hex').copy(header, 8);
    header[14] = 1; header[15] = fragment; header.writeBigUInt64BE(timestamp, 16); header.writeUInt16BE(payload.length, 28);
    return Buffer.concat([header, payload]);
}
test('JT1078 header parsing and fragmented keyframe reconstruction', () => {
    const one = mediaPacket({ sequence: 65535, fragment: 1, payload: Buffer.from([0, 0]) });
    const two = mediaPacket({ sequence: 0, fragment: 2, payload: Buffer.from([0, 1, 0x65]) });
    const parser = new Frames1078(); assert.deepEqual(parser.push(one.subarray(0, 12)), []);
    const packets = parser.push(Buffer.concat([one.subarray(12), two]));
    assert.equal(packets[0].terminal, '456789012345'); assert.equal(packets[0].channel, 1);
    const merger = new MediaFrames(); assert.equal(merger.push(packets[0]), null);
    assert.deepEqual(merger.push(packets[1]), Buffer.from([0, 0, 0, 1, 0x65]));
    merger.push(packets[0]); assert.throws(() => merger.push({ ...packets[1], sequence: 2 }), /fragment/);
    assert.throws(() => merger.push({ ...packets[0], payloadType: 99 }), /H264/);
});
test('video command advertises TCP destination and a video-only substream', () => {
    const payload = liveRequest('62.171.190.15', 1078, 2), n = payload[0];
    assert.equal(payload.subarray(1, n + 1).toString(), '62.171.190.15');
    assert.equal(payload.readUInt16BE(n + 1), 1078); assert.equal(payload.readUInt16BE(n + 3), 0);
    assert.deepEqual([...payload.subarray(n + 5)], [2, 1, 1]);
});

test('extended JT1078 identities preserve framing, fragmentation and exact identity', () => {
    const terminal = '00000123456789012345';
    const packet = (fragment, sequence, data) => {
        const header = Buffer.alloc(34);
        header.writeUInt32BE(0x30316364); header[4] = 0x81; header[5] = 98;
        header.writeUInt16BE(sequence, 6); Buffer.from(terminal, 'hex').copy(header, 8);
        header[18] = 2; header[19] = fragment; header.writeBigUInt64BE(1234n, 20);
        header.writeUInt16BE(data.length, 32);
        return Buffer.concat([header, data]);
    };
    const parser = new Frames1078(10), media = new MediaFrames();
    const first = packet(1, 4, Buffer.from([0, 0, 0, 1]));
    assert.deepEqual(parser.push(first.subarray(0, 19)), []);
    const packets = parser.push(Buffer.concat([first.subarray(19), packet(2, 5, Buffer.from([0x65, 1, 2]))]));
    assert.equal(packets.length, 2); assert.equal(packets[0].terminal, terminal);
    assert.equal(packets[0].channel, 2); assert.equal(packets[0].timestamp, 1234n);
    assert.equal(media.push(packets[0]), null);
    assert.deepEqual(media.push(packets[1]), Buffer.from([0, 0, 0, 1, 0x65, 1, 2]));
});
