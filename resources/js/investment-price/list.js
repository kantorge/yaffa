require('datatables.net');
require('datatables.net-bs');

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: prices,
        columns: [
        {
            data: "date",
            title: "Date"
        },
        {
            data: "price",
            title: "Price"
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return '<a href="' + route('investment-price.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                       '<form id="form-delete-' + data + '" action="' + route('investment-price.destroy', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
            },
            orderable: false
        }
        ],
        order: [[ 0, 'asc' ]],
        deferRender:    true,
        scrollY:        '400px',
        scrollCollapse: true,
        scroller:       true,
        stateSave:      true,
        processing:     true,
        paging:         false,
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });

    var chart = am4core.create("chartdiv", am4charts.XYChart);
    chart.data = prices;

    chart.dateFormatter.inputDateFormat = "yyyy-MM-dd";

    var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
    categoryAxis.dataFields.category = "date";
    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

    var series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.valueY = "price";
    series.dataFields.dateX = "date";

    var scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(series);
    chart.scrollbarX = scrollbarX;
});