<template>
  <div ref="chartdiv" class="waterfall-chart"></div>
</template>

<script>
  import * as am4core from '@amcharts/amcharts4/core';
  import * as am4charts from '@amcharts/amcharts4/charts';
  import am4themes_animated from '@amcharts/amcharts4/themes/animated';
  am4core.useTheme(am4themes_animated);
  import { applyAmChartsLocalization } from '@/shared/lib/i18n/amcharts';
  import {
    applyAmChartsColorTheme,
    COLOR_MODE_EVENT,
  } from '@/shared/lib/ui/amchartsColorTheme';
  import { buildWaterfallChartData } from '@/shared/lib/charts/waterfall';

  // Below this column width (px) category labels are rotated instead of kept horizontal
  const MIN_HORIZONTAL_LABEL_WIDTH = 90;
  const ROTATED_LABEL_MAX_WIDTH = 140;

  export default {
    name: 'WaterfallChart',
    props: {
      rawData: {
        type: Array,
        default: () => [],
      },
      categoryAxisVisible: {
        type: Boolean,
        default: false,
      },
      // Label for a trailing synthetic summary bar; omit to not add one
      resultLabel: {
        type: String,
        default: null,
      },
      baseCurrency: {
        type: Object,
        required: true,
      },
      locale: {
        type: String,
        required: true,
      },
      language: {
        type: String,
        required: true,
      },
      noDataMessage: {
        type: String,
        default: '',
      },
      // When true, columns emit a `column-click` event with their underlying data on click
      clickable: {
        type: Boolean,
        default: false,
      },
    },
    emits: ['column-click'],
    computed: {
      chartData() {
        return buildWaterfallChartData(this.rawData, {
          resultLabel: this.resultLabel,
        });
      },
    },
    watch: {
      chartData() {
        if (!this.chart) {
          return;
        }

        // Label layout depends on the column count, so refit before the data is redrawn
        this.fitCategoryLabels();
        this.chart.data = this.chartData;
        this.chart.validateData();
        this.toggleNoDataMessage();
      },
      noDataMessage() {
        this.toggleNoDataMessage();
      },
    },
    mounted() {
      this.createChart();
      this._colorModeHandler = () => {
        if (this.chart) this.chart.dispose();
        this.createChart();
        this.toggleNoDataMessage();
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
        applyAmChartsColorTheme(am4core);

        let chart = am4core.create(this.$refs.chartdiv, am4charts.XYChart);
        applyAmChartsLocalization(chart, this.locale, this.language);
        chart.hiddenState.properties.opacity = 0;

        // Set up number formatting
        chart.numberFormatter.intlLocales = this.locale;
        chart.numberFormatter.numberFormat = {
          style: 'currency',
          currency: this.baseCurrency.iso_code,
          minimumFractionDigits: 0,
        };

        chart.data = this.chartData;

        var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
        categoryAxis.dataFields.category = 'category';
        categoryAxis.renderer.minGridDistance = 40;

        if (!this.categoryAxisVisible) {
          categoryAxis.hide();
        } else {
          const labels = categoryAxis.renderer.labels.template;
          labels.truncate = true;
          labels.tooltipText = '{category}';
          labels.interactionsEnabled = true;
          labels.focusable = true;
          labels.hoverOnFocus = true;

          categoryAxis.events.on('sizechanged', () => this.fitCategoryLabels());
        }

        chart.yAxes.push(new am4charts.ValueAxis());

        var columnSeries = chart.series.push(new am4charts.ColumnSeries());
        columnSeries.dataFields.categoryX = 'category';
        columnSeries.dataFields.valueY = 'barValue';
        columnSeries.dataFields.openValueY = 'open';
        columnSeries.fillOpacity = 0.8;
        columnSeries.sequencedInterpolation = true;
        columnSeries.interpolationDuration = 1500;
        columnSeries.tooltipText = `[bold]{categoryX}[/]: {value}`;

        var columnTemplate = columnSeries.columns.template;
        columnTemplate.strokeOpacity = 0;
        columnTemplate.propertyFields.fill = 'color';

        if (this.clickable) {
          columnTemplate.cursorOverStyle = am4core.MouseCursorStyle.pointer;
          columnTemplate.events.on('hit', (ev) => {
            const dataContext = ev.target.dataItem.dataContext;
            if (dataContext.isResult) {
              return;
            }
            this.$emit('column-click', dataContext);
          });
        }

        var stepSeries = chart.series.push(new am4charts.StepLineSeries());
        stepSeries.dataFields.categoryX = 'category';
        stepSeries.dataFields.valueY = 'stepValue';
        stepSeries.noRisers = true;
        stepSeries.stroke = new am4core.InterfaceColorSet().getFor(
          'alternativeBackground',
        );
        stepSeries.strokeDasharray = '3,3';
        stepSeries.interpolationDuration = 2000;
        stepSeries.sequencedInterpolation = true;

        // Because column width is 80%, we modify start/end locations so that step would start with column and end with next column
        stepSeries.startLocation = 0.1;
        stepSeries.endLocation = 1.1;

        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = 'none';

        // Optional message for missing data
        const noDataMessagecontainer = chart.chartContainer.createChild(
          am4core.Container,
        );
        noDataMessagecontainer.id = 'noDataMessagecontainer';
        noDataMessagecontainer.align = 'center';
        noDataMessagecontainer.isMeasured = false;
        noDataMessagecontainer.x = am4core.percent(50);
        noDataMessagecontainer.horizontalCenter = 'middle';
        noDataMessagecontainer.y = am4core.percent(50);
        noDataMessagecontainer.verticalCenter = 'middle';
        noDataMessagecontainer.layout = 'vertical';

        const messageLabel = noDataMessagecontainer.createChild(am4core.Label);
        messageLabel.text = this.noDataMessage;
        messageLabel.textAlign = 'middle';
        messageLabel.maxWidth = 300;
        messageLabel.wrap = true;

        this.chart = chart;
        this.noDataMessagecontainer = noDataMessagecontainer;

        this.toggleNoDataMessage();
      },

      /**
       * Fit category labels to the width of one column: horizontal and truncated to the
       * column when there is room, rotated (with a fixed truncation length) when there is
       * not. Needs to run on resize and whenever the number of columns changes.
       */
      fitCategoryLabels() {
        if (!this.categoryAxisVisible || !this.chart) return;

        const axis = this.chart.xAxes.getIndex(0);
        // Not laid out yet (e.g. the chart sits in a hidden tab)
        if (!axis || !axis.pixelWidth) return;

        const labels = axis.renderer.labels.template;
        const cellWidth = axis.pixelWidth / Math.max(1, this.chartData.length);
        const rotate = cellWidth < MIN_HORIZONTAL_LABEL_WIDTH;

        labels.rotation = rotate ? -45 : 0;
        labels.horizontalCenter = rotate ? 'right' : 'middle';
        labels.verticalCenter = rotate ? 'middle' : 'top';
        labels.maxWidth = rotate ? ROTATED_LABEL_MAX_WIDTH : cellWidth - 10;
        axis.invalidateLayout();
      },

      toggleNoDataMessage() {
        if (!this.noDataMessagecontainer) {
          return;
        }

        if (!this.rawData || this.rawData.length === 0) {
          this.noDataMessagecontainer.show();
        } else {
          this.noDataMessagecontainer.hide();
        }
      },
    },
  };
</script>

<style scoped>
  .waterfall-chart {
    width: 100%;
    height: 350px;
  }
</style>
