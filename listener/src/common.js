import http from 'node:http';
import { timingSafeEqual } from 'node:crypto';

export function safeEqual(a, b) {
    const x = Buffer.from(a || ''), y = Buffer.from(b || '');
    return x.length > 0 && x.length === y.length && timingSafeEqual(x, y);
}
export function token() {
    const value = process.env.LISTENER_API_TOKEN || '';
    if (value.length < 32) throw new Error('LISTENER_API_TOKEN is required');
    return value;
}
export function log(event, data = {}) { console.log(JSON.stringify({ time: new Date().toISOString(), event, ...data })); }

export async function post(url, data) {
    const response = await fetch(url, { method: 'POST', headers: { Authorization: `Bearer ${token()}`, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(data), signal: AbortSignal.timeout(10000) });
    if (!response.ok) { const error = new Error(`Service returned ${response.status}`); error.status = response.status; throw error; }
    return response.json();
}
export function registry(kind, terminal) {
    return post(`${process.env.LARAVEL_LISTENER_URL || 'http://127.0.0.1:8081/api/internal/listener'}/resolve`, { kind, terminal: String(terminal) });
}
export function event(data) {
    return post(`${process.env.LARAVEL_LISTENER_URL || 'http://127.0.0.1:8081/api/internal/listener'}/event`, data);
}
export function reply(res, status, data) {
    res.writeHead(status, { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' });
    res.end(JSON.stringify(data));
}
export async function readJson(req) {
    let size = 0; const chunks = [];
    for await (const chunk of req) {
        size += chunk.length;
        if (size > 8192) throw new Error('Request too large');
        chunks.push(chunk);
    }
    return JSON.parse(Buffer.concat(chunks).toString() || '{}');
}
export function internalServer(port, handler, mediaHandler) {
    token();
    const server = http.createServer(async (req, res) => {
        try {
            const url = new URL(req.url, 'http://localhost');
            if (mediaHandler && url.pathname.startsWith('/media/')) return await mediaHandler(req, res, url);
            if (!safeEqual(req.headers.authorization, `Bearer ${token()}`)) return reply(res, 403, { error: 'Forbidden' });
            if (req.method === 'GET' && url.pathname === '/health') return reply(res, 200, { status: 'ok' });
            if (req.method !== 'POST') return reply(res, 405, { error: 'Method not allowed' });
            return await handler(req, res, url.pathname, await readJson(req));
        } catch (error) {
            if (!res.headersSent) reply(res, [403, 404, 409].includes(error.status) ? error.status : 503, { error: 'Request failed' });
            else res.destroy();
            log('request_failed', { reason: error.message });
        }
    });
    server.requestTimeout = 15000;
    server.headersTimeout = 10000;
    server.listen(port, '127.0.0.1');
    return server;
}
