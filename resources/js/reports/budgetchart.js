// Chart components
import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';
import am4themes_kelly from '@amcharts/amcharts4/themes/kelly';

// DataTables
import 'datatables.net-bs5';

// Generic helpers
import { applyAmChartsLocalization } from '@/shared/lib/i18n/amcharts';
import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import * as dataTableHelpers from '@/shared/lib/datatable';
import { initializeSelect2 } from '@/shared/lib/select2';
import { initializeBootstrapTooltips, scheduleCadenceText, getArrayParamFromUrl } from '@/shared/lib/helpers';
import * as toastHelpers from '@/shared/lib/toast';
import { applyAmChartsColorTheme, COLOR_MODE_EVENT } from '@/shared/lib/ui/amchartsColorTheme';
import Swal from 'sweetalert2';
import { createApp } from 'vue';
import BudgetForm from '@/reports/components/BudgetForm.vue';
import BudgetQuickView from '@/reports/components/BudgetQuickView.vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';

// Category tree
import 'jstree';
import 'jstree/src/themes/default/style.css'

// Select2 for account selection
initializeSelect2(window.YAFFA.userSettings.language);

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

// FR-7: flatten the per-period budgetBreakdown arrays budgetChart() returns into one row per
// distinct contributing Budget row (a recurring budget appears in every period it lands in;
// the drill-down cares about "which budgets contribute," not "how many times"). When the same
// budget's amount differs across periods (FR-8 inflation), the most recent period's amount wins.
function buildBudgetBreakdownRows(rawData) {
    const byBudgetId = new Map();

    rawData.forEach(function (periodEntry) {
        (periodEntry.budgetBreakdown || []).forEach(function (row) {
            const existing = byBudgetId.get(row.budget_id);

            if (!existing || periodEntry.date > existing.periodDate) {
                byBudgetId.set(row.budget_id, {
                    budget_id: row.budget_id,
                    category_name: row.category_name,
                    account_name: row.account_name,
                    amount: row.amount,
                    currency: row.currency,
                    cadence: scheduleCadenceText(row.transaction_schedule),
                    periodDate: periodEntry.date,
                });
            }
        });
    });

    return Array.from(byBudgetId.values());
}

// Same idea as buildBudgetBreakdownRows(), for the schedule-transaction side of the total
// (ReportApiController::budgetChart()'s scheduleBreakdown).
function buildScheduleBreakdownRows(rawData) {
    const byTransactionId = new Map();

    rawData.forEach(function (periodEntry) {
        (periodEntry.scheduleBreakdown || []).forEach(function (row) {
            const existing = byTransactionId.get(row.transaction_id);

            if (!existing || periodEntry.date > existing.periodDate) {
                byTransactionId.set(row.transaction_id, {
                    transaction_id: row.transaction_id,
                    category_names: (row.category_names || []).join(', '),
                    amount: row.amount,
                    currency: row.currency,
                    cadence: scheduleCadenceText(row.transaction_schedule),
                    periodDate: periodEntry.date,
                });
            }
        });
    });

    return Array.from(byTransactionId.values());
}

const elementRefreshButton = document.getElementById('reload');

let chart, dateAxis, seriesActual, seriesForecast, seriesBudget, seriesMovingAverage, scrollbarX;

function initChart() {
    if (chart) chart.dispose();

    applyAmChartsColorTheme(am4core);
    am4core.useTheme(am4themes_animated);
    am4core.useTheme(am4themes_kelly);

    chart = am4core.create("chartdiv", am4charts.XYChart);
    window.chart = chart;
    applyAmChartsLocalization(chart, window.YAFFA.userSettings.locale, window.YAFFA.userSettings.language);

    chart.numberFormatter.intlLocales = window.YAFFA.userSettings.locale;
    chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: window.YAFFA.userSettings.baseCurrency.iso_code,
        minimumFractionDigits: 0
    };

    dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.dataFields.category = "period";
    dateAxis.baseInterval = {
        timeUnit: "month",
        count: 1
    }
    dateAxis.dateFormats.setKey("month", "yyyy MMM");

    // Highlight the current month, mirroring the cash flow chart's own current-month marker.
    dateAxis.events.on("datavalidated", function (ev) {
        const axis = ev.target;
        const now = new Date();

        axis.axisRanges.clear();
        const range = axis.axisRanges.create();
        range.date = new Date(now.getFullYear(), now.getMonth(), 1);
        range.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        range.axisFill.fill = am4core.color("#396478");
        range.axisFill.fillOpacity = 0.4;
        range.grid.strokeOpacity = 0;
    });

    // This is not used later, so it is not assigned to a variable
    chart.yAxes.push(new am4charts.ValueAxis());

    // Consistent color pairing across the 4 series: "Actual" (fact) and "Moving average" share
    // one color family (blue); "Forecast" and "Budget" - both plan/projection series - share
    // another (purple), so the chart reads as two pairs rather than four unrelated colors.
    const colorActual = am4core.color("#2E86C1");
    const colorMovingAverage = am4core.color("#1B4F72");
    const colorForecastFill = am4core.color("#b39ddb");
    const colorForecastStroke = am4core.color("#7e57c2");

    seriesActual = chart.series.push(new am4charts.ColumnSeries());
    seriesActual.dataFields.valueY = "actual";
    seriesActual.dataFields.dateX = "date";
    seriesActual.name = __("Actual");
    seriesActual.tooltipText = "[bold]" + __('Actual') + ":[/] {valueY}";
    seriesActual.stacked = true;
    // Set on the series itself (not just columns.template) - the tooltip background derives its
    // color from the series' own fill/stroke, not from the column template, so setting only the
    // template leaves the tooltip on the theme's auto-assigned color instead of matching the bars.
    seriesActual.fill = colorActual;
    seriesActual.stroke = colorActual;
    seriesActual.columns.template.fill = colorActual;
    seriesActual.columns.template.stroke = colorActual;
    seriesActual.tooltip.getFillFromObject = false;
    seriesActual.tooltip.background.fill = colorActual;

    // The forecasted value of active scheduled transactions - stacked on top of "Actual" so the
    // two bars read as "so far + what's still expected this period." Lighter purple fill and a
    // dashed border distinguish it as a plan/forecast rather than recorded fact.
    seriesForecast = chart.series.push(new am4charts.ColumnSeries());
    seriesForecast.dataFields.valueY = "forecast";
    seriesForecast.dataFields.dateX = "date";
    seriesForecast.name = __("Forecast");
    seriesForecast.tooltipText = "[bold]" + __('Forecast') + ":[/] {valueY}";
    seriesForecast.stacked = true;
    seriesForecast.fill = colorForecastFill;
    seriesForecast.stroke = colorForecastStroke;
    seriesForecast.columns.template.fill = colorForecastFill;
    seriesForecast.columns.template.stroke = colorForecastStroke;
    seriesForecast.columns.template.strokeWidth = 1;
    seriesForecast.columns.template.strokeDasharray = "3,3";
    seriesForecast.tooltip.getFillFromObject = false;
    seriesForecast.tooltip.background.fill = colorForecastStroke;

    seriesBudget = chart.series.push(new am4charts.LineSeries());
    seriesBudget.strokeWidth = 3;
    seriesBudget.strokeDasharray = "8,4";
    seriesBudget.fill = colorForecastStroke;
    seriesBudget.stroke = colorForecastStroke;
    seriesBudget.dataFields.valueY = "budget";
    seriesBudget.dataFields.dateX = "date";
    seriesBudget.name = __("Budget");
    seriesBudget.tooltipText = "[bold]" + __('Budget') + ":[/] {valueY}";
    seriesBudget.tooltip.getFillFromObject = false;
    seriesBudget.tooltip.background.fill = colorForecastStroke;

    seriesMovingAverage = chart.series.push(new am4charts.LineSeries());
    seriesMovingAverage.strokeWidth = 3;
    seriesMovingAverage.fill = colorMovingAverage;
    seriesMovingAverage.stroke = colorMovingAverage;
    seriesMovingAverage.dataFields.valueY = "movingAverage";
    seriesMovingAverage.dataFields.dateX = "date";
    seriesMovingAverage.name = __("Moving average");
    seriesMovingAverage.tooltipText = "[bold]" + __('Moving average') + ":[/] {valueY}";
    seriesMovingAverage.tooltip.getFillFromObject = false;
    seriesMovingAverage.tooltip.background.fill = colorMovingAverage;

    scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesBudget);
    scrollbarX.series.push(seriesActual);
    scrollbarX.series.push(seriesForecast);
    scrollbarX.series.push(seriesMovingAverage);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();
}

initChart();

// Set AmCharts zoom in functionality for button (set up once; dateAxis is module-level)
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

// Warns the user when the currently displayed chart no longer reflects the active filters
// (category/account/account-scope changed after the last successful load, without reloading).
const staleDataWarning = document.getElementById('stale-data-warning');
initializeBootstrapTooltips();
function markDataStale() {
    if (rawData.length > 0) {
        staleDataWarning.classList.remove('d-none');
    }
}
function markDataFresh() {
    staleDataWarning.classList.add('d-none');
}

let reloadData = function () {
    elementRefreshButton.disabled = true;
    const selectedCategories = ($(treeSelector).jstree() ? $(treeSelector).jstree('get_checked', true) : []);

    $.ajax({
        url: window.route('api.v1.reports.budget-chart'),
        timeout: 30000,
        data: {
            categories: selectedCategories.map(category => category.id),
            accountSelection: $('input[name=table_filter_account_scope]:checked').val(),
            accountEntity: $(accountSelector).val(),
        }
    })
        .fail(function (jqXHR, textStatus) {
            const message = textStatus === 'timeout'
                ? __('Loading the budget chart timed out. Please try again with fewer categories, or try again later.')
                : __('Failed to load the budget chart: :error', { error: jqXHR.statusText || textStatus });

            toastHelpers.showErrorToast(message);
        })
        .done(function (data) {
            markDataFresh();

            const chartData = Array.isArray(data) ? data : (data.chartData || []);

            // Convert date strings to Date objects
            const parsedChartData = chartData.map(function (item) {
                item.date = new Date(item.period);
                return item;
            });

            // Store the raw data for later use
            rawData = parsedChartData.slice();

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

            // FR-7: the drill-down tables are driven directly by budgetChart()'s own
            // contributing-rows data (budgetBreakdown/scheduleBreakdown) - no separate request.
            window.table.clear().rows.add(buildBudgetBreakdownRows(rawData)).draw();
            window.scheduleTable.clear().rows.add(buildScheduleBreakdownRows(rawData)).draw();

            if (data.warnings && data.warnings.currenciesWithoutRates && data.warnings.currenciesWithoutRates.length > 0) {
                const currencyList = data.warnings.currenciesWithoutRates
                    .map(c => `${c.name} (${c.iso_code})`)
                    .join(', ');

                toastHelpers.showWarningToast(
                    __('reports.cashflow.missingRatesWarningPrefix') + currencyList +
                    '. ' + __('reports.cashflow.missingRatesWarningSuffix')
                );
            }
        })
        .always(function () {
            elementRefreshButton.disabled = false;
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
                    forecast: item.forecast,
                });
                return acc;
            }
            if (!existingItem.actual) existingItem.actual = 0;
            if (!existingItem.budget) existingItem.budget = 0;
            if (!existingItem.forecast) existingItem.forecast = 0;

            // At this point all months should be present, so we can safely sum the values
            existingItem.actual += item.actual;
            existingItem.budget += item.budget;
            existingItem.forecast += item.forecast;

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
                    forecast: 0,
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
                    forecast: item.forecast,
                });
                return acc;
            }
            if (!existingItem.actual) existingItem.actual = 0;
            if (!existingItem.budget) existingItem.budget = 0;
            if (!existingItem.forecast) existingItem.forecast = 0;

            // At this point all months should be present, so we can safely sum the values
            existingItem.actual += item.actual;
            existingItem.budget += item.budget;
            existingItem.forecast += item.forecast;

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
                    forecast: 0,
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
                    forecast: 0,
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
elementRefreshButton.addEventListener('click', reloadData);

// Attach event listener to time interval radio buttons to redraw the chart using the already loaded data
document.querySelectorAll('input[name="chart_time_interval"]').forEach(function (element) {
    element.addEventListener('change', function () {
        updateChart(rawData);
    });
});

const tableSelector = '#table';
const scheduleTableSelector = '#scheduleTable';

// Vue app hosting the Budget edit modal (reused from the schedules report page) and the
// read-only budget quick-view modal - a separate DOM subtree from #table, so unlike
// schedules.js there's no ordering constraint against DataTables.init().
const budgetFormApp = createApp({
    components: {
        BudgetForm,
        BudgetQuickView,
    },
    methods: {
        showEditBudgetModal(budgetId) {
            this.$refs.budgetFormEdit.show(budgetId);
        },
        showBudgetQuickView(budgetId) {
            fetch(route('api.v1.budgets.show', { budget: budgetId }))
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => {
                    if (data) {
                        this.$refs.budgetQuickView.show(data);
                    }
                });
        },
        onBudgetSaved() {
            toastHelpers.showSuccessToast(__('Budget saved'));
            // A Budget's own amount/period/account feeds directly into the chart's aggregate
            // totals, so only a full reload (not a local row patch) keeps both the chart and
            // this breakdown table correct.
            reloadData();
        },
    },
});
installRouteGlobal(budgetFormApp);
const budgetForm = budgetFormApp.mount('#budgetChartFormApp');

function confirmAndDelete(routeName, routeParams, id) {
    Swal.fire({
        animation: false,
        text: __('Are you sure to want to delete this item?'),
        icon: 'warning',
        showCancelButton: true,
        cancelButtonText: __('Cancel'),
        confirmButtonText: __('Delete'),
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        window.axios.delete(window.route(routeName, routeParams))
            .then(function () {
                toastHelpers.showSuccessToast(__('Deleted (#:id)', { id }));
                reloadData();
            })
            .catch(function (error) {
                toastHelpers.showErrorToast(
                    __('Error deleting (#:id): :error', {
                        id,
                        error: error.response?.data?.message || error.message,
                    })
                );
            });
    });
}

function deleteBudget(budgetId) {
    confirmAndDelete('api.v1.budgets.destroy', { budget: budgetId }, budgetId);
}

function amountColumn() {
    return {
        data: 'amount',
        title: __('Amount'),
        className: 'dt-nowrap',
        type: 'num',
        render: function (data, type, row) {
            if (type === 'display') {
                return dataTableHelpers.toFormattedCurrency(
                    type,
                    data,
                    window.YAFFA.userSettings.locale,
                    row.currency
                );
            }

            return data;
        },
    };
}

// FR-7: a breakdown of the standalone Budget rows contributing to the chart - populated
// directly from budgetChart()'s own response (see buildBudgetBreakdownRows() / reloadData())
// rather than a separate request. Only edit/delete are offered (via BudgetApiController
// routes) - a Budget row has no schedule to enter/skip and no linked transaction to clone/replace.
window.table = $(tableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: [],
    columns: [
        {
            data: 'category_name',
            title: __('Category'),
        },
        {
            data: 'account_name',
            title: __('Account'),
            render: function (data) {
                return data || __('No account');
            },
        },
        amountColumn(),
        {
            data: 'cadence',
            title: __('Cadence'),
        },
        {
            data: 'budget_id',
            title: __('Actions'),
            orderable: false,
            className: 'text-center dt-nowrap',
            render: function (budgetId) {
                return `
                    <button class="btn btn-xs btn-success" data-view-budget="${budgetId}" type="button" title="${__('Quick view')}">
                        <i class="fa fa-fw fa-eye"></i>
                    </button>
                    <button class="btn btn-xs btn-primary" data-edit-budget="${budgetId}" type="button" title="${__('Edit')}">
                        <i class="fa fa-fw fa-edit"></i>
                    </button>
                    <button class="btn btn-xs btn-danger" data-delete-budget="${budgetId}" type="button" title="${__('Delete')}">
                        <i class="fa fa-fw fa-trash"></i>
                    </button>
                `;
            },
        },
    ],
    createdRow: function (row, data) {
        if (!data.account_name) {
            $('td', row).eq(1).addClass('text-muted text-italic');
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

$(tableSelector).on('click', '[data-view-budget]', function () {
    budgetForm.showBudgetQuickView(Number(this.dataset.viewBudget));
});

$(tableSelector).on('click', '[data-edit-budget]', function () {
    budgetForm.showEditBudgetModal(Number(this.dataset.editBudget));
});

$(tableSelector).on('click', '[data-delete-budget]', function () {
    deleteBudget(Number(this.dataset.deleteBudget));
});

function deleteScheduleTransaction(transactionId) {
    confirmAndDelete('api.v1.transactions.destroy', { transaction: transactionId }, transactionId);
}

// Same idea as the budgets table above, for the schedule-transaction side of the total
// (ReportApiController::budgetChart()'s scheduleBreakdown). A schedule row has a real linked
// transaction, so it offers edit/replace/delete (mirroring the schedules report's context
// menu) rather than the budgets table's view/edit/delete.
window.scheduleTable = $(scheduleTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: [],
    columns: [
        {
            data: 'category_names',
            title: __('Categories'),
        },
        amountColumn(),
        {
            data: 'cadence',
            title: __('Cadence'),
        },
        {
            data: 'transaction_id',
            title: __('Actions'),
            orderable: false,
            className: 'text-center dt-nowrap',
            render: function (transactionId) {
                return `
                    <a class="btn btn-xs btn-primary" href="${window.route('transaction.open', { transaction: transactionId, action: 'edit', callback: 'back' })}" title="${__('Edit transaction')}">
                        <i class="fa fa-fw fa-edit"></i>
                    </a>
                    <a class="btn btn-xs btn-primary" href="${window.route('transaction.open', { transaction: transactionId, action: 'replace' })}" title="${__('Edit and create new schedule')}">
                        <i class="fa fa-fw fa-calendar"></i>
                    </a>
                    <button class="btn btn-xs btn-danger" data-delete-transaction="${transactionId}" type="button" title="${__('Delete')}">
                        <i class="fa fa-fw fa-trash"></i>
                    </button>
                `;
            },
        },
    ],
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

$(scheduleTableSelector).on('click', '[data-delete-transaction]', function () {
    deleteScheduleTransaction(Number(this.dataset.deleteTransaction));
});

// One search field drives both tables (matches the account list page's search pattern/behavior).
dataTableHelpers.initializeStandardExternalSearch(window.table);
dataTableHelpers.initializeStandardExternalSearch(window.scheduleTable);

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
const presetCategories = getArrayParamFromUrl(searchParams, 'categories').map(category => parseInt(category));
presetCategories.forEach(category => presetFilters.categories[category] = false);

/** @var {number} presetAccount ID of initially selected account */
const presetAccount = searchParams.has('accountEntity') ? parseInt(searchParams.get('accountEntity')) : undefined;
if (typeof presetAccount !== 'undefined') {
    presetFilters.account = false;
}

// Update URL params based on JS Tree selection
let rebuildUrl = function () {
    // Any filter change (category, account, or the two callers below) invalidates the
    // currently displayed chart until the user reloads.
    markDataStale();

    let url = new URL(window.location.origin + window.location.pathname);

    // Accounts
    if ($(accountSelector).val()) {
        url.searchParams.append('accountEntity', $(accountSelector).val());
    }

    // Categories
    $(treeSelector).jstree('get_checked').forEach((category) => url.searchParams.append('categories[]', category));

    // Update the URL
    window.history.pushState('', '', url.toString());

    // Finally, adjust reload button availability: at least one category must be checked, and
    // if the account scope is restricted to a single account, one must be selected
    const accountScopeRequiresSelection = $('input[name=table_filter_account_scope]:checked').val() === 'selected';
    elementRefreshButton.disabled = ($(treeSelector).jstree('get_checked').length === 0)
        || (accountScopeRequiresSelection && !$(accountSelector).val());
}

// Initialize category tree view
$(treeSelector)
    .jstree({
        core: {
            data: function (_obj, callback) {
                fetch('/api/v1/categories?withInactive=1&q=*')
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
                                state: {
                                    selected: presetCategories.includes(category.id)
                                }
                            }
                        });
                        callback.call(this, categories);
                    })
            },
            themes: {
                dots: false,
                icons: false
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
        url: '/api/v1/accounts',
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
        url: '/api/v1/accounts/' + presetAccount,
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
    }

    rebuildUrl();
});

// Set initial state of account selector
$(accountSelector).prop('disabled', $('input[name=table_filter_account_scope]:checked').val() !== 'selected');

document.addEventListener(COLOR_MODE_EVENT, () => {
    initChart();
    if (rawData && rawData.length > 0) {
        updateChart(rawData);
    }
});
