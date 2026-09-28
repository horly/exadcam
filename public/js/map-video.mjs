// One user-requested channel owns its leases, including automatic replacements.
export class MapVideoChannel {
    constructor({request, attach, reset, notify, schedule = (fn,ms) => setTimeout(fn,ms), cancel = id => clearTimeout(id), now = () => Date.now()}) {
        Object.assign(this, {request, attach, reset, notify, schedule, cancel, now});
        this.generation = 0; this.lease = null; this.timer = null; this.ready = false;
        this.target = null; this.failures = 0;
    }
    queue(task, delay) {
        this.timer = this.schedule(() => { this.timer = null; return task(); }, delay);
    }
    async resume() {
        // A missing timer means a start, heartbeat or recovery is already in flight.
        if (!this.target || this.timer === null) return;
        this.cancel(this.timer); this.timer = null;
        if (this.lease) await this.poll(this.generation);
        else await this.open(this.generation);
    }
    get active() { return this.target !== null; }
    async release(lease, keepalive = false) {
        if (lease) await this.request(`${lease.base}/${lease.lease_id}/stop`, {}, keepalive).catch(() => {});
    }
    markPlaying() { this.failures = 0; }
    async stop(keepalive = false) {
        this.generation++; this.cancel(this.timer); this.timer = null;
        this.target = null; this.ready = false; this.failures = 0;
        const lease = this.lease; this.lease = null;
        this.reset({preserveFrame:false});
        await this.release(lease, keepalive);
    }
    async start(base, channel) {
        const stopping = this.stop(), generation = this.generation;
        await stopping;
        if (generation !== this.generation) return;
        this.target = {base, channel}; this.notify('waiting');
        await this.open(generation);
    }
    async open(generation) {
        if (generation !== this.generation || !this.target) return;
        const {base, channel} = this.target;
        try {
            const result = await this.request(base, {channel});
            const lease = {...result, base};
            if (generation !== this.generation) { await this.release(lease); return; }
            this.lease = lease; this.openedAt = this.now();
            await this.poll(generation);
        } catch (error) { await this.recover(error, generation, 'start'); }
    }
    async poll(generation) {
        if (generation !== this.generation || !this.lease) return;
        const lease = this.lease;
        try {
            const state = await this.request(`${lease.base}/${lease.lease_id}/keepalive`, {});
            if (generation !== this.generation) return;
            if (state.status === 'ready' && !this.ready) {
                this.ready = true; this.notify('buffering');
                this.attach(lease.url, error => { void this.recover(error || Error('Media interrupted'), generation, 'media'); },
                    {startBufferSeconds: lease.startup_buffer_seconds === 4 ? 4 : 8});
            }
            if (!this.ready && this.now() - this.openedAt > 60000) throw Error('Video startup timeout');
            // Detect the first manifest promptly, then return to normal lease renewal.
            if (generation === this.generation) this.queue(() => this.poll(generation), this.ready ? 5000 : 1000);
        } catch (error) { await this.recover(error, generation, 'keepalive'); }
    }
    async recover(error, generation, phase) {
        if (generation !== this.generation || !this.target) return;
        const status = Number(error?.status);
        // A new session always goes through the normal authorization endpoint.
        if ([400,401,403,419,422].includes(status) || (phase === 'start' && status === 404)) {
            this.notify('failed', error); await this.stop(); return;
        }
        const next = ++this.generation;
        this.cancel(this.timer); this.timer = null; this.ready = false;
        const lease = this.lease; this.lease = null;
        this.reset({preserveFrame:true}); this.notify('reconnecting');
        const delay = [3000,5000,10000,20000,30000][Math.min(this.failures++,4)];
        await this.release(lease);
        if (next !== this.generation || !this.target) return;
        this.queue(() => this.open(next), delay);
    }
}
