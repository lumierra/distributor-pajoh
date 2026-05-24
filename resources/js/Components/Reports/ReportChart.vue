<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    type: { type: String, default: 'line' }, // line | bar | donut | area
    categories: { type: Array, default: () => [] },
    series: { type: Array, required: true },
    height: { type: Number, default: 280 },
    colors: { type: Array, default: () => ['#A03232', '#E08020', '#1F3A5F'] },
    formatY: { type: Function, default: null },
});

const options = computed(() => {
    const base = {
        chart: {
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
        },
        colors: props.colors,
        title: { text: props.title, style: { fontSize: '13px', fontWeight: 600 } },
        grid: { borderColor: '#eee' },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        legend: { fontSize: '11px' },
    };

    if (props.type === 'donut') {
        return {
            ...base,
            labels: props.categories,
            plotOptions: {
                pie: { donut: { size: '60%' } },
            },
        };
    }

    return {
        ...base,
        xaxis: {
            categories: props.categories,
            labels: { style: { fontSize: '10px' } },
        },
        yaxis: {
            labels: {
                style: { fontSize: '10px' },
                formatter: props.formatY ?? undefined,
            },
        },
    };
});
</script>

<template>
    <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-3">
        <apexchart :type="type" :height="height" :series="series" :options="options" />
    </div>
</template>
