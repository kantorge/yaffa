<template>
  <div class="card">
    <div class="card-body">
      <div v-if="hasData">
        <div ref="chartRate" style="width: 100%; height: 500px"></div>
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
  import { __ } from '../../helpers';

  export default {
    name: 'CurrencyRateChart',
    props: {
      currencyRates: {
        type: Array,
        required: true,
      },
      toCurrency: {
        type: Object,
        required: true,
      },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.locale : navigator.language,
      },
    },
    computed: {
      hasData() {
        return this.currencyRates && this.currencyRates.length > 0;
      },
    },
    watch: {
      currencyRates: {
        handler(newRates) {
          if (newRates && newRates.length > 0) {
            if (this.chart) {
              this.chart.data = newRates;
            } else {
              // Chart wasn't initialized yet (mounted with no data) or was disposed
              this.$nextTick(() => {
                this.initializeChart();
              });
            }
          } else {
            // No data - dispose chart if it exists
            if (this.chart) {
              this.chart.dispose();
              this.chart = null;
            }
          }
        },
        deep: true,
      },
    },
    mounted() {
      if (!this.hasData) return;

      this.initializeChart();
    },
    beforeUnmount() {
      if (this.chart) {
        this.chart.dispose();
      }
    },
    methods: {
      initializeChart() {
        am4core.useTheme(am4themes_animated);

        // Chart setup using ref instead of ID
        const chart = am4core.create(this.$refs.chartRate, am4charts.XYChart);
        chart.data = this.currencyRates;
        chart.dateFormatter.inputDateFormat = 'yyyy-MM-dd';

        const categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
        categoryAxis.dataFields.category = 'date';

        const valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        // Apply custom number formatter using Intl.NumberFormat
        const intlFormatter = new Intl.NumberFormat(this.locale, {
          style: 'currency',
          currency: this.toCurrency.iso_code,
          currencyDisplay: 'narrowSymbol',
          minimumFractionDigits: 0,
          maximumFractionDigits: 4,
        });

        // Create custom formatter function that respects locale formatting
        valueAxis.numberFormatter = new am4core.NumberFormatter();
        valueAxis.numberFormatter.numberFormat = '#,###.####';

        // Override the format method to use Intl formatting
        const originalFormat = valueAxis.numberFormatter.format.bind(
          valueAxis.numberFormatter,
        );
        valueAxis.numberFormatter.format = function (value) {
          return intlFormatter.format(value);
        };

        const series = chart.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = 'rate';
        series.dataFields.dateX = 'date';
        series.strokeWidth = 3;

        const bullet = series.bullets.push(new am4charts.Bullet());
        const square = bullet.createChild(am4core.Rectangle);
        square.width = 5;
        square.height = 5;
        square.horizontalCenter = 'middle';
        square.verticalCenter = 'middle';

        const scrollbarX = new am4charts.XYChartScrollbar();
        scrollbarX.series.push(series);
        chart.scrollbarX = scrollbarX;

        // Add cursor for date selection
        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = 'zoomX';
        chart.cursor.xAxis = categoryAxis;

        this.chart = chart;
      },
      updateChart(rates) {
        if (this.chart && rates && rates.length > 0) {
          this.chart.data = rates;
        }
      },
      __,
    },
  };
</script>
