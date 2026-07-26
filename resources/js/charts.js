/**
 * Renders every [data-chart-type] container on the page. Chart.js handles
 * bar/line/pie/donut/scatter/histogram/radar; ApexCharts handles
 * heatmap/treemap/boxplot, which Chart.js has no built-in support for (see
 * App\Enums\ChartType::usesApexCharts()).
 *
 * Module Export: every rendered chart also gets a "PNG" button, wired to
 * each library's own export capability (Chart.js canvas.toBase64Image(),
 * ApexCharts chart.dataURI()) - no backend round-trip needed since the
 * chart is already drawn in the browser.
 *
 * Module Dashboard "filtre global": updateChart() lets the dashboard filter
 * bar redraw a single already-rendered chart in place with fresh (filtered)
 * data, without a full page reload.
 */
import Chart from 'chart.js/auto';
import ApexCharts from 'apexcharts';

const APEX_CHART_TYPES = ['heatmap', 'treemap', 'boxplot'];

function attachDownloadButton(container, name, getDataUri) {
    if (container._downloadButton) {
        container._downloadButton.remove();
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-outline-secondary mb-1';
    button.textContent = 'PNG';
    button.addEventListener('click', () => {
        Promise.resolve(getDataUri()).then((uri) => {
            if (!uri) {
                return;
            }
            const link = document.createElement('a');
            link.href = uri;
            link.download = `${name || 'graphique'}.png`;
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    });
    container.parentElement.insertBefore(button, container);
    container._downloadButton = button;
}

function renderChartJs(container, chartType, data, name) {
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);
    const options = { responsive: true, maintainAspectRatio: false };
    let chart;

    if (chartType === 'bar' || chartType === 'line') {
        // Module ML "prévision": a forecast sends multiple named series
        // (Historique/Prévision) sharing one label axis instead of the
        // usual single series - fall back to the plain single-dataset shape
        // when it doesn't.
        const datasets = data.datasets || [{ label: name, data: data.data }];
        chart = new Chart(canvas, {
            type: chartType,
            data: { labels: data.labels, datasets },
            options,
        });
    } else if (chartType === 'pie' || chartType === 'donut') {
        chart = new Chart(canvas, {
            type: chartType === 'donut' ? 'doughnut' : 'pie',
            data: { labels: data.labels, datasets: [{ data: data.data }] },
            options,
        });
    } else if (chartType === 'scatter') {
        // Module ML "segmentation": clustering sends one group per cluster
        // so each renders as its own colored series - fall back to a single
        // series otherwise.
        const datasets = data.groups
            ? data.groups.map((group) => ({ label: group.label, data: group.points }))
            : [{ label: name, data: data.points }];
        chart = new Chart(canvas, {
            type: 'scatter',
            data: { datasets },
            options,
        });
    } else if (chartType === 'histogram') {
        chart = new Chart(canvas, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Fréquence', data: data.counts }] },
            options,
        });
    } else if (chartType === 'radar') {
        chart = new Chart(canvas, {
            type: 'radar',
            data: { labels: data.labels, datasets: data.datasets },
            options,
        });
    }

    if (chart) {
        container._chartInstance = chart;
        attachDownloadButton(container, name, () => chart.toBase64Image());
    }
}

function renderApexChart(container, chartType, data, name) {
    let options;

    if (chartType === 'heatmap') {
        options = {
            chart: { type: 'heatmap', height: 320, toolbar: { show: false } },
            series: data.labels.map((label, i) => ({
                name: label,
                data: data.labels.map((column, j) => ({ x: column, y: data.matrix[i][j] })),
            })),
            dataLabels: { enabled: true },
        };
    } else if (chartType === 'treemap') {
        options = {
            chart: { type: 'treemap', height: 320, toolbar: { show: false } },
            series: [{ data: data.data.map((d) => ({ x: d.name, y: d.value })) }],
        };
    } else if (chartType === 'boxplot') {
        options = {
            chart: { type: 'boxPlot', height: 320, toolbar: { show: false } },
            series: [{ type: 'boxPlot', data: data.data }],
        };
    }

    const chart = new ApexCharts(container, options);
    chart.render();
    container._chartInstance = chart;
    attachDownloadButton(container, name, () => chart.dataURI().then(({ imgURI }) => imgURI));
}

function renderContainer(container) {
    const chartType = container.dataset.chartType;
    const data = JSON.parse(container.dataset.chartPayload || '{}');
    const name = container.dataset.chartName || '';

    try {
        if (APEX_CHART_TYPES.includes(chartType)) {
            renderApexChart(container, chartType, data, name);
        } else {
            renderChartJs(container, chartType, data, name);
        }
    } catch (error) {
        console.error('Chart render failed', chartType, error);
    }
}

function renderAll() {
    document.querySelectorAll('[data-chart-type]').forEach((container) => {
        if (container.dataset.chartRendered) {
            return;
        }
        container.dataset.chartRendered = '1';
        renderContainer(container);
    });
}

/** Redraws an already-rendered chart container with a new data payload. */
function updateChart(container, newData) {
    if (container._chartInstance) {
        container._chartInstance.destroy();
        container._chartInstance = null;
    }
    container.innerHTML = '';
    container.dataset.chartPayload = JSON.stringify(newData || {});
    renderContainer(container);
}

window.DataForgeCharts = { updateChart };

document.addEventListener('DOMContentLoaded', renderAll);
