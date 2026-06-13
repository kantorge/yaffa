<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Quantity history') }}
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
  import { applyAmChartsLocalization } from '@/shared/lib/i18n/amcharts';
  import { applyAmChartsColorTheme, COLOR_MODE_EVENT } from '@/shared/lib/ui/amchartsColorTheme';

  export default {
    name: 'QuantityHistoryCard',
    props: {
      quantities: { type: Array, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.userSettings.locale : navigator.language,
      },
    },
    computed: {
      hasData() {
        return this.quantities && this.quantities.length > 0;
      },
    },
    mounted() {
      if (!this.hasData) return;

      this.createChart();
      this._colorModeHandler = () => {
        if (!this.hasData) return;
        if (this.chart) this.chart.dispose();
        this.createChart();
      };
      document.addEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
    },
    beforeUnmount() {
      document.removeEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
      if (this.chart) {
        this.chart.dispose();
      }
    },
    methods: {
      createChart() {
        am4core.useTheme(am4themes_animated);
        applyAmChartsColorTheme(am4core);

        let chart = am4core.create(this.$refs.chartQuantity, am4charts.XYChart);
        applyAmChartsLocalization(
          chart,
          this.locale,
          window.YAFFA.userSettings.language,
        );
        chart.data = this.quantities;
        chart.dateFormatter.inputDateFormat = 'yyyy-MM-dd';
        let categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
        categoryAxis.dataFields = { category: 'date' };
        chart.yAxes.push(new am4charts.ValueAxis());

        let seriesHistory = chart.series.push(new am4charts.StepLineSeries());
        seriesHistory.dataFields.valueY = 'quantity';
        seriesHistory.dataFields.dateX = 'date';
        seriesHistory.strokeWidth = 3;
        seriesHistory.startLocation = 1;

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
    },
  };
</script>
