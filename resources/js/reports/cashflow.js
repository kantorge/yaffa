import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

$(function () {
    var chart = am4core.create("chartdiv", am4charts.XYChart);
    chart.data = transactionDataHistory;

    //chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

    var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
    categoryAxis.dataFields.category = "month";
    var valueAxisMonthly = chart.yAxes.push(new am4charts.ValueAxis());

    // Monthly bars
    var seriesMonhtly = chart.series.push(new am4charts.ColumnSeries());
    seriesMonhtly.dataFields.valueY = "value";
    seriesMonhtly.yAxis = valueAxisMonthly;
    seriesMonhtly.dataFields.dateX = "month";
    seriesMonhtly.name = 'Monthly change';
    seriesMonhtly.tooltipText = "{dateX}: [b]{valueY}[/]";

    // Running total line
    var seriesTotal = chart.series.push(new am4charts.LineSeries());
    seriesTotal.dataFields.valueY = "runningTotal";
    seriesTotal.dataFields.dateX = "month";
    seriesTotal.strokeWidth = 2;
    seriesTotal.name = 'Running total';
    seriesTotal.tooltipText = "{dateX}: [b]{valueY}[/]";

    if (!singleAxes) {
        var valueAxisTotal = chart.yAxes.push(new am4charts.ValueAxis());
        valueAxisTotal.renderer.opposite = true;

        seriesTotal.yAxis = valueAxisTotal;
    }

    /*
    var bullet = seriesTotal.bullets.push(new am4charts.CircleBullet());
    bullet.circle.stroke = am4core.color("#fff");
    bullet.circle.strokeWidth = 1;
    bullet.circle.radius = 3;
    */

    var scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesMonhtly);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();
});
