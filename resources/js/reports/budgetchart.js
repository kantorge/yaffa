import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

$(function () {
    window.chart = am4core.create("chartdiv", am4charts.XYChart);

    chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";
    chart.numberFormatter.intlLocales = "hu-HU";
    chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: currency.iso_code,
        minimumFractionDigits: currency.num_digits,
        maximumFractionDigits: currency.num_digits
    };

    var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
    categoryAxis.dataFields.category = "month";

    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

    var topContainer = chart.chartContainer.createChild(am4core.Container);
    topContainer.layout = "absolute";
    topContainer.toBack();
    topContainer.paddingBottom = 15;
    topContainer.width = am4core.percent(100);
    var axisTitle = topContainer.createChild(am4core.Label);
    axisTitle.text = "Value";
    axisTitle.fontWeight = 600;
    axisTitle.align = "left";
    axisTitle.paddingLeft = 10;

    var seriesBudget = chart.series.push(new am4charts.LineSeries());
    seriesBudget.dataFields.valueY = "budget";
    seriesBudget.dataFields.dateX = "month";
    seriesBudget.name = "Budget";
    seriesBudget.tooltipText = "{dateX}: [bold]{valueY}[/]";

    var seriesActual = chart.series.push(new am4charts.ColumnSeries());
    seriesActual.dataFields.valueY = "actual";
    seriesActual.dataFields.dateX = "month";
    seriesActual.name = "Actual";
    seriesActual.tooltipText = "{dateX}: [bold]{valueY}[/]";

    var scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesBudget);
    scrollbarX.series.push(seriesActual);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();

    $("#reload").on('click', function() {
        $.ajax({
            url: '/api/budgetchart',
            data: {
                categories: $("#category_id").val()
            }
        })
        .done(function(data) {
            chart.data = data;
            chart.invalidateData();
        });

    });
});
