require('datatables.net-bs5');

import * as dataTableHelpers from './../components/dataTableHelper';

const dataTableSelector = '#table';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

$(dataTableSelector).DataTable({
    data: prices,
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "price",
            title: __("Price"),
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  dataTableHelpers.genericDataTablesActionButton(data, 'edit', 'investment-price.edit') +
                        dataTableHelpers.genericDataTablesActionButton(data, 'delete');

            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc']
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeDeleteButtonListener(dataTableSelector, 'investment-price.destroy');

var chart = am4core.create("chartdiv", am4charts.XYChart);
chart.data = prices;

chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
categoryAxis.dataFields.category = "date";
var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

var series = chart.series.push(new am4charts.LineSeries());
series.dataFields.valueY = "price";
series.dataFields.dateX = "date";

var scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(series);
chart.scrollbarX = scrollbarX;
