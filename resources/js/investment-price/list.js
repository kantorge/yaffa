require('datatables.net-bs5');

import * as dataTableHelpers from './../components/dataTableHelper';
import { toFormattedCurrency } from "../helpers";

const dataTableSelector = '#table';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

// Table currency overrides default currency settings, fixing digits to 4
let currency = Object.assign({}, window.investment.currency, {max_digits: 4});

// Parse prices from string to float
window.prices = window.prices.map(function(price) {
    price.priceFloat = parseFloat(price.price);

    // Formatted currency for chart
    price.priceFormatted = toFormattedCurrency(price.priceFloat, window.YAFFA.locale, currency);

    return price;
});

$(dataTableSelector).DataTable({
    data: window.prices,
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "priceFloat",
            title: __("Price"),
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap',
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
        [0, 'desc']
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    stateSave: true,
    processing: true,
    paging: false,
    info: false,
});

dataTableHelpers.initializeDeleteButtonListener(dataTableSelector, 'investment-price.destroy');

let chart = am4core.create("chartdiv", am4charts.XYChart);
chart.data = window.prices;

chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

// Set up number formatting
chart.numberFormatter.intlLocales = window.YAFFA.locale;
chart.numberFormatter.numberFormat = {
    style: 'currency',
    currency: window.investment.currency.iso_code,
    minimumFractionDigits: 0
};

let categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
categoryAxis.dataFields.category = "date";
let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

let series = chart.series.push(new am4charts.LineSeries());
series.dataFields.valueY = "priceFloat";
series.dataFields.dateX = "date";
series.tooltipText = `[bold]{dateX}[/]: {priceFormatted}`;

let scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(series);
chart.scrollbarX = scrollbarX;

chart.cursor = new am4charts.XYCursor();
chart.cursor.behavior = "none";
