// Switching vehicles is coordinated across both independently owned channels.
export class DashboardVideoSelection {
    constructor(channels, baseUrl) {
        this.channels = channels; this.baseUrl = baseUrl; this.generation = 0; this.device = null;
    }
    async clear(keepalive = false) {
        this.generation++; this.device = null;
        await Promise.allSettled(this.channels.map(({session}) => session.stop(keepalive)));
    }
    async choose(device) {
        const stopping = this.clear(), generation = this.generation;
        this.device = device;
        await stopping;
        if (generation !== this.generation || !device) return;
        await Promise.allSettled(this.channels.filter(({channel}) => channel <= device.channels)
            .map(({session,channel}) => session.start(`${this.baseUrl}/${device.device_id}/live`, channel)));
    }
}
