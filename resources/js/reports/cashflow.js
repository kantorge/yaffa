import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

$(function () {
    var localeString = "hu-HU";

    var chart = am4core.create("chartdiv", am4charts.XYChart);
    chart.data = transactionDataHistory;

    chart.numberFormatter.intlLocales = localeString;
    chart.dateFormatter.intlLocales = localeString;

    chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: currency.iso_code,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: currency.num_digits,
        maximumFractionDigits: currency.num_digits
    };

    chart.dateFormatter.dateFormat = {
        "year": "numeric",
        "month": "long",
    };

    var dateAxis = chart.xAxes.push(new am4charts.DateAxis());
    dateAxis.dataFields.category = "month";
    dateAxis.dateFormatter.intlLocales = localeString;
    dateAxis.dateFormats.setKey("year", { "year": "numeric" });
    dateAxis.dateFormats.setKey("month", { "year": "numeric", "month": "short" });

    // Set up event listener to date axis to highlight current month
    dateAxis.events.on("datavalidated", function(ev) {
        var axis = ev.target;
        const now = new Date();

        // Create a range
        var range = axis.axisRanges.create();
        range.date = new Date(now.getFullYear(), now.getMonth(), 1);
        range.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        range.axisFill.fill = am4core.color("#396478");
        range.axisFill.fillOpacity = 0.4;
        range.grid.strokeOpacity = 0;
    });

    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

    // Monthly bars
    var seriesMonhtly = chart.series.push(new am4charts.ColumnSeries());
    seriesMonhtly.dataFields.valueY = "value";
    seriesMonhtly.yAxis = valueAxis;
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

    var scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesMonhtly);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();
});
