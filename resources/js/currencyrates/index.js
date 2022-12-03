require('datatables.net-bs5');

import * as dataTableHelpers from './../components/dataTableHelper';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

const dataTableSelector = '#table';

$(dataTableSelector).DataTable({
    data: currencyRates,
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "rate",
            title: __("Rate"),
            render: function(data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, window.YAFFA.baseCurrency)
            }
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, _row, _meta) {
                return dataTableHelpers.genericDataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [ 0, 'desc' ]
    ]
});

var chart = am4core.create("chartdiv", am4charts.XYChart);
chart.data = currencyRates;

chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
categoryAxis.dataFields.category = "date";
var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

var series = chart.series.push(new am4charts.LineSeries());
series.dataFields.valueY = "rate";
series.dataFields.dateX = "date";

var scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(series);
chart.scrollbarX = scrollbarX;

dataTableHelpers.initializeDeleteButtonListener(dataTableSelector, 'currency-rate.destroy');
