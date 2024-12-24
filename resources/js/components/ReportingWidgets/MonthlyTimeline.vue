<template>
    <div>
        <ul class="list-group list-group-flush" v-if="busy">
            <li
                    aria-hidden="true"
                    class="list-group-item placeholder-glow"
                    v-for="i in 5"
                    v-bind:key="i"
            >
                <span class="placeholder placeholder-lg col-12"></span>
            </li>
        </ul>
        <div class="chartContainer" ref="chartContainer" v-show="!busy"></div>
    </div>
</template>

<script>
import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";

am4core.useTheme(am4themes_animated);

export default {
    name: 'MonthlyTimeline',
    props: {
        transactions: {
            type: Array,
            required: false,
            default: () => []
        },
        title: {
            type: String,
            default: "Monthly spending and income",
        },
        busy: {
            type: Boolean,
            required: true,
        }
    },
    data() {
        return {
            filteredTransactions: [],
            chartData: {},
            locale: window.YAFFA.locale,
            baseCurrency: window.YAFFA.baseCurrency,
        }
    },
    watch: {
        transactions: {
            handler(newTransactions) {
                this.updateChartData(newTransactions);
            },
            immediate: true,
            deep: true
        }
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
            transactions.forEach(transaction => {
                // Take only deposits and withdrawals
                if (transaction.transaction_type_id === 2 || transaction.transaction_type_id === 1) {
                    filteredTransactions.push(transaction);
                }
            });

            if (!filteredTransactions.length) {
                this.filteredTransactions = [];
                this.chartData = [];
            } else {
                // Process the actual transactions
                this.filteredTransactions = filteredTransactions;

                // Create the chart data by aggregating the transactions into months
                // The withdrawals and deposits are separated into two separate series, with the withdrawals being negative
                const chartData = [];
                const months = {};
                /**
                 * @var {Object} transaction
                 * @property {Date} transaction.date
                 * @property {Number} transaction.cashflow_value
                 * @property {Number} transaction.currencyRateToBase
                 */
                filteredTransactions.forEach(transaction => {
                    const date = new Date(transaction.date);
                    const month = date.getFullYear() * 100 + date.getMonth() + 1;
                    if (!months[month]) {
                        months[month] = {
                            month: month,
                            // Truncate the date to the first day of the month
                            date: new Date(date.getFullYear(), date.getMonth(), 1),
                            deposits: 0,
                            withdrawals: 0,
                            cashFlow: 0,
                        };
                    }

                    if (transaction.transaction_type_id === 2) {
                        months[month].deposits += transaction.cashflow_value * transaction.currencyRateToBase;
                    } else if (transaction.transaction_type_id === 1) {
                        months[month].withdrawals += transaction.cashflow_value * transaction.currencyRateToBase;
                    }
                });

                Object.values(months).forEach(month => {
                    chartData.push({
                        month: month.month,
                        date: month.date,
                        deposits: month.deposits,
                        withdrawals: month.withdrawals,
                        cashFlow: month.deposits + month.withdrawals, // Deposits are positive, withdrawals are negative
                    });
                });

                this.chartData = chartData;
            }

            if (this.chart) {
                this.chart.data = this.chartData;
            }
        },
        getDistinctParentIds() {
            return this.filteredTransactions
                .flatMap(transaction => transaction.transaction_items)
                .filter(item => item.category)
                .map(item => item.category.parent_id)
                .filter((value, index, self) => self.indexOf(value) === index);
        }
    },
    mounted() {
        let chart = am4core.create(this.$refs.chartContainer, am4charts.XYChart);

        // Data is empty initially
        chart.data = null;

        // Set up number formatting
        chart.numberFormatter.intlLocales = this.locale;
        chart.numberFormatter.numberFormat = {
            style: 'currency',
            currency: this.baseCurrency.iso_code,
            minimumFractionDigits: 0
        };

        // Create axes
        let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
        dateAxis.renderer.minGridDistance = 50;

        chart.yAxes.push(new am4charts.ValueAxis());

        // Create series
        let seriesDeposit = chart.series.push(new am4charts.ColumnSeries());
        seriesDeposit.dataFields.valueY = "deposits";
        seriesDeposit.dataFields.dateX = "date";
        seriesDeposit.name = "Deposits";
        seriesDeposit.tooltipText = "{name}: [bold]{valueY}[/]";
        seriesDeposit.columns.template.fill = am4core.color("green");
        seriesDeposit.strokeOpacity = 0;
        seriesDeposit.clustered = false;

        let seriesWithdrawal = chart.series.push(new am4charts.ColumnSeries());
        seriesWithdrawal.dataFields.valueY = "withdrawals";
        seriesWithdrawal.dataFields.dateX = "date";
        seriesWithdrawal.name = "Withdrawals";
        seriesWithdrawal.tooltipText = "{name}: [bold]{valueY}[/]";
        seriesWithdrawal.columns.template.fill = am4core.color("red");
        seriesWithdrawal.strokeOpacity = 0;
        seriesWithdrawal.clustered = false;

        let seriesCashFlow = chart.series.push(new am4charts.LineSeries());
        seriesCashFlow.dataFields.valueY = "cashFlow";
        seriesCashFlow.dataFields.dateX = "date";
        seriesCashFlow.name = "Monthly cash flow";
        seriesCashFlow.tooltipText = "{name}: [bold]{valueY}[/]";

        let bullet = seriesCashFlow.bullets.push(new am4charts.CircleBullet());
        bullet.circle.fill = am4core.color("#fff");
        bullet.circle.strokeWidth = 2;

        chart.cursor = new am4charts.XYCursor();

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
    }
}
</script>

<style scoped>
.chartContainer {
    width: 100%;
    height: 500px;
}
</style>
