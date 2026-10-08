export function decodeBcd(bytes) {
    const text = bytes.toString('hex');
    if (!/^\d+$/.test(text)) throw new Error('Invalid BCD');
    return text;
}

export function encode808({ id, terminal, version = '2013', serial = 0, body = Buffer.alloc(0) }) {
    const modern = version === '2019';
    if (!new RegExp(`^\\d{${modern ? 20 : 12}}$`).test(terminal) || body.length > 1023) throw new Error('Invalid outgoing frame');
    const header = Buffer.alloc(modern ? 17 : 12);
    header.writeUInt16BE(id, 0);
    header.writeUInt16BE(body.length | (modern ? 0x4000 : 0), 2);
    if (modern) header[4] = 1;
    Buffer.from(terminal, 'hex').copy(header, modern ? 5 : 4);
    header.writeUInt16BE(serial & 0xffff, modern ? 15 : 10);
    const raw = Buffer.concat([header, body]);
    let checksum = 0;
    for (const byte of raw) checksum ^= byte;
    const output = [0x7e];
    for (const byte of [...raw, checksum]) {
        if (byte === 0x7e) output.push(0x7d, 0x02);
        else if (byte === 0x7d) output.push(0x7d, 0x01);
        else output.push(byte);
    }
    output.push(0x7e);
    return Buffer.from(output);
}

export function decode808(frame) {
    if (frame[0] !== 0x7e || frame.at(-1) !== 0x7e) throw new Error('Invalid delimiters');
    const bytes = [];
    for (let i = 1; i < frame.length - 1; i++) {
        let byte = frame[i];
        if (byte === 0x7d) {
            const next = frame[++i];
            if (next !== 1 && next !== 2) throw new Error('Invalid escape');
            byte = next === 1 ? 0x7d : 0x7e;
        }
        bytes.push(byte);
    }
    const raw = Buffer.from(bytes);
    if (raw.length < 13 || raw.reduce((sum, byte) => sum ^ byte, 0) !== 0) throw new Error('Invalid checksum');
    const flags = raw.readUInt16BE(2);
    const modern = Boolean(flags & 0x4000);
    const fragmented = Boolean(flags & 0x2000);
    const size = (modern ? 17 : 12) + (fragmented ? 4 : 0);
    if ((flags & 0x1c00) !== 0 || raw.length !== size + (flags & 0x3ff) + 1) throw new Error('Unsupported encryption or invalid length');
    if (modern && raw[4] !== 1) throw new Error('Unsupported version');
    return { id: raw.readUInt16BE(0), version: modern ? '2019' : '2013', fragmented,
        terminal: decodeBcd(raw.subarray(modern ? 5 : 4, modern ? 15 : 10)),
        serial: raw.readUInt16BE(modern ? 15 : 10), ...(fragmented ? {packetTotal:raw.readUInt16BE(size-4),packetIndex:raw.readUInt16BE(size-2)} : {}), body: raw.subarray(size, -1) };
}

export class Frames808 {
    pending = Buffer.alloc(0);
    push(chunk) {
        // Bound each TCP read separately from the unfinished frame. A valid
        // 64 KiB read can follow a partial frame during ES500 multimedia bursts.
        if (chunk.length > 65536) throw new Error('GPS buffer limit');
        this.pending = Buffer.concat([this.pending, chunk]);
        // 2019 header + fragment fields + maximum body + checksum, all escaped,
        // plus the two delimiters. No individual JT808 wire frame can be larger.
        const maxFrameBytes = 2 * (17 + 4 + 1023 + 1) + 2;
        const frames = [];
        while (this.pending.length) {
            if (this.pending[0] !== 0x7e) throw new Error('Invalid frame start');
            const end = this.pending.indexOf(0x7e, 1);
            if (end === -1) break;
            if (end === 1) { this.pending = this.pending.subarray(1); continue; }
            if (end + 1 > maxFrameBytes) throw new Error('GPS frame limit');
            frames.push(decode808(this.pending.subarray(0, end + 1)));
            this.pending = this.pending.subarray(end + 1);
        }
        if (this.pending.length > maxFrameBytes) throw new Error('GPS frame limit');
        // Release the drained read rather than retaining it through a subarray.
        this.pending = Buffer.from(this.pending);
        return frames;
    }
}

export function position808(body, timezoneMinutes = 480) {
    if (body.length < 28) throw new Error('Short GPS position');
    const status = body.readUInt32BE(4);
    if (!(status & 2)) return null; // No satellite fix: never persist an invented position.
    const latitude = body.readUInt32BE(8) / 1e6 * (status & 4 ? -1 : 1);
    const longitude = body.readUInt32BE(12) / 1e6 * (status & 8 ? -1 : 1);
    const speed = body.readUInt16BE(18) / 10;
    if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180 || speed > 1000) throw new Error('Invalid GPS coordinates');
    const time = decodeBcd(body.subarray(22, 28)).match(/../g).map(Number);
    const [yy, month, day, hour, minute, second] = time;
    const local = new Date(Date.UTC(2000 + yy, month - 1, day, hour, minute, second));
    if (month < 1 || month > 12 || local.getUTCDate() !== day || hour > 23 || minute > 59 || second > 59) throw new Error('Invalid GPS time');
    const date = new Date(local.getTime() - timezoneMinutes * 60000);
    if (date.getTime() < Date.UTC(2020, 0, 1) || date.getTime() > Date.now() + 86400000) throw new Error('GPS time out of range');
    return { latitude, longitude, speed, recorded_at: date.toISOString(), alarm: body.readUInt32BE(0), status };
}

export function liveRequest(host, port, channel, dataType = 1, streamType = 1) {
    const address = Buffer.from(host, 'ascii');
    if (!address.length || address.length > 255 || port < 1024 || port > 65535 || channel < 1 || channel > 8 || ![0,1,2,3].includes(dataType) || ![0,1].includes(streamType)) throw new Error('Invalid video target');
    const result = Buffer.alloc(address.length + 8);
    result[0] = address.length;
    address.copy(result, 1);
    result.writeUInt16BE(port, 1 + address.length);
    result.writeUInt16BE(0, 3 + address.length); // TCP only for this first implementation.
    result[5 + address.length] = channel;
    result[6 + address.length] = dataType;
    result[7 + address.length] = streamType;
    return result;
}

export function audioCapabilities(body) {
    if (body.length !== 10) throw new Error('Invalid audiovisual capabilities');
    return { codec:body[0], channels:body[1], sample_rate:[8000,22050,44100,48000][body[2]] ?? null,
        sample_bits:[8,16,32][body[3]] ?? null, frame_length:body.readUInt16BE(4), output:body[6] === 1,
        video_codec:body[7], audio_channels:body[8], video_channels:body[9] };
}

export class Frames1078 {
    pending = Buffer.alloc(0);
    constructor(terminalBytes = 6) {
        this.terminalBytes = typeof terminalBytes === 'function' ? null : terminalBytes;
        this.resolveTerminalBytes = typeof terminalBytes === 'function' ? terminalBytes : null;
    }
    push(chunk) {
        this.pending = Buffer.concat([this.pending, chunk]);
        if (this.pending.length > 2 * 1024 * 1024) throw new Error('Media buffer limit');
        const packets = [];
        while (this.pending.length >= 16) {
            const p = this.pending;
            if (p.readUInt32BE(0) !== 0x30316364 || p[4] !== 0x81) throw new Error('Invalid JT1078 header');
            if (this.terminalBytes === null) {
                if (p.length < 20) break;
                this.terminalBytes = this.resolveTerminalBytes(p);
            }
            if (![6, 10].includes(this.terminalBytes)) throw new Error('Invalid terminal identity length');
            const offset = this.terminalBytes - 6;
            if (p.length < 16 + offset) break;
            const type = p[15 + offset] >> 4, fragment = p[15 + offset] & 15;
            if (type > 4 || fragment > 3) throw new Error('Invalid media type');
            const header = (type <= 2 ? 30 : type === 3 ? 26 : 18) + offset;
            if (p.length < header) break;
            const size = p.readUInt16BE(header - 2);
            if (size === 0 || size > 65500) throw new Error('Invalid media size');
            if (p.length < header + size) break;
            packets.push({ terminal: decodeBcd(p.subarray(8, 14 + offset)), channel: p[14 + offset], type, fragment,
                payloadType: p[5] & 127, sequence: p.readUInt16BE(6), timestamp: type === 4 ? 0n : p.readBigUInt64BE(16 + offset),
                payload: Buffer.from(p.subarray(header, header + size)) });
            this.pending = p.subarray(header + size);
        }
        return packets;
    }
}

export class MediaFrames {
    current = null;
    push(packet) {
        if (packet.type > 2) return null;
        if (packet.payloadType !== 98) throw new Error('Only H264 video is supported in this release');
        if (packet.fragment === 0) { this.current = null; return packet.payload; }
        if (packet.fragment === 1) {
            this.current = { timestamp: packet.timestamp, sequence: packet.sequence, chunks: [packet.payload], size: packet.payload.length };
            return null;
        }
        const frame = this.current;
        if (!frame || frame.timestamp !== packet.timestamp || ((frame.sequence + 1) & 65535) !== packet.sequence) {
            this.current = null;
            throw new Error('Missing or inconsistent media fragment');
        }
        frame.sequence = packet.sequence;
        frame.size += packet.payload.length;
        if (frame.size > 4 * 1024 * 1024) throw new Error('Media frame too large');
        frame.chunks.push(packet.payload);
        if (packet.fragment === 2) { this.current = null; return Buffer.concat(frame.chunks, frame.size); }
        return null;
    }
}
