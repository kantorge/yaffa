<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Investment price history') }}
      </div>
      <div>
        <template v-if="!hideActions">
          <a :href="priceListUrl" class="btn btn-sm btn-primary">
            <span
              class="fa fa-search"
              :title="__('List and manage investment prices')"
            ></span>
          </a>
        </template>
      </div>
    </div>
    <div class="card-body">
      <div v-if="hasData">
        <div ref="chartPrice" style="width: 100%; height: 300px"></div>
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
    name: 'PriceHistoryCard',
    props: {
      prices: { type: Array, required: true },
      investment: { type: Object, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.locale : navigator.language,
      },
      hideActions: {
        type: Boolean,
        default: false,
      },
    },
    computed: {
      hasData() {
        return this.prices && this.prices.length > 0;
      },
      priceListUrl() {
        return window.route
          ? window.route('investment-price.list', {
              investment: this.investment.id,
            })
          : '#';
      },
    },
    mounted() {
      if (!this.hasData) return;

      am4core.useTheme(am4themes_animated);
      // Chart setup
      let chart = am4core.create(this.$refs.chartPrice, am4charts.XYChart);
      chart.data = this.prices;
      chart.dateFormatter.inputDateFormat = 'yyyy-MM-dd';
      chart.numberFormatter.intlLocales = this.locale;
      chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: this.investment.currency.iso_code,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 0,
      };

      let categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
      categoryAxis.dataFields = { category: 'date' };

      let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
      let series = chart.series.push(new am4charts.LineSeries());
      series.dataFields.valueY = 'price';
      series.dataFields.dateX = 'date';
      series.strokeWidth = 3;
      series.minBulletDistance = 15;

      let bullet = series.bullets.push(new am4charts.Bullet());
      let square = bullet.createChild(am4core.Rectangle);
      square.width = 5;
      square.height = 5;
      square.horizontalCenter = 'middle';
      square.verticalCenter = 'middle';

      let scrollbarX = new am4charts.XYChartScrollbar();
      scrollbarX.series.push(series);
      chart.scrollbarX = scrollbarX;

      this.chart = chart;
    },
    beforeUnmount() {
      if (this.chart) {
        this.chart.dispose();
      }
    },
    methods: {
      __,
    },
  };
</script>
