require('datatables.net-bs5');

import * as dataTableHelpers from './../components/dataTableHelper';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

const dataTableSelector = '#table';

// Make sure that the currencyRates array contains numeric values

window.table = $(dataTableSelector).DataTable({
    data: window.currencyRates,
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "rate",
            title: __("Rate"),
            render: function(data, type) {
                return dataTableHelpers.toFormattedCurrency(
                    type,
                    data,
                    window.YAFFA.locale,
                    Object.assign({}, window.YAFFA.baseCurrency, {max_digits: 4})
                )
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
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
    info: false,
});

let chart = am4core.create("chartdiv", am4charts.XYChart);
chart.data = window.currencyRates;

chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

let categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
categoryAxis.dataFields.category = "date";
let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

let series = chart.series.push(new am4charts.LineSeries());
series.dataFields.valueY = "rate";
series.dataFields.dateX = "date";

let scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(series);
chart.scrollbarX = scrollbarX;

dataTableHelpers.initializeDeleteButtonListener(dataTableSelector, 'currency-rate.destroy');

// Listener for the search filter
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
