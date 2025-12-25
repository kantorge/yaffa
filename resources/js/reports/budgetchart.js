import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
import am4themes_kelly from "@amcharts/amcharts4/themes/kelly";
import * as dataTableHelpers from './../components/dataTableHelper'
import * as helpers from "../helpers";
import 'jstree';
import 'jstree/src/themes/default/style.css'

import 'datatables.net-bs5';
import 'select2';

const accountSelector = '#accountList';
const treeSelector = '#categoryTree';

const getAverage = (data, attribute) => data.reduce((acc, val) => acc + val[attribute], 0) / data.length;

const computeMovingAverage = (baseData, interval) => {
    // Don't do any calculations if there is no data
    if (baseData.length === 0) {
        return baseData;
    }

    // Set the period based on the interval
    let period = 12;
    if (interval === 'quarter') {
        period = 4 * 3;
    } else if (interval === 'year') {
        period = 5 * 12;
    }

    // Find the last element with actual data, default to the last element
    let maxActualDate = null;
    for (let i = baseData.length; i > 0; i--) {
        if (baseData[i - 1].actual) {
            maxActualDate = baseData[i - 1].date;
            break;
        }
    }
    if (!maxActualDate) {
        maxActualDate = baseData[baseData.length - 1].date;
    }

    // Loop through all elements, and calculate the moving average for each
    return baseData.map(function (currentItem, index) {
        // For future dates, we set the last moving average
        if (currentItem.date > maxActualDate) {
            if (index > 0) {
                currentItem.movingAverage = baseData[index - 1].movingAverage;
            }
            return currentItem;
        }

        // Calculate the interval start and end dates, using the number of months determined by the period
        const intervalStart = new Date(currentItem.date.getTime());
        intervalStart.setMonth(intervalStart.getMonth() - period);
        const intervalEnd = currentItem.date;

        const previousPeriod = baseData.filter(function (item) {
            return item.date >= intervalStart && item.date <= intervalEnd;
        });

        currentItem.movingAverage = getAverage(previousPeriod, 'actual');

        return currentItem;
    })
};

const elementRefreshButton = document.getElementById('reload');

am4core.useTheme(am4themes_animated);
am4core.useTheme(am4themes_kelly);
window.chart = am4core.create("chartdiv", am4charts.XYChart);

chart.numberFormatter.intlLocales = window.YAFFA.locale;
chart.numberFormatter.numberFormat = {
    style: 'currency',
    currency: window.YAFFA.baseCurrency.iso_code,
    minimumFractionDigits: 0
};

const dateAxis = chart.xAxes.push(new am4charts.DateAxis());
dateAxis.dataFields.category = "period";
dateAxis.baseInterval = {
    timeUnit: "month",
    count: 1
}
dateAxis.dateFormats.setKey("month", "yyyy MMM");

// This is not used later, so it is not assigned to a variable
chart.yAxes.push(new am4charts.ValueAxis());

const seriesActual = chart.series.push(new am4charts.ColumnSeries());
seriesActual.dataFields.valueY = "actual";
seriesActual.dataFields.dateX = "date";
seriesActual.name = __("Actual");
seriesActual.tooltipText = "[bold]" + __('Actual') + ":[/] {valueY}";

const seriesBudget = chart.series.push(new am4charts.LineSeries());
seriesBudget.strokeWidth = 3;
seriesBudget.strokeDasharray = "8,4";
seriesBudget.dataFields.valueY = "budget";
seriesBudget.dataFields.dateX = "date";
seriesBudget.name = __("Budget");
seriesBudget.tooltipText = "[bold]" + __('Budget') + ":[/] {valueY}";

const seriesMovingAverage = chart.series.push(new am4charts.LineSeries());
seriesMovingAverage.strokeWidth = 3;
seriesMovingAverage.dataFields.valueY = "movingAverage";
seriesMovingAverage.dataFields.dateX = "date";
seriesMovingAverage.name = __("Moving average");
seriesMovingAverage.tooltipText = "[bold]" + __('Moving average') + ":[/] {valueY}";

const scrollbarX = new am4charts.XYChartScrollbar();
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
        const currentDate = new Date();
        dateAxis.zoomToDates(
            new Date(currentDate.setMonth(currentDate.getMonth() - 13)),
            new Date(currentDate.setMonth(currentDate.getMonth() + 26))
        );
    });
}

let rawData = [];

let reloadData = function () {
    elementRefreshButton.disabled = true;
    const selectedCategories = ($(treeSelector).jstree() ? $(treeSelector).jstree('get_checked', true) : []);

    $.ajax({
        url: window.route('api.reports.budgetchart'),
        data: {
            categories: selectedCategories.map(category => category.id),
            accountSelection: $('input[name=table_filter_account_scope]:checked').val(),
            accountEntity: $(accountSelector).val(),
        }
    })
        .done(function (data) {
            // Convert date strings to Date objects
            data = data.map(function (item) {
                item.date = new Date(item.period);
                return item;
            });

            // Store the raw data for later use
            rawData = data.slice();

            // Determine the highest level of aggregation based on selected categories
            let aggregation = 'month';
            /**
             * @var {Object} category - Category object in JSTree format
             * @property {string} original - Original category object
             * @property {string} original.default_aggregation - Default aggregation of the category
             **/
            selectedCategories.some(function (category) {
                if (category.original.default_aggregation === 'quarter') {
                    aggregation = 'quarter';
                    return false;
                } else if (category.original.default_aggregation === 'year') {
                    aggregation = 'year';
                    return true;
                }
            });

            // Set the radio button based on the aggregation
            document.querySelector('input[name="chart_time_interval"][value="' + aggregation + '"]').checked = true;

            // Update the chart
            updateChart(rawData);
        })
        .always(function () {
            elementRefreshButton.disabled = false;
        });

    // We need to reload the table content, too
    window.table.ajax.reload(function () {
        // (Re-)initialize tooltips in table, once data is reloaded
        helpers.initializeBootstrapTooltips();
    });
}

function updateChart(rawData) {
    if (rawData.length === 0) {
        chart.data = [];
        chart.invalidateData();
        return;
    }

    const aggregation = document.querySelector('input[name="chart_time_interval"]:checked')?.value || 'month';
    let data;

    // Aggregate the data by quarter or year, based on user selection
    if (aggregation === 'quarter') {
        data = rawData.reduce((acc, item) => {
            const quarter = Math.floor(item.date.getMonth() / 3);
            const existingItem = acc.find(
                acc_item => acc_item.date.getFullYear() === item.date.getFullYear()
                    && Math.floor(acc_item.date.getMonth() / 3) === quarter
            );

            // Initialize keys if they do not exist
            if (typeof existingItem === 'undefined') {
                acc.push({
                    date: new Date(item.date.getFullYear(), quarter * 3),
                    period: item.date.getFullYear() + ' Q' + (quarter + 1),
                    actual: item.actual,
                    budget: item.budget,
                });
                return acc;
            }
            if (!existingItem.actual) existingItem.actual = 0;
            if (!existingItem.budget) existingItem.budget = 0;

            // At this point all months should be present, so we can safely sum the values
            existingItem.actual += item.actual;
            existingItem.budget += item.budget;

            return acc;
        }, []);

        // Add missing quarters to the data
        const minDate = data[0].date;
        const maxDate = data[data.length - 1].date;
        let currentDate = new Date(minDate);
        while (currentDate < maxDate) {
            if (!data.find(
                item => item.date.getFullYear() === currentDate.getFullYear()
                    && Math.floor(item.date.getMonth() / 3) === Math.floor(currentDate.getMonth() / 3))
            ) {
                data.push({
                    date: new Date(currentDate),
                    period: currentDate.getFullYear() + ' Q' + (Math.floor(currentDate.getMonth() / 3) + 1),
                    actual: 0,
                    budget: 0,
                });
            }
            currentDate.setMonth(currentDate.getMonth() + 3);
        }

        // Change the date axis base interval based on the aggregation
        dateAxis.baseInterval = {
            timeUnit: 'month',
            count: 3
        }
    } else if (aggregation === 'year') {
        data = rawData.reduce((acc, item) => {
            const existingItem = acc.find(
                acc_item => acc_item.date.getFullYear() === item.date.getFullYear()
            );

            // Initialize keys if they do not exist
            if (typeof existingItem === 'undefined') {
                acc.push({
                    date: new Date(item.date.getFullYear(), 0),
                    period: item.date.getFullYear().toString(),
                    actual: item.actual,
                    budget: item.budget,
                });
                return acc;
            }
            if (!existingItem.actual) existingItem.actual = 0;
            if (!existingItem.budget) existingItem.budget = 0;

            // At this point all months should be present, so we can safely sum the values
            existingItem.actual += item.actual;
            existingItem.budget += item.budget;

            return acc;
        }, []);

        // Add missing years to the data
        const minDate = data[0].date;
        const maxDate = data[data.length - 1].date;
        let currentDate = new Date(minDate);
        while (currentDate < maxDate) {
            if (!data.find(
                item => item.date.getFullYear() === currentDate.getFullYear())
            ) {
                data.push({
                    date: new Date(currentDate),
                    period: currentDate.getFullYear().toString(),
                    actual: 0,
                    budget: 0,
                });
            }
            currentDate.setFullYear(currentDate.getFullYear() + 1);
        }

        // Change the date axis base interval based on the aggregation
        dateAxis.baseInterval = {
            timeUnit: 'year',
            count: 1
        }
    } else {
        data = rawData.slice();

        // Add the missing months to the data
        const minDate = data[0].date;
        const maxDate = data[data.length - 1].date;
        let currentDate = new Date(minDate);
        while (currentDate < maxDate) {
            if (!data.find(
                item => item.date.getFullYear() === currentDate.getFullYear()
                    && item.date.getMonth() === currentDate.getMonth()
                    && item.date.getDate() === currentDate.getDate())
            ) {
                data.push({
                    date: new Date(currentDate),
                    period: currentDate.toISOString().slice(0, 7),
                    actual: 0,
                    budget: 0,
                });
            }
            currentDate.setMonth(currentDate.getMonth() + 1);
        }

        // Change the date axis base interval based on the aggregation
        dateAxis.baseInterval = {
            timeUnit: 'month',
            count: 1
        }
    }

    // Sort the data by date
    data.sort((a, b) => a.date - b.date);

    // Add moving average (knowing that data is ordered)
    data = computeMovingAverage(data, aggregation);

    // Update the chart settings
    chart.data = data;
    chart.invalidateData();
}

// Attach event listener to refresh button
// TODO: if the account selection is enabled, but no account is selected, the button should be disabled
elementRefreshButton.addEventListener('click', reloadData);

// Attach event listener to time interval radio buttons to redraw the chart using the already loaded data
document.querySelectorAll('input[name="chart_time_interval"]').forEach(function (element) {
    element.addEventListener('change', function () {
        updateChart(rawData);
    });
});

// Initially we need to prevent dataTables from calling AJAX, as JStree will not be initialized
let initialTableLoad = true;
const tableSelector = '#table';

window.table = $(tableSelector).DataTable({
    ajax: {
        url: window.route('api.transactions.getScheduledItems', {type: 'any'}),
        type: 'GET',
        dataSrc: function (data) {
            if (!data.transactions) {
                return [];
            }
            return data.transactions
                .map(helpers.processTransaction)
                .map(helpers.processScheduledTransaction);
        },
        data: function () {
            // As the first load, we are intentionally not loading any data. It will be loaded once the tree is ready.
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
                accountSelection: $('input[name=table_filter_account_scope]:checked').val(),
                accountEntity: $(accountSelector).val(),
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
                // TODO: translate the RRule string
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField("transaction_schedule.next_date", __("Next date"), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('schedule', __('Schedule')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('budget', __('Budget')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('transaction_schedule.active', __('Active')),
        {
            data: "transaction_type.type",
            title: __("Type"),
            render: function (data, type) {
                if (type === 'filter') {
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
        dataTableHelpers.transactionColumnDefinition.extra,
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
    order: [
        [0, "asc"]
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeSkipInstanceButton(tableSelector);
dataTableHelpers.initializeAjaxDeleteButton(tableSelector);

// Initialize an object which checks if preset filters are populated.
// This is used to trigger initial chart and table content.
let presetFilters = {
    categories: {},
    account: undefined,
    ready: function () {
        for (let key in presetFilters.categories) {
            if (presetFilters.categories[key] === false) {
                return false;
            }
        }

        return presetFilters.account !== false;

    }
};

/** @var {URLSearchParams} searchParams URL search parameters */
const searchParams = new URLSearchParams(window.location.search);
/** @var {Array} presetCategories Array of initially selected category IDs */
const presetCategories = searchParams.getAll('categories[]').map(category => parseInt(category));
presetCategories.forEach(category => presetFilters.categories[category] = false);

/** @var {number} presetAccount ID of initially selected account */
const presetAccount = searchParams.has('accountEntity') ? parseInt(searchParams.get('accountEntity')) : undefined;
if (typeof presetAccount !== 'undefined') {
    presetFilters.account = false;
}

// Update URL params based on JS Tree selection
let rebuildUrl = function () {
    let url = new URL(window.location.origin + window.location.pathname);

    // Accounts
    if ($(accountSelector).val()) {
        url.searchParams.append('accountEntity', $(accountSelector).val());
    }

    // Categories
    $(treeSelector).jstree('get_checked').forEach((category) => url.searchParams.append('categories[]', category));

    // Update the URL
    window.history.pushState('', '', url.toString());

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
                        /**
                         * category represents an instance of a category model
                         * @var {Object} category
                         * @property {number} id
                         * @property {number} parent_id - ID of the parent category, or null if it is a root category
                         */
                        let categories = data.map(function (category) {
                            // Mark this preset item as ready, if it is preset
                            if (presetFilters.categories[category.id] !== undefined) {
                                presetFilters.categories[category.id] = true;
                            }

                            return {
                                id: category.id,
                                parent: category.parent_id || '#',
                                default_aggregation: category.default_aggregation,
                                text: (category.active ? category.name : '<span class="text-muted" title="' + __('Inactive') + '">' + category.name + '</span>'),
                                full_name: category.full_name,
                                icon: (!category.parent ? 'fa fa-folder text-info' : (category.active ? 'fa fa-check text-success' : 'fa fa-remove text-danger')),
                                state: {
                                    selected: presetCategories.includes(category.id)
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
        if (($(treeSelector).jstree('get_checked').length > 0)) {
            if (presetFilters.ready()) {
                reloadData();
            }
        } else {
            elementRefreshButton.disabled = true;
        }
    });

// Account filter
$(accountSelector).select2({
    theme: "bootstrap-5",
    ajax: {
        url: '/api/assets/account',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            return {
                results: data.map(function (account) {
                    return {
                        id: account.id,
                        text: account.name,
                    }
                }),
            };
        },
        cache: true
    },
    placeholder: __("Select account"),
    allowClear: true
})
    .on('select2:select', rebuildUrl)
    .on('select2:unselect', rebuildUrl);

// Default account
if (typeof presetAccount !== 'undefined') {
    $.ajax({
        url: '/api/assets/account/' + presetAccount,
        data: {
            _token: window.csrfToken,
        }
    })
        .done(data => {
            // Create the option and append to Select2
            $(accountSelector).append(new Option(data.name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.name,
                        }
                    }
                });

            presetFilters.account = true;

            // Initial data for the preset account, if other preset filters are ready
            if (presetFilters.ready() && $(treeSelector).jstree('get_checked').length > 0) {
                reloadData();
            }
        });
} else {
    // Initial data for the preset account, if other preset filters are ready
    if (presetFilters.ready() && $(treeSelector).jstree('get_checked').length > 0) {
        reloadData();
    }
}

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

// Account type switch
$('input[name=table_filter_account_scope]').on("change", function() {
    // Only selected items are needed, so we need to enable the account selector
    $(accountSelector).prop('disabled', this.value !== 'selected');

    // If the account selector is disabled, we need to clear the account filter
    if (this.value !== 'selected') {
        $(accountSelector).val(null).trigger('change');
        rebuildUrl();
    }
});

// Set initial state of account selector
$(accountSelector).prop('disabled', $('input[name=table_filter_account_scope]:checked').val() !== 'selected');
