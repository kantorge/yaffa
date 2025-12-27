import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";

am4core.useTheme(am4themes_animated);
import 'select2';

window.chartData = [];
let chart;

chart = am4core.create("chartdiv", am4charts.XYChart);

chart.numberFormatter.intlLocales = window.YAFFA.locale;
chart.dateFormatter.intlLocales = window.YAFFA.locale;

chart.numberFormatter.numberFormat = {
    style: 'currency',
    currency: window.YAFFA.baseCurrency.iso_code,
    currencyDisplay: 'narrowSymbol',
    minimumFractionDigits: 0
};

chart.dateFormatter.dateFormat = {
    "year": "numeric",
    "month": "long",
};

let dateAxis = chart.xAxes.push(new am4charts.DateAxis());
dateAxis.dataFields.category = "month";
dateAxis.dateFormatter.intlLocales = window.YAFFA.locale;
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

let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

// Monthly account balance fact bars
let seriesMonhtly = chart.series.push(new am4charts.ColumnSeries());
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
let seriesTotal = chart.series.push(new am4charts.LineSeries());
seriesTotal.dataFields.valueY = "account_balance_running_total";
seriesTotal.dataFields.dateX = "month";
seriesTotal.strokeWidth = 2;
seriesTotal.stroke = am4core.color('black');
seriesTotal.name = __('Running total');
seriesTotal.tooltipText = "{dateX}: [b]{valueY}[/]";

// Line for the investment value fact
let seriesInvestment = chart.series.push(new am4charts.LineSeries());
seriesInvestment.dataFields.valueY = "investment_value";
seriesInvestment.dataFields.dateX = "month";
seriesInvestment.strokeWidth = 2;
seriesInvestment.stroke = am4core.color('blue');
seriesInvestment.name = __('Investment value');
seriesInvestment.tooltipText = "{dateX}: [b]{valueY}[/]";

let valueAxisTotal = chart.yAxes.push(new am4charts.ValueAxis());
valueAxisTotal.renderer.opposite = true;

let scrollbarX = new am4charts.XYChartScrollbar();
scrollbarX.series.push(seriesMonhtly);
chart.scrollbarX = scrollbarX;

chart.legend = new am4charts.Legend();
chart.cursor = new am4charts.XYCursor();


function reloadData() {
    const url = window.route('api.reports.cashflow', {
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

                // Emit a custom event to global scope about the result
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        header: __('Warning'),
                        body: data.message,
                        toastClass: "bg-warning",
                    }
                });
                window.dispatchEvent(notificationEvent);

                return;
            }

            chart.data = data.chartData;
            chart.invalidateData();
            document.getElementById('placeholder').classList.add('hidden');
            document.getElementById('chartdiv').classList.remove('hidden');
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
        url: '/api/assets/account/' + window.presetAccount,
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
