import { test } from 'node:test';
import assert from 'node:assert/strict';
import { encode808, Frames808 } from '../src/protocol.js';

for (const version of ['2013', '2019']) {
    test(`${version}: partial multimedia frame followed by full TCP reads stays connected`, () => {
        const terminal = version === '2013' ? '456789012345' : '00000123456789012345';
        const body = Buffer.alloc(1023, 0x7e); // Maximum body, including wire escaping.
        const encoded = Array.from({ length: 180 }, (_, serial) =>
            encode808({ id: 0x0801, terminal, version, serial, body }));
        const heartbeat = encode808({ id: 0x0002, terminal, version, serial: 180 });
        const wire = Buffer.concat([...encoded, heartbeat]);
        const parser = new Frames808();
        assert.deepEqual(parser.push(wire.subarray(0, 37)), []);
        const messages = [];
        for (let offset = 37; offset < wire.length; offset += 65536) {
            messages.push(...parser.push(wire.subarray(offset, offset + 65536)));
            assert.ok(parser.pending.length < 2092, 'only an incomplete wire frame may remain');
        }
        assert.equal(messages.length, 181);
        assert.deepEqual(messages.map(message => message.serial), Array.from({ length: 181 }, (_, i) => i));
        for (const message of messages.slice(0, -1)) {
            assert.equal(message.id, 0x0801);
            assert.equal(message.terminal, terminal);
            assert.equal(message.version, version);
            assert.deepEqual(message.body, body);
        }
        assert.equal(messages.at(-1).id, 0x0002, 'the heartbeat after the burst is not lost');
        assert.equal(parser.pending.length, 0);
    });
}

test('frame bounds reject oversized incomplete and completed frames independently of TCP read size', () => {
    const oversized = Buffer.alloc(2093); oversized[0] = 0x7e;
    const partial = new Frames808();
    assert.deepEqual(partial.push(oversized.subarray(0, 1000)), []);
    assert.throws(() => partial.push(oversized.subarray(1000)), /GPS frame limit/);
    oversized[oversized.length - 1] = 0x7e;
    assert.throws(() => new Frames808().push(oversized), /GPS frame limit/);
    assert.throws(() => new Frames808().push(Buffer.alloc(65537)), /GPS buffer limit/);
});

test('bad checksum inside a coalesced burst still rejects it', () => {
    const first = encode808({ id: 2, terminal: '456789012345', serial: 1 });
    const bad = encode808({ id: 2, terminal: '456789012345', serial: 2 });
    bad[3] ^= 1;
    assert.throws(() => new Frames808().push(Buffer.concat([first, bad, first])), /checksum/);
});
