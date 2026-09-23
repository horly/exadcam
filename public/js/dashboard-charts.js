(() => {
    'use strict';

    const dataElement = document.getElementById('dashboard-chart-data');
    if (!dataElement) return;

    let payload = JSON.parse(dataElement.textContent);
    const periodButtons = [...document.querySelectorAll('[data-chart-period]')];
    const number = new Intl.NumberFormat(payload.locale, { maximumFractionDigits: 0 });
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const rootStyle = getComputedStyle(document.documentElement);
    const colors = {
        primary: rootStyle.getPropertyValue('--exad-primary').trim(),
        secondary: rootStyle.getPropertyValue('--exad-chart-secondary').trim(),
        offline: '#c5d0df',
        muted: '#7c8da3',
        ink: '#203b5d',
    };
    let activityChart;
    let statusChart;
    let mounting;
    let periodKey = 'day';

    const seriesFor = period => [
        { name: payload.labels.online, data: period.online },
        { name: payload.labels.moving, data: period.moving },
    ];
    const baseChart = {
        fontFamily: getComputedStyle(document.body).fontFamily,
        foreColor: colors.muted,
        toolbar: { show: false },
        zoom: { enabled: false },
        parentHeightOffset: 0,
        redrawOnParentResize: true,
        redrawOnWindowResize: true,
        animations: { enabled: !reducedMotion, speed: 400, dynamicAnimation: { speed: 250 } },
    };

    function showFallback() {
        document.querySelector('[data-chart-error]').hidden = false;
        document.getElementById('activity-chart-table').open = true;
        periodButtons.forEach(button => { button.disabled = false; });
    }

    function updateTable(period) {
        const rows = period.categories.map((label, index) => {
            const row = document.createElement('tr');
            [label, period.online[index], period.moving[index]].forEach((value, column) => {
                const cell = document.createElement(column === 0 ? 'th' : 'td');
                if (column === 0) cell.scope = 'row';
                cell.textContent = String(value);
                row.append(cell);
            });
            return row;
        });
        document.querySelector('[data-chart-table-body]').replaceChildren(...rows);
        document.querySelector('[data-chart-table-caption]').textContent = period.caption;
        document.querySelector('[data-chart-caption]').textContent = period.caption;
    }

    async function mountCharts() {
        if (typeof window.ApexCharts !== 'function') {
            showFallback();
            return;
        }
        const day = payload.periods.day;
        activityChart = new window.ApexCharts(document.getElementById('fleet-activity-chart'), {
            chart: { ...baseChart, type: 'area', height: 250 },
            series: seriesFor(day),
            colors: [colors.primary, colors.secondary],
            dataLabels: { enabled: false },
            stroke: { curve: 'straight', width: [3, 2], dashArray: [0, 5] },
            fill: { type: 'gradient', gradient: { shadeIntensity: 0, opacityFrom: 0.25, opacityTo: 0.02, stops: [0, 100] } },
            markers: { size: 0, hover: { size: 5 } },
            grid: { borderColor: '#e9eef4', strokeDashArray: 4, padding: { top: 0, bottom: 0, left: 8, right: 18 } },
            legend: { show: false },
            xaxis: {
                categories: day.categories,
                axisBorder: { show: false }, axisTicks: { show: false },
                labels: { style: { fontSize: '10px' }, rotate: 0, hideOverlappingLabels: true },
                tooltip: { enabled: false },
            },
            yaxis: { min: 0, max: Math.max(1,payload.total), tickAmount: 3, labels: { formatter: value => number.format(value), style: { fontSize: '10px' } } },
            tooltip: { shared: true, intersect: false, theme: 'light', y: { formatter: value => `${number.format(value)} ${payload.labels.vehicles}` } },
            responsive: [{ breakpoint: 576, options: { chart: { height: 230 }, grid: { padding: { left: 0, right: 8 } } } }],
        });
        statusChart = new window.ApexCharts(document.getElementById('dashcam-status-chart'), {
            chart: { ...baseChart, type: 'donut', height: 206 },
            series: payload.status.series,
            labels: payload.status.labels,
            colors: [colors.primary, colors.offline],
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 5, colors: ['#fff'] },
            plotOptions: { pie: { expandOnClick: false, donut: { size: '78%', labels: {
                show: true,
                name: { show: true, fontSize: '11px', fontWeight: 500, color: colors.muted, offsetY: 22 },
                value: { fontSize: '32px', fontWeight: 700, color: colors.ink, offsetY: -14, formatter: value => number.format(value) },
                total: { show: true, showAlways: true, label: payload.labels.dashcams, color: colors.muted, fontSize: '11px', fontWeight: 500, formatter: () => number.format(payload.camera_total) },
            } } } },
            states: { hover: { filter: { type: 'lighten', value: 0.04 } }, active: { filter: { type: 'none' } } },
            tooltip: { theme: 'light', y: { formatter: value => number.format(value) } },
        });
        await Promise.all([activityChart.render(), statusChart.render()]);
    }

    function showCharts() {
        if (document.body.dataset.view !== 'overview') return;
        if (!mounting) mounting = mountCharts().catch(showFallback);
        else mounting.then(() => Promise.all([
            activityChart?.updateOptions({}, false, false),
            statusChart?.updateOptions({}, false, false),
        ])).catch(showFallback);
    }

    periodButtons.forEach(button => button.addEventListener('click', async () => {
        const key = button.dataset.chartPeriod;
        if (!Object.hasOwn(payload.periods, key)) return;
        periodKey = key;
        const period = payload.periods[key];
        periodButtons.forEach(item => {
            const active = item === button;
            item.classList.toggle('active', active);
            item.setAttribute('aria-pressed', String(active));
            item.disabled = true;
        });
        updateTable(period);
        try {
            await mounting;
            await activityChart?.updateOptions({ series: seriesFor(period), xaxis: { categories: period.categories } }, false, !reducedMotion);
        } catch {
            showFallback();
        } finally {
            periodButtons.forEach(item => { item.disabled = false; });
        }
    }));

    document.addEventListener('exadcam:dashboard-data', async event => {
        if (!event.detail.charts) return;
        payload = event.detail.charts;
        const period = payload.periods[periodKey]; updateTable(period);
        document.querySelectorAll('[data-dashcam-series]').forEach(node => { node.textContent = payload.status.series[Number(node.dataset.dashcamSeries)]; });
        try { await mounting; await activityChart?.updateOptions({series:seriesFor(period),xaxis:{categories:period.categories},yaxis:{max:Math.max(1,payload.total)}},false,false); await statusChart?.updateSeries(payload.status.series,false); } catch { showFallback(); }
    });
    document.addEventListener('exadcam:view-changed', showCharts);
    showCharts();
})();
