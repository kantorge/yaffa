// Import AmCharts libraries
import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
import am4themes_kelly from "@amcharts/amcharts4/themes/kelly";
am4core.useTheme(am4themes_animated);
am4core.useTheme(am4themes_kelly);

window.chartData = [];
let chart;

function initializeChart() {
    chart = am4core.create("chart", am4charts.XYChart);
    chart.hiddenState.properties.opacity = 0;
    chart.paddingRight = 30;
    chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

    var colorSet = new am4core.ColorSet();
    colorSet.saturation = 0.4;

    chart.data = window.chartData;

    var categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
    categoryAxis.dataFields.category = "name";
    categoryAxis.renderer.labels.template.disabled = true;
    categoryAxis.renderer.grid.template.location = 0;
    categoryAxis.renderer.inversed = true;
    categoryAxis.renderer.cellStartLocation = 0.1;
    categoryAxis.renderer.cellEndLocation = 0.9;

    var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.dateFormatter.dateFormat = "yyyy-MM-dd";
    dateAxis.renderer.minGridDistance = 70;
    dateAxis.baseInterval = { count: 1, timeUnit: "month" };
    dateAxis.max = new Date(window.app_end_date).getTime();
    dateAxis.strictMinMax = true;
    dateAxis.renderer.tooltipLocation = 0;

    var series1 = chart.series.push(new am4charts.ColumnSeries());
    //series1.columns.template.tooltipText = "{name}: {openDateX} - {dateX}";
    series1.columns.template.tooltipText = `[bold]{name}[/]
----
{openDateX} - {dateX}
Last quantity: {formatted_quantity}
Estimated end value: {formatted_value}`
    series1.dataFields.openDateX = "start";
    series1.dataFields.dateX = "end";
    series1.dataFields.categoryY = "name";
    series1.columns.template.strokeOpacity = 1;
    series1.columns.template.height = am4core.percent(100);

    chart.scrollbarX = new am4core.Scrollbar();
    chart.scrollbarY = new am4core.Scrollbar();
}

function filterData() {
    const filterActive = document.querySelector('input[name="active"]:checked').value;
    const filterOpen = document.querySelector('input[name="open"]:checked').value;
    let today = new Date();
    today.setHours(0,0,0,0);

    chart.data = window.chartData
                    // Filter by active flag
                    .filter(function(item) {
                        if (filterActive === 'All') {
                            return true;
                        }
                        return item.active === (filterActive === 'Yes');
                    })
                    // Filter by open status
                    .filter(function(item) {
                        if (filterOpen === 'All') {
                            return true;
                        }
                        let end = new Date(item.end);
                        end.setHours(0,0,0,0);
                        if (filterOpen === 'Yes') {
                            return end >= today;
                        } else {
                            return end < today;
                        }
                    });
}

// Refresh chart data on input change
$('#filters input[type="radio"]').on('change', filterData);

// Fetch API and calculate Gantt dates
const url = '/api/assets/investment/timeline';
fetch(url)
    .then(response => response.json())
    .then(function(data) {
        window.chartData = data.map(function(item) {
            item.value = item.quantity * item.last_price;

            item.formatted_quantity = item.quantity.toLocalQuantity(4, false);
            item.formatted_value = item.value.toLocalCurrency(item.currency, false);

            return item;
        });

        initializeChart();
    })
    .catch(error => console.error(error));
