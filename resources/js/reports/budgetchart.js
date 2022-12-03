import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
import am4themes_kelly from "@amcharts/amcharts4/themes/kelly";
require('datatables.net-bs5');
import * as dataTableHelpers from './../components/dataTableHelper'
import 'jstree';
import 'jstree/src/themes/default/style.css'
import { RRule } from 'rrule';
import { toFormattedCurrency } from '../helpers';

const getAverage = (data, attribute) => data.reduce((acc, val) => acc + val[attribute], 0) / data.length;

const computeMovingAverage = (baseData, period) => {
    var maxActualDate = null;
    for (var i = baseData.length; i > 0; i--) {
        if (baseData[i - 1].actual) {
            maxActualDate = baseData[i - 1].date;
            break;
        }
    }

    if (!maxActualDate) {
        maxActualDate = baseData[baseData.length - 1].date;
    }

    return baseData.map(function (currentItem, index) {
        if (currentItem.date > maxActualDate) {
            if (index > 0) {
                currentItem.movingAverage = baseData[index - 1].movingAverage;
            }
            return currentItem;
        }

        var intervalStart = new Date(currentItem.date.getTime());
        intervalStart.setMonth(intervalStart.getMonth() - period);
        var intervalEnd = currentItem.date;

        var previousPeriod = baseData.filter(function (item) {
            return item.date >= intervalStart && item.date <= intervalEnd;
        });

        currentItem.movingAverage = getAverage(previousPeriod, 'actual');

        return currentItem;
    })
}

const elementRefreshButton = document.getElementById('reload');

am4core.useTheme(am4themes_animated);
am4core.useTheme(am4themes_kelly);
window.chart = am4core.create("chartdiv", am4charts.XYChart);

chart.numberFormatter.intlLocales = window.YAFFA.locale;
chart.numberFormatter.numberFormat = {
    style: 'currency',
    currency: window.YAFFA.baseCurrency.iso_code,
    minimumFractionDigits: window.YAFFA.baseCurrency.num_digits,
    maximumFractionDigits: window.YAFFA.baseCurrency.num_digits
};

var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
dateAxis.dataFields.category = "period";

var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

var seriesActual = chart.series.push(new am4charts.ColumnSeries());
seriesActual.dataFields.valueY = "actual";
seriesActual.dataFields.dateX = "date";
seriesActual.name = __("Actual");
seriesActual.tooltipText = "[bold]" + __('Actual') + ":[/] {valueY}";

var seriesBudget = chart.series.push(new am4charts.LineSeries());
seriesBudget.strokeWidth = 3;
seriesBudget.strokeDasharray = "8,4";
seriesBudget.dataFields.valueY = "budget";
seriesBudget.dataFields.dateX = "date";
seriesBudget.name = __("Budget");
seriesBudget.tooltipText = "[bold]" + __('Budget') + ":[/] {valueY}";

var seriesMovingAverage = chart.series.push(new am4charts.LineSeries());
seriesMovingAverage.strokeWidth = 3;
seriesMovingAverage.dataFields.valueY = "movingAverage";
seriesMovingAverage.dataFields.dateX = "date";
seriesMovingAverage.name = __("Moving average");
seriesMovingAverage.tooltipText = "[bold]" + __('Moving average') + ":[/] {valueY}";

var scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(seriesBudget);
scrollbarX.series.push(seriesActual);
scrollbarX.series.push(seriesMovingAverage);
chart.scrollbarX = scrollbarX;

chart.legend = new am4charts.Legend();
chart.cursor = new am4charts.XYCursor();

// Set AmCharts zoom in functionality for button
const btnZoomIn = document.getElementById('btnZoomIn')
if (btnZoomIn) {
    btnZoomIn.addEventListener('click', function () {
        // Zoom to current month +/- 13 months
        var currentDate = new Date();
        dateAxis.zoomToDates(
            new Date(currentDate.setMonth(currentDate.getMonth() - 13)),
            new Date(currentDate.setMonth(currentDate.getMonth() + 26))
        );
    });
}

let reloadData = function () {
    elementRefreshButton.disabled = true;

    $.ajax({
        url: '/api/budgetchart',
        data: {
            categories: ($('#category_tree').jstree() ? $('#category_tree').jstree('get_checked') : []),
            byYears: (byYears ? 1 : 0),
        }
    })
        .done(function (data) {
            // Convert date
            data = data.map(function (item) {
                item.date = new Date(item.period);
                return item;
            });

            // Add moving average (assuming data is ordered)
            if (data.length > 0) {
                data = computeMovingAverage(data, 12 * (byYears ? 5 : 1));
            }
            chart.data = data;
            chart.invalidateData();
        })
        .always(function () {
            elementRefreshButton.disabled = false;
        });

    window.table.ajax.reload();

    // (Re-)Initialize tooltips in table
    $('[data-toggle="tooltip"]').tooltip();
}

// Attach event listener to refresh button
elementRefreshButton.addEventListener('click', reloadData);

var numberRenderer = $.fn.dataTable.render.number('&nbsp;', ',', 0).display;

// Initially we need to prevent dataTables from calling AJAX, as JStree will not be initialized
let initialTableLoad = true;

window.table = $('#table').DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/any',
        type: 'GET',
        dataSrc: function (data) {
            var transactions = data.transactions || [];
            return transactions.map(function (transaction) {
                transaction.schedule_config.start_date = new Date(transaction.schedule_config.start_date);
                if (transaction.schedule_config.next_date) {
                    transaction.schedule_config.next_date = new Date(transaction.schedule_config.next_date);
                }
                if (transaction.schedule_config.end_date) {
                    transaction.schedule_config.end_date = new Date(transaction.schedule_config.end_date);
                }

                // Create rule
                transaction.schedule_config.rule = new RRule({
                    freq: RRule[transaction.schedule_config.frequency],
                    interval: transaction.schedule_config.interval,
                    dtstart: transaction.schedule_config.start_date,
                    until: transaction.schedule_config.end_date,
                });

                transaction.schedule_config.active = !!transaction.schedule_config.rule.after(new Date(), true);

                return transaction;
            });
        },
        data: function() {
            if (initialTableLoad) {
                initialTableLoad = false;
                return {
                    categories: [],
                    category_required: 1,
                };
            }

            return Object.assign({}, {
                categories: ($('#category_tree').jstree() ? $('#category_tree').jstree('get_checked') : []),
                category_required: 1,
            });
        },
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField("schedule_config.start_date", __("Start date"), window.YAFFA.locale),
        {
            data: "schedule_config.rule",
            title: __("Schedule"),
            render: function (data) {
                // Return human readable format
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField("schedule_config.next_date", __("Start date"), window.YAFFA.locale),
        {
            data: "schedule",
            title: __("Schedule"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "budget",
            title: __("Budget"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "schedule_config.active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "transaction_type.type",
            title: __("Type"),
            render: function (data, type) {
                if (type == 'filter') {
                    return  data;
                }
                return (  data === 'standard'
                        ? '<i class="fa fa-money text-primary" title="' + __('Standard') + '"></i>'
                        : '<i class="fa fa-line-chart text-primary" title="' + __('Investment') + '"></i>');
            },
            className: "text-center",
        },
        {
            title: __('Payee'),
            render: function (_data, _type, row) {
                if (row.transaction_type.type === 'standard') {
                    if (row.transaction_type.name === 'withdrawal' && row.config.account_to) {
                        return row.config.account_to.name;
                    }
                    if (row.transaction_type.name === 'deposit' && row.config.account_from) {
                        return row.config.account_from.name;
                    }
                    if (row.transaction_type.name === 'transfer') {
                        if (row.transactionOperator === 'minus') {
                            return __('Transfer to :account', {account: row.account_to_name});
                        } else {
                            return __('Transfer from :account', {account: row.account_from_name});
                        }
                    }
                }
                if (row.transaction_type.type === 'investment') {
                    return row.investment_name;
                }

                return null;
            },
        },
        {
            title: __("Category"),
            render: function (_data, _type, row) {
                //standard transaction
                if (row.transaction_type.type === 'standard') {
                    if (row.categories.length > 1) {
                        return __('Split transaction');
                    }
                    if (row.categories.length === 1) {
                        return row.categories[0];
                    }

                    return '';
                }
                //investment transaction
                if (row.transaction_type.type === 'investment') {
                    if (!row.quantity_operator) {
                        return row.transaction_type.name;
                    }
                    if (!row.transaction_operator) {
                        return row.transaction_type.name + " " + row.quantity;
                    }

                    return row.transaction_type.name + " " + row.quantity + " @ " + numberRenderer(row.price);
                }

                return '';
            },
            orderable: false
        },
        {
            title: __("Amount"),
            render: function (_data, type, row) {
                if (type === 'display') {
                    let prefix = '';
                    if (row.transaction_operator == 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_operator == 'plus') {
                        prefix = '+ ';
                    }
                    return prefix + toFormattedCurrency(row.config.amount_to, window.YAFFA.locale, row.currency);
                }

                return row.config.amount_to;
            },
            className: 'dt-nowrap',
        },
        {
            data: 'comment',
            title: __("Comment"),
            render: function (data, type) {
                return dataTableHelpers.commentIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "tags",
            title: __("Tags"),
            render: function (data, type) {
                return dataTableHelpers.tagIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: 'id',
            title: __("Actions"),
            render: function (data, _type, row) {
                return dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                       dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                       dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                       dataTableHelpers.dataTablesActionButton(data, 'delete') +
                       (row.schedule
                        ? '<a href="' + (row.transaction_type.type === 'standard' ? route('transactions.open.standard', { transaction: data, action: 'enter' }) : route('transactions.open.investment', { transaction: data, action: 'enter' })) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="' + __('Edit and insert instance') + '"></i></a> ' +
                          '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button"><i class="fa fa-fw fa-forward" title="' + __('Skip current schedule') + '"></i></i></button> '
                        : '');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        if (!data.schedule_config.next_date) {
            return;
        }

        if (data.schedule_config.next_date < new Date(new Date().setHours(0, 0, 0, 0))) {
            $(row).addClass('danger');
        } else if (data.schedule_config.next_date < new Date(new Date().setHours(24, 0, 0, 0))) {
            $(row).addClass('warning');
        }
    },
    initComplete: function (_settings, _json) {
        $('[data-toggle="tooltip"]').tooltip();
    },
    order: [
        [0, "asc"]
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeSkipInstanceButton("#table");
dataTableHelpers.initializeDeleteButton("#table");

// Initialize an object which checks if preset filters are populated. This is used to trigger initial dataTable content.
let presetFilters = {
    ready: function () {
        for (let key in presetFilters) {
            if (presetFilters[key] === false) {
                return false;
            }
        }
        return true;
    }
};

// Loop filter categories and populate presetFilters array.
presetCategories.forEach(category => presetFilters[category] = false);

// Disable table refresh, if any filters are preset
if (!presetFilters.ready()) {
    elementRefreshButton.disabled = true;
}

// Attach event listener to category select2 for select and unselect events to update browser url, without reloading page.
let rebuildUrl = function () {
    let params = $('#category_tree').jstree('get_checked').map((category) => 'categories[]=' + category);
    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));

    // Finally, adjust reload button availability
    elementRefreshButton.disabled = ($('#category_tree').jstree('get_checked').length === 0);
}

// Initialize category tree view
$('#category_tree')
.jstree({
    core: {
        data: function (_obj, callback) {
            fetch('/api/assets/categories?withInactive=1')
                .then(response => response.json())
                .then(data => {
                    let categories = data.map(function(category) {
                        var i = presetCategories.findIndex(cat => cat == category.id);
                        presetCategories[i] = false;

                        return {
                            id: category.id,
                            parent: category.parent_id || '#',
                            text: (category.active ? category.name : '<span class="text-muted" title="' + __('Inactive') + '">' + category.name + '</span>'),
                            full_name: category.full_name,
                            icon: (!category.parent ? 'fa fa-folder text-info' : (category.active ? 'fa fa-check text-success' : 'fa fa-remove text-danger')),
                            state: {
                                selected: (i > -1)
                            }
                        }
                    });
                    callback.call(this, categories);
                })
		},
        themes: {
            dots: false
          }
    },
    plugins: [
        "checkbox"
    ],
    checkbox: {
        keep_selected_style: false
    },
})
.on('select_node.jstree', rebuildUrl)
.on('deselect_node.jstree', rebuildUrl)
.on('ready.jstree', function() {
    elementRefreshButton.disabled = ($('#category_tree').jstree('get_checked').length === 0);
    reloadData();
});

// Select all button function
document.getElementById('all').addEventListener('click', function() {
    $('#category_tree').jstree('check_all');
    rebuildUrl()
});

// Clear button function
document.getElementById('clear').addEventListener('click', function() {
    $('#category_tree').jstree('uncheck_all');
    rebuildUrl()
});
