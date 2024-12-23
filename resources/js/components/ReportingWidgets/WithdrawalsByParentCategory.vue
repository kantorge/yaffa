<template>
    <div class="chartContainer" ref="chartContainer">
    </div>
</template>

<script>
import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
import { toFormattedCurrency } from "../../helpers";

am4core.useTheme(am4themes_animated);

export default {
    name: 'WithdrawalsByParentCategory',
    props: {
        transactions: {
            type: Array,
            required: false,
            default: () => []
        },
        title: {
            type: String,
            default: "Withdrawals by parent category",
        },
    },
    data() {
        return {
            filteredTransactions: [],
            chartData: {},
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
        updateChartData(transactions) {
            const transactionsCopy = JSON.parse(JSON.stringify(transactions));
            const filteredTransactions = transactionsCopy.filter(transaction => transaction.transaction_type_id === 1);

            if (!filteredTransactions.length) {
                this.chartData = [];
                this.filteredTransactions = [];
                return;
            }

            this.filteredTransactions = filteredTransactions;

            const parentCategories = filteredTransactions
                .flatMap(transaction => transaction.transaction_items)
                .reduce((acc, item) => {
                    if (!item.category) {
                        return acc;
                    }

                    const parentCategory = item.category.parent_id ? item.category.parent.name : item.category.name;
                    if (!acc[parentCategory]) {
                        acc[parentCategory] = 0;
                    }
                    acc[parentCategory] += item.amount_in_base;

                    return acc;
                }, {});

            // Transform parentCategories to an array of objects by converting amounts to formatted currency
            this.chartData = Object.entries(parentCategories).map(([category, amount]) => ({
                category,
                amount,
                formatted_amount: toFormattedCurrency(amount, window.YAFFA.locale, window.YAFFA.baseCurrency)
            }));

            if (this.chart) {
                this.chart.data = this.chartData;
            }
        }
    },
    mounted() {
        let chart = am4core.create(this.$refs.chartContainer, am4charts.PieChart);
        chart.data = null;

        let pieSeries = chart.series.push(new am4charts.PieSeries());
        pieSeries.dataFields.value = "amount";
        pieSeries.dataFields.category = "category";

        // Set the tooltip to use currency format
        pieSeries.slices.template.tooltipText = "{category}: {formatted_amount}";

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
    width: 90%;
    height: 300px;
}
</style>
