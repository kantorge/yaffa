<template>
    <div class="card mb-4" v-if="available">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Monthly overview for top-level categories') }}
            </div>
            <div v-show="ready">
                <button class="btn btn-sm btn-info" type="button" @click="previousMonth"
                        :title="__('Previous month')"><span
                        class="fa fa-fw fa-caret-left"></span></button>
                {{ dateLabel }}
                <button class="btn btn-sm btn-info" type="button" @click="nextMonth" :title="__('Next month')"><span
                        class="fa fa-fw fa-caret-right"></span></button>
            </div>
        </div>
        <div class="card-body">
            <p aria-hidden="true" v-if="!ready" class="placeholder-glow">
                <span class="placeholder col-12"></span>
            </p>
            <div id="categoryWaterfallChart" ref="chartdiv" v-show="ready"></div>
        </div>
        <div class="card-footer text-end">
            <div class="btn-group" role="group" aria-label="Transaction type selector for category waterfall chart">
                <input
                        type="radio"
                        class="btn-check"
                        name="waterfallTransactionCategory"
                        id="waterfallTransactionCategory_All"
                        value="all"
                        autocomplete="off"
                        v-model="transactionTypeData"
                        @change="refreshData"
                        :disabled="busy"
                >
                <label class="btn btn-sm btn-outline-primary" for="waterfallTransactionCategory_All">All
                    transactions</label>

                <input
                        type="radio"
                        class="btn-check"
                        name="waterfallTransactionCategory"
                        id="waterfallTransactionCategory_Standard"
                        value="standard"
                        autocomplete="off"
                        v-model="transactionTypeData"
                        @change="refreshData"
                        :disabled="busy"
                >
                <label class="btn btn-sm btn-outline-primary" for="waterfallTransactionCategory_Standard">Only
                    standard</label>

                <input
                        type="radio"
                        class="btn-check"
                        name="waterfallTransactionCategory"
                        id="waterfallTransactionCategory_Investment"
                        value="investment"
                        autocomplete="off"
                        v-model="transactionTypeData"
                        @change="refreshData"
                        :disabled="busy"
                >
                <label class="btn btn-sm btn-outline-primary" for="waterfallTransactionCategory_Investment">Only
                    investment</label>
            </div>
        </div>
    </div>
</template>

<script>
import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);
import * as helpers from '../../helpers';

export default {
    props: {
        categoryAxisVisible: {
            type: Boolean,
            default: false,
        },
        transactionType: {
            type: String,
            default: 'all',
        }
    },
    data() {
        return {
            available: false,
            busy: false,
            baseCurrency: window.YAFFA.baseCurrency,
            locale: window.YAFFA.locale,
            rawData: [],
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            transactionTypeData: this.transactionType,
            ready: false,
        }
    },
    created() {
        // Verify if base currency is set. Without this, the widget cannot be displayed.
        if (!this.baseCurrency) {
            return;
        }

        this.available = true;
        this.refreshData();
    },
    mounted() {
        if (!this.available) {
            return;
        }

        let chart = am4core.create(this.$refs.chartdiv, am4charts.XYChart);
        chart.hiddenState.properties.opacity = 0;

        // Set up number formatting
        chart.numberFormatter.intlLocales = this.locale;
        chart.numberFormatter.numberFormat = {
            style: 'currency',
            currency: this.baseCurrency.iso_code,
            minimumFractionDigits: 0
        };

        chart.data = this.chartData;

        var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
        categoryAxis.dataFields.category = "category";
        categoryAxis.renderer.minGridDistance = 40;

        if (!this.categoryAxisVisible) {
            categoryAxis.hide();
        } else {
            categoryAxis.events.on("sizechanged", function (ev) {
                var axis = ev.target;
                var cellWidth = axis.pixelWidth / (axis.endIndex - axis.startIndex);
                if (cellWidth < axis.renderer.labels.template.maxWidth) {
                    axis.renderer.labels.template.rotation = -45;
                    axis.renderer.labels.template.horizontalCenter = "right";
                    axis.renderer.labels.template.verticalCenter = "middle";
                } else {
                    axis.renderer.labels.template.rotation = 0;
                    axis.renderer.labels.template.horizontalCenter = "middle";
                    axis.renderer.labels.template.verticalCenter = "top";
                }
            });
        }

        var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

        var columnSeries = chart.series.push(new am4charts.ColumnSeries());
        columnSeries.dataFields.categoryX = "category";
        columnSeries.dataFields.valueY = "barValue";
        columnSeries.dataFields.openValueY = "open";
        columnSeries.fillOpacity = 0.8;
        columnSeries.sequencedInterpolation = true;
        columnSeries.interpolationDuration = 1500;
        columnSeries.tooltipText = `[bold]{categoryX}[/]: {value}`;

        var columnTemplate = columnSeries.columns.template;
        columnTemplate.strokeOpacity = 0;
        columnTemplate.propertyFields.fill = "color";

        var stepSeries = chart.series.push(new am4charts.StepLineSeries());
        stepSeries.dataFields.categoryX = "category";
        stepSeries.dataFields.valueY = "stepValue";
        stepSeries.noRisers = true;
        stepSeries.stroke = new am4core.InterfaceColorSet().getFor("alternativeBackground");
        stepSeries.strokeDasharray = "3,3";
        stepSeries.interpolationDuration = 2000;
        stepSeries.sequencedInterpolation = true;

        // Because column width is 80%, we modify start/end locations so that step would start with column and end with next column
        stepSeries.startLocation = 0.1;
        stepSeries.endLocation = 1.1;

        chart.cursor = new am4charts.XYCursor();
        chart.cursor.behavior = "none";

        // Optional message for missing data
        const noDataMessagecontainer = chart.chartContainer.createChild(am4core.Container);
        noDataMessagecontainer.id = "noDataMessagecontainer";
        noDataMessagecontainer.align = 'center';
        noDataMessagecontainer.isMeasured = false;
        noDataMessagecontainer.x = am4core.percent(50);
        noDataMessagecontainer.horizontalCenter = 'middle';
        noDataMessagecontainer.y = am4core.percent(50);
        noDataMessagecontainer.verticalCenter = 'middle';
        noDataMessagecontainer.layout = 'vertical';

        const messageLabel = noDataMessagecontainer.createChild(am4core.Label);
        messageLabel.text = __('There is no data to show on this chart.');
        messageLabel.textAlign = 'middle';
        messageLabel.maxWidth = 300;
        messageLabel.wrap = true;

        this.chart = chart;
        this.noDataMessagecontainer = noDataMessagecontainer;
    },
    methods: {
        previousMonth: function () {
            this.month--;
            if (this.month < 1) {
                this.year--;
                this.month = 12;
            }

            this.refreshData();
        },

        nextMonth: function () {
            this.month++;
            if (this.month > 12) {
                this.year++;
                this.month = 1;
            }

            this.refreshData();
        },

        refreshData() {
            if (this.busy) {
                return;
            }

            this.busy = true;
            this.ready = false;
            let $vm = this;

            let url = '/api/reports/waterfall/' + this.transactionTypeData + '/result/' + this.year + '/' + this.month;
            let options = {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json;charset=UTF-8",
                },
            };

            fetch(url, options)
                .then((response) => response.json())
                .then((data) => {
                    $vm.rawData = data.chartData;

                    if (!$vm.rawData || $vm.rawData.length === 0) {
                        $vm.noDataMessagecontainer.show();
                    } else {
                        $vm.noDataMessagecontainer.hide();
                    }

                    $vm.ready = true;
                })
                .finally(() => $vm.busy = false)
                .catch(function (error) {
                    console.log(error);
                });
        },

        /**
         * Import the translation helper function.
         */
        __: function (string, replace) {
            return helpers.__(string, replace);
        },
    },
    computed: {
        chartData() {
            var openHistory = 0;

            let data = this.rawData
                .sort(function (a, b) {
                    // Assign the values of a and b to x and y respectively
                    let x = a.value;
                    let y = b.value;

                    // If both x and y are negative, convert them to positive by multiplying by -1
                    // The reason for this is that we want negative values to be sorted as absolute values
                    if (x < 0 && y < 0) {
                        x = x * -1;
                        y = y * -1;
                    }

                    // Compare x and y
                    return ((x > y) ? -1 : ((x < y) ? 1 : 0));
                })
                .map(function (category) {
                    // Assign opening to the last known value
                    category.open = openHistory;
                    category.stepValue = openHistory + category.value;
                    category.barValue = openHistory + category.value;

                    // Adjust open with current value
                    openHistory = category.barValue;

                    // Adjust color
                    category.color = (category.value > 0 ? am4core.color('green') : am4core.color('red'));

                    return category;
                });

            // Add end result if there are more than one data points
            if (data.length > 1) {
                // Open history is the last value of the data
                data.push({
                    category: __('Result'),
                    open: 0,
                    stepValue: 0,
                    barValue: openHistory,
                    value: openHistory,
                    color: (openHistory > 0 ? am4core.color('green') : am4core.color('red')),
                });
            }

            return data;
        },

        dateLabel() {
            const date = new Date(this.year, this.month - 1, 1);
            return date.toLocaleDateString(
                window.YAFFA.locale,
                {
                    year: 'numeric',
                    month: 'long',
                }
            );
        },
    },
    beforeDestroy() {
        if (this.chart) {
            this.chart.dispose();
        }
    },
    updated() {
        if (!this.chart) {
            return;
        }

        // Update chart based on props
        this.chart.data = this.chartData;
        this.chart.validateData();
    },
}
</script>

<style scoped>
#categoryWaterfallChart {
    width: 100%;
    height: 350px;
}
</style>
