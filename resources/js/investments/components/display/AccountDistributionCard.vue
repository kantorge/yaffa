<template>
  <div v-if="slices.length > 1" class="card mb-3">
    <div class="card-header">
      <div class="card-title">
        {{ __('Distribution by account') }}
      </div>
    </div>
    <div class="card-body">
      <div ref="chartDistribution" class="chart-container"></div>
    </div>
  </div>
</template>

<script>
  import * as am4core from '@amcharts/amcharts4/core';
  import * as am4charts from '@amcharts/amcharts4/charts';
  import am4themes_animated from '@amcharts/amcharts4/themes/animated';
  import {
    __,
    toFormattedCurrency,
    toFormattedNumber,
  } from '@/shared/lib/i18n';
  import {
    applyAmChartsColorTheme,
    COLOR_MODE_EVENT,
  } from '@/shared/lib/ui/amchartsColorTheme';

  // The investment has a single price, so the share of quantity and of value is identical:
  // one chart covers both, the tooltip shows both figures.
  export default {
    name: 'AccountDistributionCard',
    props: {
      accounts: { type: Array, default: () => [] },
      investment: { type: Object, required: true },
      selectedAccountId: { type: Number, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    computed: {
      slices() {
        return this.accounts
          .filter((account) => account.quantity > 0)
          .map((account) => ({
            id: account.id,
            name: account.name,
            quantity: account.quantity,
            quantityText: toFormattedNumber(account.quantity, this.locale, {
              minimumFractionDigits: 0,
              maximumFractionDigits: 4,
            }),
            valueText:
              this.investment.latest_price == null
                ? __('Not set')
                : toFormattedCurrency(
                    account.quantity * this.investment.latest_price,
                    this.locale,
                    this.investment.currency,
                  ),
          }));
      },
    },
    watch: {
      slices() {
        this.rebuild();
      },
      selectedAccountId() {
        this.highlightSelected();
      },
    },
    mounted() {
      this.rebuild();
      this._colorModeHandler = () => this.rebuild();
      document.addEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
    },
    beforeUnmount() {
      document.removeEventListener(COLOR_MODE_EVENT, this._colorModeHandler);
      if (this.chart) this.chart.dispose();
    },
    methods: {
      __,
      rebuild() {
        if (this.chart) {
          this.chart.dispose();
          this.chart = null;
        }
        // The container only exists while there is something to compare
        this.$nextTick(() => {
          if (this.slices.length > 1 && this.$refs.chartDistribution) {
            this.createChart();
          }
        });
      },
      createChart() {
        am4core.useTheme(am4themes_animated);
        applyAmChartsColorTheme(am4core);

        const chart = am4core.create(
          this.$refs.chartDistribution,
          am4charts.PieChart,
        );
        chart.data = this.slices;

        const series = chart.series.push(new am4charts.PieSeries());
        series.dataFields.value = 'quantity';
        series.dataFields.category = 'name';
        series.labels.template.disabled = true;
        series.ticks.template.disabled = true;
        series.slices.template.tooltipText =
          '{category}: {quantityText} ({value.percent.formatNumber("#.0")}%)\n{valueText}';
        series.slices.template.states.getKey('active').properties.shiftRadius =
          0.08;

        chart.legend = new am4charts.Legend();
        chart.legend.position = 'bottom';
        // Full-width legend so long account names are not truncated
        chart.legend.labels.template.truncate = false;
        chart.legend.maxHeight = 90;
        chart.legend.scrollable = true;
        chart.legend.valueLabels.template.disabled = true;

        this.chart = chart;
        // Slices are only created once the data has been validated
        series.events.once('datavalidated', () => this.highlightSelected());
      },
      highlightSelected() {
        if (!this.chart) return;
        this.chart.series.getIndex(0).slices.each((slice) => {
          slice.isActive =
            this.selectedAccountId != null &&
            slice.dataItem.dataContext.id === this.selectedAccountId;
        });
      },
    },
  };
</script>
