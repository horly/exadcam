(() => {
    'use strict';
    const configNode = document.getElementById('server-monitoring-config');
    if (!configNode) return;
    const config = JSON.parse(configNode.textContent), labels = config.labels;
    const root = document.getElementById('server-monitoring-module'), $ = id => document.getElementById('sm-' + id);
    const charts = {}, visible = () => document.body.dataset.view === 'server-monitoring' && !document.hidden;
    let denied = false, timer = null, controller = null, sequence = 0, last = null;
    const valid = value => typeof value === 'number' && Number.isFinite(value);
    const number = (value, digits = 1) => valid(value) ? new Intl.NumberFormat(config.locale, {maximumFractionDigits: digits}).format(value) : '—';
    const percent = value => valid(value) ? number(value) + ' %' : '—';
    const translate = (key, values = {}) => Object.entries(values).reduce((result, [name, value]) => result.replaceAll(':' + name, String(value)), labels[key]);
    const time = value => new Intl.DateTimeFormat(config.locale, {timeZone: 'Africa/Kinshasa', hour: '2-digit', minute: '2-digit', second: '2-digit'}).format(new Date(value));
    function bytes(value, rate = false, decimal = false) {
        if (!valid(value) || value < 0) return '—';
        const units = decimal ? (config.locale === 'fr' ? ['o', 'Ko', 'Mo', 'Go', 'To'] : ['B', 'KB', 'MB', 'GB', 'TB']) : (config.locale === 'fr' ? ['o', 'Kio', 'Mio', 'Gio', 'Tio'] : ['B', 'KiB', 'MiB', 'GiB', 'TiB']);
        const base = decimal ? 1000 : 1024;
        const index = value > 0 ? Math.min(units.length - 1, Math.floor(Math.log(value) / Math.log(base))) : 0;
        const safeIndex = Math.max(0, index);
        return number(value / base ** safeIndex, decimal ? 2 : 1) + ' ' + units[safeIndex] + (rate ? '/s' : '');
    }
    function uptime(value) {
        if (!valid(value)) return labels.unknown;
        return `${Math.floor(value / 86400)} ${labels.days} ${Math.floor(value % 86400 / 3600)} ${labels.hours} ${Math.floor(value % 3600 / 60)} ${labels.minutes}`;
    }
    function state(key) { $('state').textContent = labels[key]; $('state').className = 'sm-state is-' + key; }
    function bar(key, value) { $(key + '-bar').style.width = (valid(value) ? Math.min(100, Math.max(0, value)) : 0) + '%'; }
    function table(body, rows, empty = null) {
        const fragment = document.createDocumentFragment();
        for (const values of rows) {
            const tr = document.createElement('tr');
            for (const value of values) { const td = document.createElement('td'); td.textContent = value; tr.append(td); }
            fragment.append(tr);
        }
        if (!rows.length && empty) {
            const tr = document.createElement('tr'), td = document.createElement('td');
            td.colSpan = 5; td.textContent = empty; tr.append(td); fragment.append(tr);
        }
        body.replaceChildren(fragment);
    }
    function render(data) {
        last = data;
        const {cpu, memory, disk, load, network, system} = data;
        $('cpu').textContent = percent(cpu.usage); $('ram').textContent = percent(memory.percent); $('disk').textContent = percent(disk.percent);
        $('load').textContent = number(load.one, 2);
        $('cpu-detail').textContent = valid(cpu.cores) ? translate('cores', {count: cpu.cores}) : labels.unknown;
        for (const [key, metric] of [['ram', memory], ['disk', disk]]) {
            $(key + '-detail').textContent = translate('used_total', {used: bytes(metric.used, false, key === 'disk'), total: bytes(metric.total, false, key === 'disk')});
        }
        $('load-detail').textContent = [load.one, load.five, load.fifteen].map(v => number(v, 2)).join(' / ') + ' · ' + labels.load_intervals;
        bar('cpu', cpu.usage); bar('ram', memory.percent); bar('disk', disk.percent);
        $('disk-ring-value').textContent = percent(disk.percent);
        $('disk-ring').style.setProperty('--percent', (valid(disk.percent) ? Math.min(100, Math.max(0, disk.percent)) : 0) + '%');
        $('disk-ring').setAttribute('aria-label', labels.disk_usage + ' : ' + (valid(disk.percent) ? percent(disk.percent) : labels.unknown));
        $('disk-free').textContent = translate('free_disk', {free: bytes(disk.available, false, true)});
        $('rx').textContent = bytes(network.rx_rate, true); $('tx').textContent = bytes(network.tx_rate, true);
        table($('interfaces'), (network.interfaces || []).map(i => [i.name, bytes(i.rx), bytes(i.tx), bytes(i.rx_rate, true), bytes(i.tx_rate, true)]), labels.no_interfaces);
        for (const key of ['hostname', 'os', 'php', 'laravel', 'environment']) $('system-' + key).textContent = system[key] || labels.unknown;
        $('system-uptime').textContent = uptime(system.uptime);
        $('system-swap').textContent = memory.swap_total === 0 ? labels.no_swap : translate('used_total', {used: bytes(memory.swap_used), total: bytes(memory.swap_total)});
        $('updated').textContent = translate('updated', {time: time(data.captured_at)});
        state(data.stale ? 'stale' : 'live');
        table($('history-rows'), [...data.history].reverse().map(p => [time(p.time), percent(p.cpu), percent(p.memory), bytes(p.rx, true), bytes(p.tx, true), number(p.load, 2)]));
        updateCharts(data.history);
    }
    function chartOptions(series, kind) {
        const formatter = value => kind === 'network' ? bytes(value, true) : number(value, kind === 'load' ? 2 : 1) + (kind === 'performance' ? ' %' : '');
        return {
            series, responsive: [{breakpoint: 576, options: {chart: {height: 240}}}], chart: {type: 'area', height: 260, fontFamily: 'inherit', foreColor: '#8195ae', toolbar: {show: false}, animations: {enabled: false}, zoom: {enabled: false}, parentHeightOffset: 0},
            colors: kind === 'performance' ? ['#5686bd', '#28a580'] : kind === 'network' ? ['#209ed1', '#8870cc'] : ['#cc7288'],
            stroke: {curve: 'straight', width: 2}, fill: {type: 'gradient', gradient: {opacityFrom: .16, opacityTo: .01}},
            dataLabels: {enabled: false}, markers: {size: series[0]?.data.length === 1 ? 3 : 0},
            grid: {borderColor: '#e8eef6', strokeDashArray: 4},
            xaxis: {type: 'datetime', labels: {datetimeUTC: false, formatter: (value, timestamp) => valid(timestamp) ? time(timestamp) : ''}, axisBorder: {show: false}, axisTicks: {show: false}, tooltip: {enabled: false}},
            yaxis: {min: 0, ...(kind === 'performance' ? {max: value => Math.min(100, Math.max(5, Math.ceil(value * 1.15)))} : {}), labels: {formatter}, tickAmount: 4},
            legend: {show: true, position: 'bottom', fontSize: '11px', onItemClick: {toggleDataSeries: false}, onItemHover: {highlightDataSeries: false}},
            tooltip: {shared: true, x: {formatter: time}, y: {formatter}}, noData: {text: labels.history_empty},
        };
    }
    function updateCharts(history) {
        const definitions = {performance: [['cpu', labels.cpu], ['memory', labels.ram]], network: [['rx', labels.incoming], ['tx', labels.outgoing]], load: [['load', labels.load]]};
        for (const [kind, columns] of Object.entries(definitions)) {
            const message = root.querySelector(`[data-chart-message="${kind}"]`);
            const hasValues = history.some(row => columns.some(([key]) => valid(row[key])));
            message.hidden = Boolean(window.ApexCharts) && hasValues;
            if (!window.ApexCharts) { message.textContent = labels.chart_failed; continue; }
            const series = columns.map(([key, name]) => ({name, data: history.map(p => ({x: p.time, y: valid(p[key]) ? p[key] : null}))}));
            if (!charts[kind]) {
                charts[kind] = new ApexCharts($(kind + '-chart'), chartOptions(series, kind));
                Promise.resolve(charts[kind].render()).catch(() => { message.hidden = false; message.textContent = labels.chart_failed; });
            } else {
                Promise.resolve(charts[kind].updateSeries(series, false)).catch(() => { message.hidden = false; message.textContent = labels.chart_failed; });
            }
        }
    }
    function stop() {
        clearTimeout(timer); controller?.abort(); controller = null; sequence++;
        root.removeAttribute('aria-busy');
    }
    function schedule() { clearTimeout(timer); if (visible() && !denied) timer = setTimeout(() => load(), 5000); }
    async function load() {
        if (!visible() || denied) return;
        stop(); const current = sequence, request = new AbortController(); controller = request;
        const timeout = setTimeout(() => request.abort(), 10000);
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(config.url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal: request.signal});
            if (current !== sequence) return;
            if ([401, 403, 419].includes(response.status) || response.redirected) { denied = true; throw new Error(labels.denied); }
            if (!response.ok) throw new Error(labels.failed);
            const data = await response.json(); if (current !== sequence) return;
            if (!data.available) { state('unavailable'); return; }
            render(data);
        } catch (error) {
            if (current === sequence) { state('failed'); $('updated').textContent = (last ? translate('updated', {time: time(last.captured_at)}) + ' · ' : '') + (error.name === 'AbortError' ? labels.failed : error.message); }
        } finally {
            clearTimeout(timeout);
            if (current === sequence) { controller = null; root.removeAttribute('aria-busy'); schedule(); }
        }
    }
    const changed = () => { stop(); if (visible() && !denied) void load(); };
    document.addEventListener('exadcam:view-changed', changed);
    document.addEventListener('visibilitychange', changed);
    window.addEventListener('pagehide', stop);
    if (visible()) void load();
})();
