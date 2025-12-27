<template>
  <div class="container">
    <div class="loader" v-show="busy"></div>
    <div class="chartContainer" ref="chartContainer" v-show="!busy"></div>
  </div>
</template>

<script>
  import * as am4core from '@amcharts/amcharts4/core';
  import * as am4charts from '@amcharts/amcharts4/charts';
  import am4themes_animated from '@amcharts/amcharts4/themes/animated';
  import { toFormattedCurrency } from '../../helpers';

  am4core.useTheme(am4themes_animated);

  export default {
    name: 'WithdrawalsByParentCategory',
    props: {
      transactions: {
        type: Array,
        required: false,
        default: () => [],
      },
      title: {
        type: String,
        default: 'Withdrawals by category',
      },
      busy: {
        type: Boolean,
        required: true,
      },
    },
    data() {
      return {
        filteredTransactions: [],
        chartData: {},
        selectedParentId: undefined,
      };
    },
    watch: {
      transactions: {
        handler(newTransactions) {
          this.updateChartData(newTransactions);
        },
        immediate: true,
        deep: true,
      },
    },
    methods: {
      /**
       * Update the chart data based on the current set of transactions.
       *
       * @param {Array} transactions
       * @property {Number} transactions.transaction_type_id
       * @returns {void}
       */
      updateChartData(transactions) {
        const filteredTransactions = [];
        transactions.forEach((transaction) => {
          if (transaction.transaction_type_id === 1) {
            filteredTransactions.push(transaction);
          }
        });

        if (!filteredTransactions.length) {
          this.filteredTransactions = [];
          // Add one dummy data point to the chart to display a message when there are no transactions
          this.chartData = [
            {
              amount: 1,
              id: 0,
              parent_id: 0,
              parent_name: 'No data',
              name: 'No data',
              selected: false,
              disabled: true,
              color: am4core.color('#dadada'),
              tooltip: 'No data',
            },
          ];
        } else {
          // Process the actual transactions
          this.filteredTransactions = filteredTransactions;

          const categorySummary = [];

          filteredTransactions
            // Flatten the transaction items to a single array
            .flatMap((transaction) => transaction.transaction_items)
            /**
             * Process each transaction item and group them by parent category,
             * where the parent category is determined based on the selectedParentId.
             *
             * @param {Object} item
             * @property {Object} item.category
             * @property {Number} item.amount_in_base
             */
            .forEach((item) => {
              if (!item.category) {
                return;
              }

              // Create a map of parent categories with their color codes assigned from amCharts
              const colorCodesPerParent = {};
              this.getDistinctParentIds().forEach((id, index) => {
                if (this.chart) {
                  colorCodesPerParent[id] = this.chart.colors.getIndex(index);
                } else {
                  colorCodesPerParent[id] = am4core.color('#000000');
                }
              });

              let parentCategory;
              if (this.selectedParentId) {
                if (item.category.parent_id === this.selectedParentId) {
                  parentCategory = item.category;
                } else {
                  parentCategory = item.category.parent_id
                    ? item.category.parent
                    : item.category;
                }
              } else {
                parentCategory = item.category.parent_id
                  ? item.category.parent
                  : item.category;
              }

              let categoryIndex = categorySummary.findIndex(
                (category) => category.id === parentCategory.id,
              );

              if (categoryIndex === -1) {
                categorySummary.push({
                  amount: 0,
                  id: parentCategory.id,
                  parent_id: parentCategory.parent_id,
                  parent_name: parentCategory.parent_id
                    ? parentCategory.parent.name
                    : parentCategory.name,
                  name:
                    parentCategory.parent_id === this.selectedParentId
                      ? parentCategory.full_name
                      : parentCategory.name,
                  selected: parentCategory.parent_id === this.selectedParentId,
                  color:
                    colorCodesPerParent[parentCategory.id] ||
                    colorCodesPerParent[parentCategory.parent_id],
                });

                categoryIndex = categorySummary.length - 1;
              }

              categorySummary[categoryIndex].amount += item.amount_in_base;
            });
          // Sort the categories array by the true parent category name
          categorySummary.sort((a, b) =>
            a.parent_name.localeCompare(b.parent_name),
          );

          // Transform parentCategories to an array of objects by converting amounts to formatted currency
          this.chartData = categorySummary.map((category) => {
            return {
              ...category,
              tooltip: `${category.name}: ${toFormattedCurrency(category.amount, window.YAFFA.locale, window.YAFFA.baseCurrency)}`,
            };
          });
        }

        if (this.chart) {
          this.chart.data = this.chartData;
        }
      },
      getDistinctParentIds() {
        return this.filteredTransactions
          .flatMap((transaction) => transaction.transaction_items)
          .filter((item) => item.category)
          .map((item) => item.category.parent_id)
          .filter((value, index, self) => self.indexOf(value) === index);
      },
    },
    mounted() {
      let chart = am4core.create(this.$refs.chartContainer, am4charts.PieChart);
      chart.data = null;

      let pieSeries = chart.series.push(new am4charts.PieSeries());
      pieSeries.dataFields.value = 'amount';
      pieSeries.dataFields.category = 'name';
      pieSeries.slices.template.propertyFields.fill = 'color';
      pieSeries.slices.template.propertyFields.isActive = 'selected';
      pieSeries.labels.template.propertyFields.disabled = 'disabled';
      pieSeries.ticks.template.propertyFields.disabled = 'disabled';

      // Set the tooltip to use currency format
      pieSeries.slices.template.tooltipText = '{tooltip}';

      // Set up listener for the slice click event
      pieSeries.slices.template.events.on('hit', (event) => {
        const data = event.target.dataItem.dataContext;
        this.selectedParentId = data.id;
        this.updateChartData(this.transactions);
      });

      // Set the chart title
      let title = chart.titles.create();
      title.text = this.title;
      title.fontSize = 20;
      title.marginBottom = 20;

      this.chart = chart;
    },
    beforeDestroy() {
      if (this.chart) {
        this.chart.dispose();
      }
    },
  };
</script>

<style scoped>
  @import './PieChartLoader.css';
  .container {
    display: flex;
    justify-content: center;
  }

  .chartContainer {
    width: 100%;
    height: 400px;
  }
</style>
