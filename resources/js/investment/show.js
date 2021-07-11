require( 'datatables.net' );
require( 'datatables.net-bs' );

require( 'daterangepicker');
var moment = require('moment');
//moment().format();

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

Number.prototype.toLocalCurreny = function(currency) {
    return this.toLocaleString('hu-HU', {
        style: 'currency',
        currency: currency.iso_code,
        minimumFractionDigits: currency.num_digits,
        maximumFractionDigits: currency.num_digits});
};

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    window.summary = {
        'Buying' : {
            value : 0,
            isCurrency : true,
        },
        'Selling' : {
            value : 0,
            isCurrency : true,
        },
        'Added': {
            value : 0,
            isCurrency : false,
        },
        'Removed': {
            value : 0,
            isCurrency : false,
        },
        'Dividend' : {
            value : 0,
            isCurrency : true,
        },
        'Commission' : {
            value : 0,
            isCurrency : true,
        },
        'Taxes' : {
            value : 0,
            isCurrency : true,
        },
        'Quantity' : {
            value : 0,
            isCurrency : false,
        },
        'Value' : {
            value : 0,
            isCurrency : true,
        },
        'Result' : {
            value : 0,
            isCurrency : true,
        },
    };

    window.table = $('#table').DataTable({
        data: transactions,
        columns: [
        {
            data: "date",
            title: "Date",
        },
        {
            data: "transaction_name",
            title: "Transaction",
        },
        {
            data: "quantity",
            title: "Quantity",
            render: function(data) {
                if (data !== null) {
                    return data.toLocaleString('hu-HU');
                }
                return null;
            }
        },
        {
            data: "price",
            title: "Price",
            render: function(data, type, row, meta) {
                if (data === null) {
                    return data;
                }

                return data.toLocalCurreny(investment.currency);
            },
        },
        {
            data: "dividend",
            title: "Dividend",
            render: function(data, type, row, meta) {
                if (data === null) {
                    return data;
                }

                return data.toLocalCurreny(investment.currency);
            },
        },
        {
            data: "commission",
            title: "Commission",
            render: function(data, type, row, meta) {
                if (data === null) {
                    return data;
                }

                return data.toLocalCurreny(investment.currency);
            },
        },
        {
            data: "tax",
            title: "Tax",
            render: function(data, type, row, meta) {
                if (data === null) {
                    return data;
                }

                return data.toLocalCurreny(investment.currency);
            },
        },
        {
            title: "Amount",
            render: function( data, type, row, meta) {
                var operator = row.amount_operator;
                if (!operator) {
                    return 0;
                }
                var result = (operator == 'minus'
                        ? - row.price * row.quantity
                        : row.dividend + row.price * row.quantity )
                        - row.tax
                        - row.commission;

                return result.toLocalCurreny(investment.currency);
            }
        },
        {
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                       '<button class="btn btn-xs btn-default set-date" data-type="from" data-date="' + row.date + '"><i class="fa fa-fw fa-toggle-left" title="Make this the start date"></i></button> ' +
                       '<button class="btn btn-xs btn-default set-date" data-type="to" data-date="' + row.date + '"><i class="fa fa-fw  fa-toggle-right" title="Make this the end date"></i></button> ' +
                       '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                       '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ' +
                       '<form id="form-delete-' + data + '" action="' + route('transactions.destroy', {transaction: data}) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
            },
            orderable: false
        }
        ],
        order: [[ 0, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });

    $("#table").on("click", ".set-date", function(e) {
        //TODO: catch invalid combinations
        if ($(this).data('type') == 'from') {
            $('#date_from').data('daterangepicker').setStartDate( $(this).data('date') );
        } else {
            $('#date_to').data('daterangepicker').setStartDate( $(this).data('date') );
        }
        window.table.draw();
        window.calculateSummary.call();
    });

    //initialize date filter inputs
    var datePickerStandardSettings = {
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            format: 'YYYY-MM-DD'
        },
        autoApply: true
    };
    //get min and max dates from transactions
    window.minDate = moment(Math.min(...transactions.map(e => new Date(e.date))));
    window.maxDate = moment(Math.max(...transactions.map(e => new Date(e.date))));

    $('#date_from').daterangepicker(datePickerStandardSettings);
    $('#date_to').daterangepicker(datePickerStandardSettings);

    $('#date_from').on('apply.daterangepicker', function(ev, picker) {
        $('#date_to').data('daterangepicker').minDate = picker.endDate;
        window.table.draw();
        window.calculateSummary.call();
    });

    $('#date_to').on('apply.daterangepicker', function(ev, picker) {
        $('#date_from').data('daterangepicker').maxDate = picker.startDate;
        window.table.draw();
        window.calculateSummary.call();
    });

    //summary calculations
    window.calculateSummary = function() {
        var min = $('#date_from').data('daterangepicker').startDate;
        var max = $('#date_to').data('daterangepicker').endDate;

        var filtered = transactions.filter(function(transaction) {
            var date = moment(transaction.date);
            return ( date >= min && date <= max);
        });

        window.summary.Buying.value = filtered.filter(trx => trx.transaction_name == 'Buy').reduce((sum, trx) => sum + trx.price * trx.quantity, 0);
        window.summary.Added.value = filtered.filter(trx => trx.transaction_name == 'Add').reduce((sum, trx) => sum + trx.quantity, 0);
        window.summary.Removed.value = filtered.filter(trx => trx.transaction_name == 'Remove').reduce((sum, trx) => sum + trx.quantity, 0);
        window.summary.Selling.value = filtered.filter(trx => trx.transaction_name == 'Sell').reduce((sum, trx) => sum + trx.price * trx.quantity, 0);
        window.summary.Dividend.value = filtered.reduce((sum, trx) => sum + trx.dividend, 0);
        window.summary.Commission.value = filtered.reduce((sum, trx) => sum + trx.commission, 0);
        window.summary.Taxes.value = filtered.reduce((sum, trx) => sum + trx.tax, 0);
        window.summary.Quantity.value = filtered.reduce((sum, trx) => sum + (trx.quantity_operator == 'minus' ? -1 : + 1) * trx.quantity, 0);

        if (prices.length > 0) {
            var lastPrice = prices.slice(-1)[0].price;
        } else if (filtered.filter(trx => !!trx.price).length > 0) {  //TODO: remove filter duplicate
            lastPrice = filtered
            .filter(trx => !!trx.price)  //TODO: do we have to account for price of 0
            .sort(function(a,b) {
                return (  moment(a.date).isBefore(moment(b.date))
                        ? 1
                        : (moment(b.date).isBefore(moment(a.date))
                           ? -1
                           : 0
                          )
                       );
            })[0].price;
        } else {
            lastPrice = 1;
        }

        window.summary.Value.value = window.summary.Quantity.value * lastPrice;

        //final result
        window.summary.Result.value =   window.summary.Selling.value
                                      + window.summary.Dividend.value
                                      + window.summary.Value.value
                                      - window.summary.Buying.value
                                      - window.summary.Commission.value
                                      - window.summary.Taxes.value;

        //calculate ROI
        var ROI = (window.summary.Buying.value == 0 ? 0 : window.summary.Result.value / window.summary.Buying.value);
        var years = $('#date_to').data('daterangepicker').startDate.diff($('#date_from').data('daterangepicker').startDate, 'years',true);
        var AROI = (years > 0 ? Math.pow(1 + ROI, 1 / years) - 1 : 0);
        document.getElementById('summaryROI').innerHTML = (ROI * 100).toFixed(2) + '%';
        document.getElementById('summaryAROI').innerHTML = (AROI * 100).toFixed(2) + '%';

        //assign calculated data to respective fields
        for (var prop in window.summary) {
            if (Object.prototype.hasOwnProperty.call(window.summary, prop)) {
                document.getElementById('summary' + prop).innerHTML = (window.summary[prop].isCurrency ? window.summary[prop].value.toLocalCurreny(investment.currency) : window.summary[prop].value.toLocaleString('hu-HU'));
            }
        }
    };

    window.dateReset = function() {
        $('#date_from').data('daterangepicker').setStartDate( minDate );
        $('#date_from').data('daterangepicker').maxDate = maxDate;

        $('#date_to').data('daterangepicker').setStartDate (maxDate);
        $('#date_to').data('daterangepicker').minDate = minDate;

        window.table.draw();
        window.calculateSummary.call();
    };
    window.dateReset.call();

    $("#clear_dates").on('click', dateReset);

    //datatables filtering
    $.fn.dataTable.ext.search.push(
        function( settings, data, dataIndex ) {
            var min = $('#date_from').data('daterangepicker').startDate;
            var max = $('#date_to').data('daterangepicker').startDate;
            var date = moment( data[0] );

            return ( date.isSameOrAfter(min) && date.isSameOrBefore(max));
        }
    );

    //initialize charts
    if (prices.length > 0) {
        var chartPrice = am4core.create("chartPrice", am4charts.XYChart);
        chartPrice.data = prices;

        chartPrice.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        var categoryAxis = chartPrice.xAxes.push(new am4charts.DateAxis());
        categoryAxis.dataFields.category = "date";
        var valueAxis = chartPrice.yAxes.push(new am4charts.ValueAxis());

        var series = chartPrice.series.push(new am4charts.LineSeries());
        series.dataFields.valueY = "price";
        series.dataFields.dateX = "date";
        series.strokeWidth = 3;

        var bullet = series.bullets.push(new am4charts.Bullet());
        var square = bullet.createChild(am4core.Rectangle);
        square.width = 5;
        square.height = 5;
        square.horizontalCenter = "middle";
        square.verticalCenter = "middle";

        var scrollbarX = new am4charts.XYChartScrollbar();
        scrollbarX.series.push(series);
        chartPrice.scrollbarX = scrollbarX;
    } else {
        document.getElementById('chartPrice').remove();
        document.getElementById('priceChartNoData').classList.remove('hidden');
    }

    if (quantities.length > 0) {
        var chartQuantity = am4core.create("chartQuantity", am4charts.XYChart);
        chartQuantity.data = quantities;

        chartQuantity.dateFormatter.inputDateFormat = "yyyy-MM-dd";

        var categoryAxis = chartQuantity.xAxes.push(new am4charts.DateAxis());
        categoryAxis.dataFields.category = "date";
        var valueAxis = chartQuantity.yAxes.push(new am4charts.ValueAxis());

        var series = chartQuantity.series.push(new am4charts.StepLineSeries());
        series.dataFields.valueY = "quantity";
        series.dataFields.dateX = "date";
        series.strokeWidth = 3;

        var scrollbarX = new am4charts.XYChartScrollbar();
        scrollbarX.series.push(series);
        chartQuantity.scrollbarX = scrollbarX;
    } else {
        document.getElementById('chartQuantity').remove();
        document.getElementById('quantityChartNoData').classList.remove('hidden');
    }
});
