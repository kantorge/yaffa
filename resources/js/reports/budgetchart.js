import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
import am4themes_kelly from "@amcharts/amcharts4/themes/kelly";

require('datatables.net-bs5');
import * as dataTableHelpers from './../components/dataTableHelper'
import 'jstree';
import 'jstree/src/themes/default/style.css'
import * as helpers from "../helpers";

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
const treeSelector = '#category_tree';

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
            categories: ($(treeSelector).jstree() ? $(treeSelector).jstree('get_checked') : []),
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

// Initially we need to prevent dataTables from calling AJAX, as JStree will not be initialized
let initialTableLoad = true;
const tableSelector = '#table';


window.table = $(tableSelector).DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/any',
        type: 'GET',
        dataSrc: function (data) {
            return data.transactions
                .map(helpers.processTransaction)
                .map(helpers.processScheduledTransaction);
        },
        data: function () {
            if (initialTableLoad) {
                initialTableLoad = false;
                return {
                    categories: [],
                    category_required: 1,
                };
            }

            return Object.assign({}, {
                categories: ($(treeSelector).jstree() ? $(treeSelector).jstree('get_checked') : []),
                category_required: 1,
            });
        },
    },
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField("transaction_schedule.start_date", __("Start date"), window.YAFFA.locale),
        {
            data: "transaction_schedule.rule",
            title: __("Schedule"),
            render: function (data) {
                // Return human readable format
                // TODO: translation
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField("transaction_schedule.next_date", __("Start date"), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('schedule', __('Schedule')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('budget', __('Budget')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('transaction_schedule.active', __('Active')),
        {
            data: "transaction_type.type",
            title: __("Type"),
            render: function (data, type) {
                if (type == 'filter') {
                    return data;
                }
                return (data === 'standard'
                    ? '<i class="fa fa-money text-primary" title="' + __('Standard') + '"></i>'
                    : '<i class="fa fa-line-chart text-primary" title="' + __('Investment') + '"></i>');
            },
            className: "text-center",
        },
        dataTableHelpers.transactionColumnDefinition.payee,
        dataTableHelpers.transactionColumnDefinition.category,
        dataTableHelpers.transactionColumnDefinition.amount,
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
                return dataTableHelpers.dataTablesActionButton(data, 'edit') +
                    dataTableHelpers.dataTablesActionButton(data, 'clone') +
                    dataTableHelpers.dataTablesActionButton(data, 'replace') +
                    dataTableHelpers.dataTablesActionButton(data, 'delete') +
                    (row.schedule
                        ? '<a href="' + window.route('transaction.open', {
                            transaction: data,
                            action: 'enter'
                        }) + '" class="btn btn-xs btn-success" title="' + __('Edit and insert instance') + '"><i class="fa fa-fw fa-pencil"></i></a> ' +
                        '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button" title="' + __('Skip current schedule') + '"><i class="fa fa-fw fa-forward"></i></i></button> '
                        : '');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        if (!data.transaction_schedule.next_date) {
            return;
        }

        if (data.transaction_schedule.next_date < new Date(new Date().setHours(0, 0, 0, 0))) {
            $(row).addClass('danger');
        } else if (data.transaction_schedule.next_date < new Date(new Date().setHours(24, 0, 0, 0))) {
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

dataTableHelpers.initializeSkipInstanceButton(tableSelector);
dataTableHelpers.initializeDeleteButton(tableSelector);

// Initialize an object which checks if preset filters are populated.
// This is used to trigger initial chart and table content.
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
window.presetCategories.forEach(category => presetFilters[category] = false);

// Disable refresh, if any filters are preset, as an initial load will be performed
if (!presetFilters.ready()) {
    elementRefreshButton.disabled = true;
}

// Update URL params based on JS Tree selection
let rebuildUrl = function () {
    let params = $(treeSelector).jstree('get_checked').map((category) => 'categories[]=' + category);
    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));

    // Finally, adjust reload button availability
    elementRefreshButton.disabled = ($(treeSelector).jstree('get_checked').length === 0);
}

// Initialize category tree view
$(treeSelector)
    .jstree({
        core: {
            data: function (_obj, callback) {
                fetch('/api/assets/categories?withInactive=1')
                    .then(response => response.json())
                    .then(data => {
                        let categories = data.map(function (category) {
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
    .on('ready.jstree', function () {
        elementRefreshButton.disabled = ($(treeSelector).jstree('get_checked').length === 0);
        reloadData();
    });

// Select all button function
document.getElementById('all').addEventListener('click', function() {
    $(treeSelector).jstree('check_all');
    rebuildUrl()
});

// Clear button function
document.getElementById('clear').addEventListener('click', function() {
    $(treeSelector).jstree('uncheck_all');
    rebuildUrl()
});
