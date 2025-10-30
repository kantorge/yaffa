<template>
    <div class="card">
        <div class="card-body">
            <div id="currencyRateChart" style="width: 100%; height: 500px"></div>
        </div>
    </div>
</template>

<script>
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';

am4core.useTheme(am4themes_animated);

export default {
    name: 'CurrencyRateChart',
    props: {
        currencyRates: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            chart: null,
        };
    },
    watch: {
        currencyRates: {
            handler(newRates) {
                if (this.chart) {
                    this.chart.data = newRates;
                }
            },
            deep: true,
        },
    },
    mounted() {
        this.initializeChart();
    },
    beforeUnmount() {
        if (this.chart) {
            this.chart.dispose();
        }
    },
    methods: {
        initializeChart() {
            this.chart = am4core.create('currencyRateChart', am4charts.XYChart);
            this.chart.data = this.currencyRates;

            this.chart.dateFormatter.inputDateFormat = 'yyyy-MM-dd';

            const categoryAxis = this.chart.xAxes.push(new am4charts.DateAxis());
            categoryAxis.dataFields.category = 'date';
            const valueAxis = this.chart.yAxes.push(new am4charts.ValueAxis());

            const series = this.chart.series.push(new am4charts.LineSeries());
            series.dataFields.valueY = 'rate';
            series.dataFields.dateX = 'date';

            const scrollbarX = new am4charts.XYChartScrollbar();
            scrollbarX.series.push(series);
            this.chart.scrollbarX = scrollbarX;
        },
        updateChart(rates) {
            if (this.chart) {
                this.chart.data = rates;
            }
        },
    },
};
</script>
