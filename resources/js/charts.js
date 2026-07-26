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
 *
 * Thème néon: ni Chart.js ni ApexCharts ne connaissent data-bs-theme - sans
 * ça, le texte des axes/légendes reste noir par défaut et devient illisible
 * sur le fond sombre. themeColors() lit les tokens CSS réels (--df-*) plutôt
 * que de dupliquer une palette ici, pour rester la seule source de vérité.
 * Un MutationObserver redessine tout graphique déjà affiché quand
 * l'utilisateur bascule clair/sombre sans recharger la page.
 */
import Chart from 'chart.js/auto';
import ApexCharts from 'apexcharts';

const APEX_CHART_TYPES = ['heatmap', 'treemap', 'boxplot'];

function isDarkTheme() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function themeColors() {
    return {
        text: cssVar('--df-text-muted'),
        grid: cssVar('--df-border'),
        accent: cssVar('--df-ember'),
        accentStrong: cssVar('--df-ember-strong'),
    };
}

/**
 * --df-ember is always a plain 3/6-digit hex string in both themes - a small
 * local helper avoids depending on Chart.js's own Chart.helpers.color(),
 * which throws inside this bundle (its color-parsing dependency isn't wired
 * up the way "chart.js/auto" is imported here) and silently broke every
 * single Chart.js render on the page.
 */
function hexToRgba(hex, alpha) {
    const clean = hex.replace('#', '');
    const full = clean.length === 3 ? clean.split('').map((c) => c + c).join('') : clean;
    const value = parseInt(full, 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

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
    let chart;

    // Passé en options par instance plutôt qu'en mutant Chart.defaults
    // globalement (une tentative précédente d'écrire directement dans
    // Chart.defaults.color/plugins.legend.labels.color cassait la
    // construction de TOUT graphique Chart.js sur la page, silencieusement -
    // Chart.js n'apprécie pas qu'on réécrive certaines branches de son objet
    // Defaults partagé après coup). Cette forme est aussi celle recommandée
    // par Chart.js pour ne pas affecter d'autres graphiques sur la page.
    const colors = themeColors();
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        color: colors.text,
        plugins: { legend: { labels: { color: colors.text } } },
    };
    if (chartType === 'bar' || chartType === 'line' || chartType === 'histogram' || chartType === 'scatter') {
        options.scales = {
            x: { ticks: { color: colors.text }, grid: { color: colors.grid } },
            y: { ticks: { color: colors.text }, grid: { color: colors.grid } },
        };
    } else if (chartType === 'radar') {
        options.scales = {
            r: {
                ticks: { color: colors.text, backdropColor: 'transparent' },
                grid: { color: colors.grid },
                angleLines: { color: colors.grid },
                pointLabels: { color: colors.text },
            },
        };
    }

    // Un dataset simple (pas de série multiple fournie par le backend)
    // prend la couleur d'accent du thème courant plutôt que la rotation de
    // couleurs par défaut de Chart.js - cohérence visuelle avec le reste de
    // l'interface (voir Thème néon dans l'en-tête du fichier).
    const accentFill = hexToRgba(colors.accent, 0.7);
    const accentFillSoft = hexToRgba(colors.accent, 0.15);

    if (chartType === 'bar' || chartType === 'line') {
        // Module ML "prévision": a forecast sends multiple named series
        // (Historique/Prévision) sharing one label axis instead of the
        // usual single series - fall back to the plain single-dataset shape
        // when it doesn't.
        const datasets = data.datasets || [{
            label: name,
            data: data.data,
            backgroundColor: chartType === 'bar' ? accentFill : accentFillSoft,
            borderColor: colors.accent,
            fill: chartType === 'line',
            tension: chartType === 'line' ? 0.3 : undefined,
        }];
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
            : [{ label: name, data: data.points, backgroundColor: accentFill }];
        chart = new Chart(canvas, {
            type: 'scatter',
            data: { datasets },
            options,
        });
    } else if (chartType === 'histogram') {
        chart = new Chart(canvas, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Fréquence', data: data.counts, backgroundColor: accentFill, borderColor: colors.accent }] },
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
    const dark = isDarkTheme();
    const colors = themeColors();
    const themeOptions = {
        theme: { mode: dark ? 'dark' : 'light' },
        chart: { foreColor: colors.text, background: 'transparent' },
        grid: { borderColor: colors.grid },
    };
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

    options.chart = { ...options.chart, ...themeOptions.chart };
    options.theme = themeOptions.theme;
    options.grid = { ...options.grid, ...themeOptions.grid };

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

/**
 * Thème néon: le bouton clair/sombre (theme-toggle.js) ne fait que basculer
 * data-bs-theme sur <html>, sans recharger la page - un graphique déjà
 * dessiné garde sinon ses couleurs figées de l'ancien thème. On observe cet
 * attribut et on redessine chaque graphique avec sa propre donnée déjà
 * stockée (container.dataset.chartPayload), sans réclamer quoi que ce soit
 * au serveur.
 */
function redrawAllForThemeChange() {
    document.querySelectorAll('[data-chart-type][data-chart-rendered]').forEach((container) => {
        updateChart(container, JSON.parse(container.dataset.chartPayload || '{}'));
    });
}

new MutationObserver((mutations) => {
    if (mutations.some((m) => m.attributeName === 'data-bs-theme')) {
        redrawAllForThemeChange();
    }
}).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

window.DataForgeCharts = { updateChart };

document.addEventListener('DOMContentLoaded', renderAll);
