import { init, use } from 'echarts/core';
import { BarChart, LineChart, PieChart, SankeyChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TitleComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

use([BarChart, LineChart, PieChart, SankeyChart, GridComponent, LegendComponent, TitleComponent, TooltipComponent, CanvasRenderer]);

const NARROW_WIDTH = 520;

const COLORS = {
    income: '#10b981',
    expense: '#f43f5e',
    saved: '#6366f1',
    invested: '#f59e0b',
    hub: '#4f46e5',
    leftOver: '#94a3b8',
    reserves: '#64748b',
    text: '#334155',
    muted: '#64748b',
    grid: '#e2e8f0',
};

const PALETTE = ['#6366f1', '#f43f5e', '#10b981', '#f59e0b', '#0ea5e9', '#8b5cf6', '#14b8a6', '#f97316', '#94a3b8'];

const FLOW_COLORS = {
    hub: COLORS.hub,
    income: COLORS.income,
    savings: COLORS.saved,
    investment: COLORS.invested,
    left_over: COLORS.leftOver,
    reserves: COLORS.reserves,
};

const currency = new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK', maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat('cs-CZ', { notation: 'compact', maximumFractionDigits: 1 });

const percent = new Intl.NumberFormat('cs-CZ', { maximumFractionDigits: 1 });

const formatAmount = (haler) => currency.format(haler / 100);
const formatCompact = (haler) => compact.format(haler / 100);

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
}[character]));

const tooltipBase = {
    backgroundColor: '#ffffff',
    borderColor: COLORS.grid,
    textStyle: { color: COLORS.text },
    extraCssText: 'box-shadow: 0 4px 12px rgb(15 23 42 / 0.12); border-radius: 8px;',
};

function donutOptions(data, width) {
    const narrow = width < NARROW_WIDTH;
    const center = narrow ? ['50%', '40%'] : ['36%', '50%'];

    return {
        color: PALETTE,
        title: {
            text: formatAmount(data.total),
            subtext: data.totalLabel,
            left: center[0],
            top: narrow ? '33%' : '43%',
            textAlign: 'center',
            textStyle: { fontSize: 18, fontWeight: 600, color: '#0f172a' },
            subtextStyle: { fontSize: 12, color: COLORS.muted },
            itemGap: 4,
        },
        tooltip: {
            ...tooltipBase,
            trigger: 'item',
            formatter: (params) => `${params.marker} ${escapeHtml(params.name)}<br><b>${formatAmount(params.value)}</b> · ${percent.format(params.percent)} %`,
        },
        legend: narrow
            ? { type: 'scroll', bottom: 0, icon: 'circle', textStyle: { color: COLORS.text } }
            : { type: 'scroll', orient: 'vertical', right: 8, top: 'middle', icon: 'circle', itemGap: 12, textStyle: { color: COLORS.text } },
        series: [{
            type: 'pie',
            radius: ['55%', '80%'],
            center,
            padAngle: 2,
            minAngle: 3,
            avoidLabelOverlap: true,
            itemStyle: { borderRadius: 8, borderColor: '#ffffff', borderWidth: 2 },
            label: { show: false },
            labelLine: { show: false },
            emphasis: { scale: true, scaleSize: 8, itemStyle: { shadowBlur: 16, shadowColor: 'rgb(15 23 42 / 0.2)' } },
            animationType: 'scale',
            animationEasing: 'cubicOut',
            data: data.items.map((item) => ({ name: item.name, value: item.value, url: item.url, cursor: item.url ? 'pointer' : 'default' })),
        }],
    };
}

function trendOptions(data) {
    const styles = {
        income: { type: 'bar', color: COLORS.income },
        expenses: { type: 'bar', color: COLORS.expense },
        saved: { type: 'line', color: COLORS.saved },
        invested: { type: 'line', color: COLORS.invested },
    };

    return {
        tooltip: {
            ...tooltipBase,
            trigger: 'axis',
            axisPointer: { type: 'shadow', shadowStyle: { color: 'rgb(99 102 241 / 0.06)' } },
            valueFormatter: (value) => formatAmount(value),
        },
        legend: { top: 0, icon: 'roundRect', itemWidth: 12, itemHeight: 12, textStyle: { color: COLORS.text } },
        grid: { left: 56, right: 12, top: 44, bottom: 28 },
        xAxis: {
            type: 'category',
            data: data.labels,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: COLORS.grid } },
            axisLabel: { color: COLORS.muted },
        },
        yAxis: {
            type: 'value',
            axisLabel: { color: COLORS.muted, formatter: (value) => formatCompact(value) },
            splitLine: { lineStyle: { color: COLORS.grid, type: 'dashed' } },
        },
        series: data.series.map((series) => {
            const style = styles[series.key];
            const common = { name: series.name, data: series.values, itemStyle: { color: style.color } };

            return style.type === 'bar'
                ? { ...common, type: 'bar', barMaxWidth: 16, barGap: '20%', itemStyle: { color: style.color, borderRadius: [4, 4, 0, 0] } }
                : { ...common, type: 'line', smooth: true, symbol: 'circle', symbolSize: 7, lineStyle: { width: 3, color: style.color } };
        }),
    };
}

function flowOptions(data, width) {
    const narrow = width < NARROW_WIDTH;
    let expenseIndex = 0;
    const nodes = data.nodes.map((node) => {
        const color = node.group === 'expense'
            ? PALETTE[expenseIndex++ % PALETTE.length]
            : FLOW_COLORS[node.group] ?? COLORS.leftOver;

        const isTarget = ['expense', 'left_over'].includes(node.group) || ['saved', 'invested'].includes(node.name);

        return {
            name: node.name,
            display: node.label,
            url: node.url,
            itemStyle: { color, borderColor: color },
            label: { show: ! narrow || isTarget },
        };
    });
    const displayName = Object.fromEntries(nodes.map((node) => [node.name, node.display]));

    return {
        tooltip: {
            ...tooltipBase,
            trigger: 'item',
            formatter: (params) => params.dataType === 'edge'
                ? `${escapeHtml(displayName[params.data.source])} → ${escapeHtml(displayName[params.data.target])}<br><b>${formatAmount(params.value)}</b>`
                : `${escapeHtml(params.data.display)}<br><b>${formatAmount(params.value)}</b>`,
        },
        series: [{
            type: 'sankey',
            left: 8,
            right: narrow ? 120 : 230,
            top: 12,
            bottom: 12,
            nodeWidth: 14,
            nodeGap: narrow ? 8 : 12,
            nodeAlign: 'justify',
            draggable: false,
            emphasis: { focus: 'adjacency' },
            lineStyle: { color: 'gradient', opacity: 0.3, curveness: 0.5 },
            label: {
                color: COLORS.text,
                fontSize: narrow ? 11 : 12,
                formatter: (params) => narrow
                    ? params.data.display
                    : `{name|${params.data.display}}  {value|${formatAmount(params.value)}}`,
                rich: { name: { color: COLORS.text }, value: { color: COLORS.muted } },
            },
            data: nodes,
            links: data.links,
        }],
    };
}

const BUILDERS = { donut: donutOptions, trend: trendOptions, flow: flowOptions };

function registerChart(Alpine) {
    Alpine.data('chart', (type, data) => ({
        instance: null,
        observer: null,
        narrow: false,

        init() {
            this.instance = init(this.$el, null, { renderer: 'canvas' });
            this.instance.setOption(BUILDERS[type](data, this.$el.clientWidth));
            this.instance.on('click', (params) => {
                if (params.data?.url) {
                    window.location.href = params.data.url;
                }
            });
            this.narrow = this.$el.clientWidth < NARROW_WIDTH;
            this.observer = new ResizeObserver(() => {
                const narrow = this.$el.clientWidth < NARROW_WIDTH;

                if (narrow !== this.narrow) {
                    this.narrow = narrow;
                    this.instance?.setOption(BUILDERS[type](data, this.$el.clientWidth), true);
                }

                this.instance?.resize();
            });
            this.observer.observe(this.$el);
        },

        destroy() {
            this.observer?.disconnect();
            this.instance?.dispose();
        },
    }));
}

if (window.Alpine) {
    registerChart(window.Alpine);
} else {
    document.addEventListener('alpine:init', () => registerChart(window.Alpine));
}
