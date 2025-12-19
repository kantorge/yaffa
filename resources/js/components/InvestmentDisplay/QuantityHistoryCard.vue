<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Quantity history') }}
      </div>
      <div>
        <span class="badge text-bg-warning me-2" v-if="!hasData">{{
          __('No data available')
        }}</span>
      </div>
    </div>
    <div class="card-body">
      <div v-if="hasData">
        <div ref="chartQuantity" style="width: 100%; height: 300px"></div>
      </div>
      <div v-else>
        <div class="text-center text-muted">{{ __('No data available') }}</div>
      </div>
    </div>
  </div>
</template>

<script>
  import * as am4core from '@amcharts/amcharts4/core';
  import * as am4charts from '@amcharts/amcharts4/charts';
  import am4themes_animated from '@amcharts/amcharts4/themes/animated';

  export default {
    name: 'QuantityHistoryCard',
    props: {
      quantities: { type: Array, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.locale : navigator.language,
      },
    },
    computed: {
      hasData() {
        return this.quantities && this.quantities.length > 0;
      },
    },
    mounted() {
      if (!this.hasData) return;

      am4core.useTheme(am4themes_animated);
      // Chart setup
      let chart = am4core.create(this.$refs.chartQuantity, am4charts.XYChart);
      chart.data = this.quantities;
      let categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
      categoryAxis.dataFields = { category: 'date' };
      let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

      // Step line for history
      let seriesHistory = chart.series.push(new am4charts.StepLineSeries());
      seriesHistory.dataFields.valueY = 'quantity';
      seriesHistory.dataFields.dateX = 'date';
      seriesHistory.strokeWidth = 3;
      seriesHistory.startLocation = 1;

      // Step line for schedule
      let seriesSchedule = chart.series.push(new am4charts.StepLineSeries());
      seriesSchedule.dataFields.valueY = 'schedule';
      seriesSchedule.dataFields.dateX = 'date';
      seriesSchedule.strokeWidth = 3;
      seriesSchedule.strokeDasharray = '3,3';
      seriesSchedule.startLocation = 1;

      let scrollbarX = new am4charts.XYChartScrollbar();
      scrollbarX.series.push(seriesHistory);
      scrollbarX.series.push(seriesSchedule);
      chart.scrollbarX = scrollbarX;

      this.chart = chart;
    },
    beforeUnmount() {
      if (this.chart) {
        this.chart.dispose();
      }
    },
    methods: {},
  };
</script>
