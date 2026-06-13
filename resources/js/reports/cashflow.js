import * as am4core from '@amcharts/amcharts4/core';
import * as am4charts from '@amcharts/amcharts4/charts';
import am4themes_animated from '@amcharts/amcharts4/themes/animated';
import { applyAmChartsLocalization } from '@/shared/lib/i18n/amcharts';

am4core.useTheme(am4themes_animated);

// Select2 for account selection
import { __ } from '@/shared/lib/i18n';
import { initializeSelect2 } from '@/shared/lib/select2';
initializeSelect2(window.YAFFA.userSettings.language);

import * as toastHelpers from '@/shared/lib/toast';
import { applyAmChartsColorTheme, COLOR_MODE_EVENT } from '@/shared/lib/ui/amchartsColorTheme';

window.chartData = [];
let chart, dateAxis, valueAxis, seriesMonhtly, seriesTotal, seriesInvestment, valueAxisTotal, scrollbarX;
let cachedChartData = null;

function initChart() {
    if (chart) chart.dispose();

    applyAmChartsColorTheme(am4core);

    chart = am4core.create("chartdiv", am4charts.XYChart);
    applyAmChartsLocalization(chart, window.YAFFA.userSettings.locale, window.YAFFA.userSettings.language);

    chart.numberFormatter.intlLocales = window.YAFFA.userSettings.locale;
    chart.dateFormatter.intlLocales = window.YAFFA.userSettings.locale;

    chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: window.YAFFA.userSettings.baseCurrency.iso_code,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 0
    };

    chart.dateFormatter.dateFormat = {
        "year": "numeric",
        "month": "long",
    };

    dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.dataFields.category = "month";
    dateAxis.dateFormatter.intlLocales = window.YAFFA.userSettings.locale;
    dateAxis.dateFormats.setKey("year", {"year": "numeric"});
    dateAxis.dateFormats.setKey("month", {"year": "numeric", "month": "short"});

    // Set up event listener to date axis to highlight current month
    dateAxis.events.on("datavalidated", function (ev) {
        let axis = ev.target;
        const now = new Date();

        // Create a range
        let range = axis.axisRanges.create();
        range.date = new Date(now.getFullYear(), now.getMonth(), 1);
        range.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        range.axisFill.fill = am4core.color("#396478");
        range.axisFill.fillOpacity = 0.4;
        range.grid.strokeOpacity = 0;
    });

    valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

    // Monthly account balance fact bars
    seriesMonhtly = chart.series.push(new am4charts.ColumnSeries());
    seriesMonhtly.dataFields.valueY = "account_balance";
    seriesMonhtly.yAxis = valueAxis;
    seriesMonhtly.dataFields.dateX = "month";
    seriesMonhtly.name = __('Monthly balance change');
    seriesMonhtly.columns.template.strokeOpacity = 0;
    seriesMonhtly.tooltipText = "{dateX}: [b]{valueY}[/]";
    seriesMonhtly.columns.template.fill = am4core.color('green');
    seriesMonhtly.columns.template.adapter.add("fill", function(fill, target) {
        if (target.dataItem && (target.dataItem.valueY < 0)) {
          return am4core.color("red");
        }

        return fill;
    });

    // Running total line for account balance fact
    seriesTotal = chart.series.push(new am4charts.LineSeries());
    seriesTotal.dataFields.valueY = "account_balance_running_total";
    seriesTotal.dataFields.dateX = "month";
    seriesTotal.strokeWidth = 2;
    seriesTotal.stroke = am4core.color('black');
    seriesTotal.name = __('Running total');
    seriesTotal.tooltipText = "{dateX}: [b]{valueY}[/]";

    // Line for the investment value fact
    seriesInvestment = chart.series.push(new am4charts.LineSeries());
    seriesInvestment.dataFields.valueY = "investment_value";
    seriesInvestment.dataFields.dateX = "month";
    seriesInvestment.strokeWidth = 2;
    seriesInvestment.stroke = am4core.color('blue');
    seriesInvestment.name = __('Investment value');
    seriesInvestment.tooltipText = "{dateX}: [b]{valueY}[/]";

    valueAxisTotal = chart.yAxes.push(new am4charts.ValueAxis());
    valueAxisTotal.renderer.opposite = true;

    scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesMonhtly);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();
}

initChart();


function reloadData() {
    const url = window.route('api.v1.reports.cashflow', {
            withForecast: document.getElementById('withForecast').checked,
            accountEntity: $(elementAccountSelector).val() ? $(elementAccountSelector).val() : undefined,
        });

    document.getElementById('btnReload').disabled = true;

    fetch(url)
        .then(response => response.json())
        .then(function (data) {
            // Check if the result is busy, which means the data is not ready yet
            if (data.result === 'busy') {
                document.getElementById('placeholder').classList.remove('hidden');
                document.getElementById('chartdiv').classList.add('hidden');

                toastHelpers.showWarningToast(data.message);

                return;
            }

            cachedChartData = data.chartData;
            chart.data = data.chartData;
            chart.invalidateData();
            document.getElementById('placeholder').classList.add('hidden');
            document.getElementById('chartdiv').classList.remove('hidden');

            // Log exchange rate details to the browser console to help diagnose currency conversion issues
            if (data.debug && data.debug.length > 0) {
                const baseCurrency = window.YAFFA.userSettings.baseCurrency.iso_code;
                const flaggedCount = data.debug.filter(r => r.flags && r.flags.length > 0).length;
                const groupLabel = flaggedCount > 0
                    ? `Cashflow debug ⚠ ${flaggedCount} flagged row(s) (base currency: ${baseCurrency})`
                    : `Cashflow debug: currency exchange rate details (base currency: ${baseCurrency})`;

                console.groupCollapsed(groupLabel);
                console.table(data.debug.map(row => ({
                    'Month': row.month,
                    'Type': row.transaction_type,
                    'Currency': row.currency_iso_code,
                    'Raw amount': row.raw_amount,
                    'Exchange rate': row.is_base_currency
                        ? '(base currency, rate: 1)'
                        : (row.exchange_rate != null ? row.exchange_rate : '⚠ no rate found, used 1:1 fallback'),
                    'Rate source month': row.rate_source_month != null
                        ? row.rate_source_month
                        : (row.is_base_currency ? '(base currency)' : '⚠ no rate found, fallback to 1:1'),
                    'Converted amount': row.converted_amount,
                    'Flags': row.flags && row.flags.length > 0 ? row.flags.join(', ') : '',
                })));

                const flaggedRows = data.debug.filter(r => r.flags && r.flags.length > 0);
                if (flaggedRows.length > 0) {
                    console.warn('Flagged rows:', flaggedRows);
                }

                console.groupEnd();
            }

            // Check for warnings about currencies without rates
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
        .catch(error => console.error(error))
        .finally(() => {
            document.getElementById('btnReload').disabled = false;
        });
}

const elementAccountSelector = '#cashflowAccount';

function rebuildUrl() {
    let params = [];

    // Accounts
    if ($(elementAccountSelector).val()) {
        params.push('accountEntity=' + $(elementAccountSelector).val());
    }

    // With forecast
    if (document.getElementById('withForecast').checked) {
        params.push('withForecast=1');
    }

    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));
}

// Account filter
$(elementAccountSelector).select2({
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

// Input event listeners
document.getElementById('singleAxis').addEventListener('change', function(){
    if (seriesTotal.yAxis == valueAxis) {
        seriesTotal.yAxis = valueAxisTotal;

        valueAxisTotal.disabled = false;
    } else {
        seriesTotal.yAxis = valueAxis;

        valueAxisTotal.disabled = true;
    }
});
document.getElementById('withForecast').addEventListener('change', rebuildUrl);
document.getElementById('btnReload').addEventListener('click', reloadData);

// Default account
if (window.presetAccount) {
    $.ajax({
        url: '/api/v1/accounts/' + window.presetAccount,
        data: {
            _token: window.csrfToken,
        }
    })
        .done(data => {
            // Create the option and append to Select2
            $(elementAccountSelector).append(new Option(data.name, data.id, true, true))
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

            // Initial data for the preset account
            reloadData();
        });
} else {
    // Initial data for all accounts
    reloadData();
}

/**
 * Initialize dismissible alert for rate note
 * Fetches user's preference and shows alert only if not previously dismissed
 * Saves preference when user closes the alert
 */
function initializeRateNoteAlert() {
    const dismissKey = 'dismissCashflowRateNote';
    const alert = document.getElementById('cashflowRateNoteAlert');

    if (!alert) return;

    // Check if user has dismissed this alert before
    fetch(window.route('api.v1.users.me.preferences.get', { key: dismissKey }))
        .then(response => response.json())
        .then(data => {
            // Only show alert if user has NOT dismissed it
            if (data.value !== true) {
                alert.classList.remove('hidden');
            }
        })
        .catch(error => console.error('Failed to fetch dismissal preference:', error));

    // Handle dismiss button click
    const dismissButton = alert.querySelector('[data-bs-dismiss="alert"]');
    if (dismissButton) {
        dismissButton.addEventListener('click', () => {
            fetch(window.route('api.v1.users.me.preferences.set', { key: dismissKey }), {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                },
            })
            .catch(error => console.error('Failed to save dismissal preference:', error));
        });
    }
}

// Initialize rate note alert when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRateNoteAlert);
} else {
    initializeRateNoteAlert();
}

document.addEventListener(COLOR_MODE_EVENT, () => {
    initChart();
    if (cachedChartData) {
        chart.data = cachedChartData;
        chart.invalidateData();
    }
});
