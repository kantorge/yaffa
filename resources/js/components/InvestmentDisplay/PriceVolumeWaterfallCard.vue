<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Price/Volume Waterfall') }}
      </div>
      <div>
        <span class="label label-danger" v-if="!hasData">{{
          __('No data available')
        }}</span>
      </div>
    </div>
    <div class="card-body">
      <div v-if="hasData">
        <div ref="chartWaterfall" style="width: 100%; height: 400px"></div>
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
    name: 'PriceVolumeWaterfallCard',
    props: {
      waterfallData: { type: Array, required: true },
      investment: { type: Object, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.locale : navigator.language,
      },
    },
    computed: {
      hasData() {
        return this.waterfallData && this.waterfallData.length > 0;
      },
    },
    mounted() {
      console.log('PriceVolumeWaterfallCard mounted');
      console.log('waterfallData:', this.waterfallData);
      console.log('hasData:', this.hasData);
      if (!this.hasData) {
        console.log('No waterfall data, not rendering chart');
        return;
      }

      am4core.useTheme(am4themes_animated);
      
      // Create chart
      let chart = am4core.create(this.$refs.chartWaterfall, am4charts.XYChart);
      
      // Transform data for waterfall - need open and close values for each bar
      let transformedData = [];
      this.waterfallData.forEach((item, index) => {
        // Get starting position (end of previous month, or 0 for first)
        const startPosition = index > 0 ? this.waterfallData[index - 1].runningTotal : 0;
        
        // Track position within this month
        let currentPosition = startPosition;
        
        // Buys bar
        if (item.buys !== 0) {
          transformedData.push({
            category: `${item.period} - Buys`,
            open: currentPosition,
            close: currentPosition + item.buys,
            value: item.buys,
            color: '#28a745',
            type: 'Buys'
          });
          currentPosition += item.buys;
        }
        
        // Price Change bar
        if (item.priceChange !== 0) {
          transformedData.push({
            category: `${item.period} - Price`,
            open: currentPosition,
            close: currentPosition + item.priceChange,
            value: item.priceChange,
            color: item.priceChange > 0 ? '#007bff' : '#ffc107',
            type: 'Price Change'
          });
          currentPosition += item.priceChange;
        }
        
        // Sells bar
        if (item.sells !== 0) {
          transformedData.push({
            category: `${item.period} - Sells`,
            open: currentPosition,
            close: currentPosition + item.sells,
            value: item.sells,
            color: '#dc3545',
            type: 'Sells'
          });
          currentPosition += item.sells;
        }
      });
      
      chart.data = transformedData;
      
      // Create axes
      let categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
      categoryAxis.dataFields.category = 'category';
      categoryAxis.renderer.grid.template.location = 0;
      categoryAxis.renderer.minGridDistance = 30;
      categoryAxis.renderer.labels.template.rotation = -45;
      categoryAxis.renderer.labels.template.horizontalCenter = 'right';
      categoryAxis.renderer.labels.template.verticalCenter = 'middle';
      
      let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
      valueAxis.title.text = `Value (${this.investment.currency?.iso_code || ''})`;
      valueAxis.renderer.minGridDistance = 50;
      
      // Create waterfall series
      let series = chart.series.push(new am4charts.ColumnSeries());
      series.dataFields.valueY = 'close';
      series.dataFields.openValueY = 'open';
      series.dataFields.categoryX = 'category';
      series.columns.template.strokeOpacity = 0;
      series.columns.template.propertyFields.fill = 'color';
      series.columns.template.tooltipText = '{type}: {value.formatNumber("#,###.00")}';
      
      // Add step line to connect bars
      let stepLineSeries = chart.series.push(new am4charts.StepLineSeries());
      stepLineSeries.dataFields.valueY = 'close';
      stepLineSeries.dataFields.categoryX = 'category';
      stepLineSeries.stroke = am4core.color('#999');
      stepLineSeries.strokeWidth = 2;
      stepLineSeries.noRisers = true;
      stepLineSeries.strokeDasharray = '3,3';
      
      // Add cursor
      chart.cursor = new am4charts.XYCursor();
      
      this.chart = chart;
    },
    beforeUnmount() {
      if (this.chart) {
        this.chart.dispose();
      }
    },
  };
</script>
