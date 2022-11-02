<template>
    <div class="box">
        <div class="box-header">
            <h3 class="box-title">
                {{ __('Monthly overview for top-level categories') }}
            </h3>
            <div class="pull-right" v-show="ready">
                <button class="btn btn-xs btn-info" type="button" @click="previousMonth" :title="__('Previous month')"><span class="fa fa-fw fa-caret-left"></span></button>
                {{ dateLabel }}
                <button class="btn btn-xs btn-info" type="button" @click="nextMonth" :title="__('Next month')"><span class="fa fa-fw fa-caret-right"></span></button>
            </div>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <Skeletor
                width="100%"
                v-if="!ready"
            />
            <div id="categoryWaterfallChart" ref="chartdiv" v-show="ready"></div>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->
</template>

<script>
import { Skeletor } from 'vue-skeletor';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";

am4core.useTheme(am4themes_animated);

export default {
    components: { Skeletor },
    props: {
        categoryAxisVisible: {
            type: Boolean,
            default: false,
        }
    },
    data() {
        return {
            baseCurrency: window.YAFFA.baseCurrency,
            locale: window.YAFFA.locale,
            rawData: [],
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
            ready: false,
        }
    },
    created() {
        this.refreshData();
    },
    mounted() {
        let chart = am4core.create(this.$refs.chartdiv, am4charts.XYChart);
        chart.hiddenState.properties.opacity = 0;

        // Set up number formatting
        chart.numberFormatter.intlLocales = this.locale;
        chart.numberFormatter.numberFormat = {
            style: 'currency',
            currency: this.baseCurrency.iso_code,
            minimumFractionDigits: this.baseCurrency.num_digits,
            maximumFractionDigits: this.baseCurrency.num_digits
        };

        chart.data = this.chartData;

        var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
        categoryAxis.dataFields.category = "category";
        categoryAxis.renderer.minGridDistance = 40;

        if (!this.categoryAxisVisible) {
            categoryAxis.hide();
        } else {
            categoryAxis.events.on("sizechanged", function(ev) {
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
        var tooltipText = `[bold]{categoryX}[/]: {value}`;

        var columnSeries = chart.series.push(new am4charts.ColumnSeries());
        columnSeries.dataFields.categoryX = "category";
        columnSeries.dataFields.valueY = "barValue";
        columnSeries.dataFields.openValueY = "open";
        columnSeries.fillOpacity = 0.8;
        columnSeries.sequencedInterpolation = true;
        columnSeries.interpolationDuration = 1500;
        columnSeries.tooltipText = tooltipText;

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
        previousMonth: function() {
            this.month--;
            if (this.month < 1) {
                this.year--;
                this.month = 12;
            }

            this.refreshData();
        },

        nextMonth: function() {
            this.month++;
            if (this.month > 12) {
                this.year++;
                this.month = 1;
            }

            this.refreshData();
        },

        refreshData() {
            this.ready = false;
            let $vm = this;

            axios.get('/api/reports/waterfall/result/' + this.year + '/' + this.month)
                .then(function(response) {
                    $vm.rawData = response.data.chartData;

                    if (!$vm.rawData || $vm.rawData.length === 0) {
                        $vm.noDataMessagecontainer.show();
                    } else {
                        $vm.noDataMessagecontainer.hide();
                    }

                    $vm.ready = true;
                })
                .catch(function(error) {
                    console.log(error);
                })
            }
    },
    computed: {
        chartData() {
            var openHistory = 0;

            var data = this.rawData
                .sort(function(a, b) {
                    var x = a.value;
                    var y = b.value;

                    if (x < 0 && y < 0) {
                        x = x * -1;
                        y = y * -1;
                    }

                    return ((x > y) ? -1 : ((x < y) ? 1 : 0));
                })
                .map(function(category) {

                    // Assign open to last known value
                    category.open = openHistory;
                    category.stepValue = openHistory + category.value;
                    category.barValue = openHistory + category.value;

                    // Adjust open with current value
                    openHistory = category.barValue;

                    // Adjust color
                    category.color = (category.value > 0 ? am4core.color('green') : am4core.color('red'));

                    return category;
                });

            // Add end result
            if (data.length > 1) {
                var last = data[data.length - 1];
                data.push({
                    category: 'Result',
                    open: 0,
                    stepValue: 0,
                    barValue: openHistory + last.value,
                    value: openHistory + last.value,
                    color: (openHistory + last.value > 0 ? am4core.color('green') : am4core.color('red') ),
                });
            }

            return data;
        },

        dateLabel() {
            return this.year + ' ' + this.month;
        },
    },
    beforeDestroy() {
        if (this.chart) {
            this.chart.dispose();
        }
    },
    updated() {
        if (this.chart) {
            // Update chart based on props
            this.chart.data = this.chartData;
            this.chart.validateData();
        }
    },
}
</script>

<style scoped>
    #categoryWaterfallChart {
        width: 100%;
        height: 350px;
    }
</style>
