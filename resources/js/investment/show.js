require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from '../components/dataTableHelper';
import { toFormattedCurrency } from '../helpers';
import Datepicker from 'vanillajs-datepicker/Datepicker';

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

window.calculateYears = function (from, to) {
    var diffMs = to - from;
    var diffDate = new Date(diffMs); // miliseconds from epoch
    return Math.abs(diffDate.getUTCFullYear() - 1970);
}

// Table data transformation
window.transactions = window.transactions.map(function(transaction) {
    if (transaction.date) {
        transaction.date = new Date(Date.parse(transaction.date));
        transaction.date.setHours(0, 0, 0, 0);
    }

    return transaction;
});

// Quantity chart data transformation
window.quantities = window.quantities.map(function(quantity) {
    quantity.date = new Date(Date.parse(quantity.date));
    quantity.date.setHours(0, 0, 0, 0);
    return quantity;
});

// Add a dummy value to quantity chart to draw beyond last value. Set date to two months ahead. Values are copied from last value.
window.quantities.push({
    date: new Date(quantities[quantities.length - 1].date.getTime() + 2*30*24*60*60*1000),
    quantity: quantities[quantities.length - 1].quantity,
    schedule: quantities[quantities.length - 1].schedule,
});

// Add a dummy value to quantity chart to draw before first value. Set date to two months behind. Values are set to 0, assuming no historical quantity existed.
window.quantities.unshift({
    date: new Date(quantities[0].date.getTime() - 2*30*24*60*60*1000),
    quantity: 0,
    schedule: 0,
});

window.summary = {
    'Buying' : {
        value : 0,
        isCurrency : true,
    },
    'Selling' : {
        value : 0,
        isCurrency : true,
    },
    'Added': {
        value : 0,
        isCurrency : false,
    },
    'Removed': {
        value : 0,
        isCurrency : false,
    },
    'Dividend' : {
        value : 0,
        isCurrency : true,
    },
    'Commission' : {
        value : 0,
        isCurrency : true,
    },
    'Taxes' : {
        value : 0,
        isCurrency : true,
    },
    'Quantity' : {
        value : 0,
        isCurrency : false,
    },
    'Value' : {
        value : 0,
        isCurrency : true,
    },
    'Result' : {
        value : 0,
        isCurrency : true,
    },
};

const selectorDataTable = "#table";
window.table = $(selectorDataTable).DataTable({
    data: transactions,
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "transaction_type.name",
            title: __("Transaction"),
        },
        {
            data: "quantity",
            title: __("Quantity"),
            render: function(data) {
                if (data !== null) {
                    return data.toLocaleString(window.YAFFA.locale);
                }
                return null;
            }
        },
        {
            data: "price",
            title: __("Price"),
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, investment.currency);
            },
        },
        {
            data: "dividend",
            defaultContent: '',
            title: __("Dividend"),
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, investment.currency);
            },
        },
        {
            data: "commission",
            defaultContent: '',
            title: __("Commission"),
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, investment.currency);
            },
        },
        {
            data: "tax",
            defaultContent: '',
            title: __("Tax"),
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, investment.currency);
            },
        },
        {
            defaultContent: '',
            title: __("Cash flow value"),
            render: function (_data, type, row) {
                if (isNaN(row.amount_multiplier)) {
                    return 0;
                }
                const result = (  row.amount_multiplier === -1
                              ? - row.price * row.quantity
                              : row.dividend + row.price * row.quantity )
                            - row.tax
                            - row.commission;

                return dataTableHelpers.toFormattedCurrency(type, result, window.YAFFA.locale, investment.currency);
            }
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                var actions = '<button class="btn btn-xs btn-outline-dark set-date" data-type="from" data-date="' + row.date + '" title="' + __('Make this the start date') + '"><i class="fa fa-fw fa-caret-left"></i></button> ' +
                              '<button class="btn btn-xs btn-outline-dark set-date" data-type="to" data-date="' + row.date + '" title="' + __('Make this the end date') + '"><i class="fa fa-fw  fa-caret-right"></i></button> ';
                if (!row.schedule) {
                    actions += '<a href="' + route('transaction.open', {transaction: data, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="' + __('Edit') + '"></i></a> ' +
                               '<a href="' + route('transaction.open', {transaction: data, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="' + __('Clone') + '"></i></a> ' +
                               '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-fw fa-trash" title="' + __('Delete') + '"></i></button> ';
                }

                return actions;
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        if (data.schedule) {
            $(row).addClass('text-muted text-italic');
        }
    },
    order: [
        [ 0, 'asc' ]
    ],
    responsive: true,
});

$(selectorDataTable).on("click", ".data-delete", function() {
    if (!confirm(__('Are you sure to want to delete this item?'))) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('transactions.destroy', {transaction: this.dataset.id});
    form.submit();
});

$(selectorDataTable).on("click", ".set-date", function(_event) {
    //TODO: catch invalid combinations
    if (this.dataset.type === 'from') {
        datepickerFrom.setDate(
            new Date(this.dataset.date),
            {
                clear: true
            }
        );
    } else {
        datepickerTo.setDate(
            new Date(this.dataset.date),
            {
                clear: true
            }
        );
    }
    window.table.draw();
    window.calculateSummary.call();
});

// Initialize date filter inputs
var datePickerStandardSettings = {
    weekStart: 1,
    todayBtn: true,
    todayBtnMode: 1,
    todayHighlight: true,
    format: 'yyyy-mm-dd',
    autohide: true,
    buttonClass: 'btn',
};

// Get min and max dates from transactions
window.minDate = new Date(Math.min(...transactions.map(e => e.date)));
window.maxDate = new Date(Math.max(...transactions.map(e => e.date)));

window.datepickerFrom = new Datepicker(document.getElementById('date_from'), datePickerStandardSettings);
window.datepickerTo = new Datepicker(document.getElementById('date_to'), datePickerStandardSettings);

document.getElementById('date_from').addEventListener("changeDate", function(event) {
    datepickerTo.setOptions({
        minDate: event.date,
    });
    window.table.draw();
    window.calculateSummary.call();
});

document.getElementById('date_to').addEventListener("changeDate", function(event) {
    datepickerFrom.setOptions({
        maxDate: event.date,
    });
    window.table.draw();
    window.calculateSummary.call();
});

// Summary calculations
window.calculateSummary = function() {
    const min = datepickerFrom.getDate();
    const max = datepickerTo.getDate();

    if (typeof max === 'undefined' || typeof min === 'undefined') {
        return;
    }

    const filtered = transactions.filter(function(transaction) {
        return (transaction.date.getTime() >= min.getTime() && transaction.date.getTime() <= max.getTime());
    });

    window.summary.Buying.value = filtered
        .filter(trx => trx.transaction_type.name === 'Buy')
        .reduce((sum, trx) => sum + trx.price * trx.quantity, 0);

    window.summary.Added.value = filtered
        .filter(trx => trx.transaction_type.name === 'Add')
        .reduce((sum, trx) => sum + trx.quantity, 0);

    window.summary.Removed.value = filtered
        .filter(trx => trx.transaction_type.name === 'Remove')
        .reduce((sum, trx) => sum + trx.quantity, 0);

    window.summary.Selling.value = filtered
        .filter(trx => trx.transaction_type.name === 'Sell')
        .reduce((sum, trx) => sum + trx.price * trx.quantity, 0);

    window.summary.Dividend.value = filtered
        .reduce((sum, trx) => sum + trx.dividend, 0);

    window.summary.Commission.value = filtered
        .reduce((sum, trx) => sum + trx.commission, 0);

    window.summary.Taxes.value = filtered
        .reduce((sum, trx) => sum + trx.tax, 0);

    window.summary.Quantity.value = filtered
        .reduce((sum, trx) => sum + trx.transaction_type.quantity_multiplier * trx.quantity, 0);

    let lastPrice;
    if (prices.length > 0) {
        lastPrice = prices.slice(-1)[0].price;
    } else if (filtered.filter(trx => !isNaN(trx.price)).length > 0) {
        lastPrice = filtered
        .filter(trx => !isNaN(trx.price))
        .sort(function (a, b) {
            if (a.date.getTime() < b.date.getTime()) {
                return 1;
            }

            if (a.date.getTime() > b.date.getTime()) {
                return -1;
            }

            return 0;
        })[0].price;
    } else {
        lastPrice = 1;
    }

    window.summary.Value.value = window.summary.Quantity.value * lastPrice;

    // Final result
    window.summary.Result.value = window.summary.Selling.value
                                + window.summary.Dividend.value
                                + window.summary.Value.value
                                - window.summary.Buying.value
                                - window.summary.Commission.value
                                - window.summary.Taxes.value;

    // Calculate ROI
    var ROI = (window.summary.Buying.value == 0 ? 0 : window.summary.Result.value / window.summary.Buying.value);
    var years = calculateYears(datepickerTo.getDate(), datepickerFrom.getDate());
    var AROI = (years > 0 ? Math.pow(1 + ROI, 1 / years) - 1 : 0);
    document.getElementById('summaryROI').innerHTML = (ROI * 100).toFixed(2) + '%';
    document.getElementById('summaryAROI').innerHTML = (AROI * 100).toFixed(2) + '%';

    // Assign calculated data to respective fields
    for (var prop in window.summary) {
        if (Object.prototype.hasOwnProperty.call(window.summary, prop)) {
            document.getElementById('summary' + prop).innerHTML = (window.summary[prop].isCurrency
                                                                    ? toFormattedCurrency(window.summary[prop].value, window.YAFFA.locale, investment.currency)
                                                                    : window.summary[prop].value.toLocaleString(window.YAFFA.locale));
        }
    }
};

window.dateReset = function() {
    datepickerFrom.setDate(
        minDate,
        {
            clear: true
        }
    );
    datepickerFrom.setOptions({
        maxDate: maxDate,
    });

    datepickerTo.setDate(
        maxDate,
        {
            clear: true
        }
    );
    datepickerTo.setOptions({
        minDate: minDate,
    });

    window.table.draw();
    window.calculateSummary.call();
};
window.dateReset.call();

$("#clear_dates").on('click', dateReset);

// Datatables filtering
$.fn.dataTable.ext.search.push(
    function (settings, data) {
        var min = datepickerFrom.getDate();
        var max = datepickerTo.getDate();
        var date = new Date(data[0]);

        return (date.getTime() >= min.getTime() && date.getTime() <= max.getTime());
    }
);

// Initialize charts
if (prices.length > 0) {
    let chartPrice = am4core.create("chartPrice", am4charts.XYChart);
    chartPrice.data = prices;

    chartPrice.dateFormatter.inputDateFormat = "yyyy-MM-dd";

    let categoryAxisPrice = chartPrice.xAxes.push(new am4charts.DateAxis());
    categoryAxisPrice.dataFields.category = "date";
    let valueAxisPrice = chartPrice.yAxes.push(new am4charts.ValueAxis());

    let seriesPrice = chartPrice.series.push(new am4charts.LineSeries());
    seriesPrice.dataFields.valueY = "price";
    seriesPrice.dataFields.dateX = "date";
    seriesPrice.strokeWidth = 3;

    let bullet = seriesPrice.bullets.push(new am4charts.Bullet());
    let square = bullet.createChild(am4core.Rectangle);
    square.width = 5;
    square.height = 5;
    square.horizontalCenter = "middle";
    square.verticalCenter = "middle";

    let scrollbarXprice = new am4charts.XYChartScrollbar();
    scrollbarXprice.series.push(seriesPrice);
    chartPrice.scrollbarX = scrollbarXprice;
} else {
    document.getElementById('chartPrice').remove();
    document.getElementById('priceChartNoData').classList.remove('hidden');
}

if (quantities.length > 0) {
    window.chartQuantity = am4core.create("chartQuantity", am4charts.XYChart);
    chartQuantity.data = quantities;

    let categoryAxisQuantity = chartQuantity.xAxes.push(new am4charts.DateAxis());
    categoryAxisQuantity.dataFields.category = "date";
    let valueAxis = chartQuantity.yAxes.push(new am4charts.ValueAxis());

    let seriesHistory = chartQuantity.series.push(new am4charts.StepLineSeries());
    seriesHistory.dataFields.valueY = "quantity";
    seriesHistory.dataFields.dateX = "date";
    seriesHistory.strokeWidth = 3;
    seriesHistory.startLocation = 1;

    let seriesSchedule = chartQuantity.series.push(new am4charts.StepLineSeries());
    seriesSchedule.dataFields.valueY = "schedule";
    seriesSchedule.dataFields.dateX = "date";
    seriesSchedule.strokeWidth = 3;
    seriesSchedule.strokeDasharray = "3,3";
    seriesSchedule.startLocation = 1;

    let scrollbarXquantity = new am4charts.XYChartScrollbar();
    scrollbarXquantity.series.push(seriesHistory);
    scrollbarXquantity.series.push(seriesSchedule);
    chartQuantity.scrollbarX = scrollbarXquantity;
} else {
    document.getElementById('chartQuantity').remove();
    document.getElementById('quantityChartNoData').classList.remove('hidden');
}
