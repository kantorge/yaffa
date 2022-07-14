require( 'datatables.net' );
require( 'datatables.net-bs' );
import { RRule } from 'rrule';
import * as dataTableHelpers from './../components/dataTableHelper'

import * as am4core from "@amcharts/amcharts4/core";
import * as am4charts from "@amcharts/amcharts4/charts";
import am4themes_animated from "@amcharts/amcharts4/themes/animated";
am4core.useTheme(am4themes_animated);

const getAverage = (data, attribute) => data.reduce((acc, val) => acc + val[attribute], 0) / data.length;

const computeMovingAverage = (baseData, period) => {
    var maxActualDate = null;
    for (var i = baseData.length ; i > 0; i--) {
        if (baseData[i-1].actual) {
            maxActualDate = baseData[i-1].date;
            break;
        }
    }

    if (!maxActualDate) {
        maxActualDate = baseData[baseData.length - 1].date;
    }

    return baseData.map(function(currentItem, index) {
        if (currentItem.date > maxActualDate) {
            if (index > 0) {
                currentItem.movingAverage = baseData[index - 1].movingAverage;
            }
            return currentItem;
        }

        var intervalStart = new Date(currentItem.date.getTime());
        intervalStart.setMonth(intervalStart.getMonth() - period);
        var intervalEnd = currentItem.date;

        var previousPeriod = baseData.filter(function(item) {
            return item.date >= intervalStart && item.date <= intervalEnd;
        });

        currentItem.movingAverage = getAverage(previousPeriod, 'actual');

        return currentItem;
    })
}

window.tableData = [];

$(function () {
    window.chart = am4core.create("chartdiv", am4charts.XYChart);

    chart.numberFormatter.intlLocales = "hu-HU";
    chart.numberFormatter.numberFormat = {
        style: 'currency',
        currency: currency.iso_code,
        minimumFractionDigits: currency.num_digits,
        maximumFractionDigits: currency.num_digits
    };

    var categoryAxis = chart.xAxes.push(new am4charts.DateAxis());
    categoryAxis.dataFields.category = "period";

    var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

    var seriesBudget = chart.series.push(new am4charts.LineSeries());
    seriesBudget.dataFields.valueY = "budget";
    seriesBudget.dataFields.dateX = "date";
    seriesBudget.name = "Budget";
    seriesBudget.tooltipText = "{dateX}: [bold]{valueY}[/]";

    var seriesActual = chart.series.push(new am4charts.ColumnSeries());
    seriesActual.dataFields.valueY = "actual";
    seriesActual.dataFields.dateX = "date";
    seriesActual.name = "Actual";
    seriesActual.tooltipText = "{dateX}: [bold]{valueY}[/]";

    var seriesMovingAverage = chart.series.push(new am4charts.LineSeries());
    seriesMovingAverage.dataFields.valueY = "movingAverage";
    seriesMovingAverage.dataFields.dateX = "date";
    seriesMovingAverage.name = "Moving average";
    seriesMovingAverage.tooltipText = "{dateX}: [bold]{valueY}[/]";

    var scrollbarX = new am4charts.XYChartScrollbar();
    scrollbarX.series.push(seriesBudget);
    scrollbarX.series.push(seriesActual);
    scrollbarX.series.push(seriesMovingAverage);
    chart.scrollbarX = scrollbarX;

    chart.legend = new am4charts.Legend();
    chart.cursor = new am4charts.XYCursor();

    $("#reload").on('click', function() {
        $(this).prop('disabled', true);

        $.ajax({
            url: '/api/budgetchart',
            data: {
                categories: $("#category_id").val(),
                byYears: (byYears ? 1 : 0),
            }
        })
        .done(function(data) {
            // Convert date
            data = data.map(function(item) {
                item.date = new Date(item.period);
                return item;
            });

            // Add moving average (assuming data is ordered)
            if (data.length > 0) {
                data = computeMovingAverage(data, 12 * (byYears ? 5 : 1));
            }
            chart.data = data;
            chart.invalidateData();
        })
        .always(function() {
            $("#reload").prop('disabled', false);
        });

        window.table.ajax.reload();
    });

    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    window.table = $('#table').DataTable({
        //data: tableData,
        ajax: {
            url: '/api/scheduled_transactions',
            type: 'GET',
            dataSrc: function(data) {
                return data.map(function(transaction) {
                    transaction.transaction_schedule.start_date = new Date(transaction.transaction_schedule.start_date);
                    if (transaction.transaction_schedule.next_date) {
                        transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
                    }
                    if (transaction.transaction_schedule.end_date) {
                        transaction.transaction_schedule.end_date = new Date(transaction.transaction_schedule.end_date);
                    }

                    // Create rule
                    transaction.transaction_schedule.rule = new RRule({
                        freq: RRule[transaction.transaction_schedule.frequency],
                        interval: transaction.transaction_schedule.interval,
                        dtstart: transaction.transaction_schedule.start_date,
                        until: transaction.transaction_schedule.end_date,
                    });

                    transaction.transaction_schedule.active = !!transaction.transaction_schedule.rule.after(new Date(), true);

                    return transaction;
                });
            },
            data: function () {
                return $.extend( {}, {
                    'categories': $("#category_id").val()
                });
            },
            deferRender: true
        },
        columns: [
            {
                data: "transaction_schedule.start_date",
                title: "Start date",
                render: function(data, type, row, meta) {
                    return data.toLocaleDateString('hu-HU'); //TODO: make this dynamic
                }
            },
            {
                data: "transaction_schedule.rule",
                title: "Schedule",
                render: function(data, type, row, meta) {
                    // Return human readable format
                    return data.toText();
                }
            },
            {
                data: "transaction_schedule.next_date",
                title: "Next date",
                render: function(data, type, row, meta) {
                    if (!data) {
                        return '';
                    }

                    return data.toLocaleDateString('hu-HU'); //TODO: make this dynamic
                }
            },
            {
                data: "schedule",
                title: "Schedule",
                render: function (data, type) {
                    return dataTableHelpers.booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "budget",
                title: "Budget",
                render: function (data, type) {
                    return dataTableHelpers.booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "transaction_schedule.active",
                title: "Active",
                render: function (data, type) {
                    return dataTableHelpers.booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "transaction_type.type",
                title: "Type",
            },
            {
                title: 'Payee',
                render: function (data, type, row, meta ) {
                    if (row.transaction_type.type == 'Standard') {
                        if (row.transaction_type.name == 'withdrawal') {
                            return row.account_to_name;
                        }
                        if (row.transaction_type.name == 'deposit') {
                            return row.account_from_name;
                        }
                        if (row.transaction_type.name == 'transfer') {
                            if (row.transaction_operator == 'minus') {
                                return 'Transfer to ' + row.account_to_name;
                            } else {
                                return 'Transfer from ' + row.account_from_name;
                            }
                        }
                    } else if (row.transaction_type.type == 'Investment') {
                        return row.investment_name;
                    }

                    return null;
                },
            },
            {
                title: "Category",
                render: function (_data, _type, row) {
                    //standard transaction
                    if (row.transaction_type.type === 'Standard') {
                        if (row.categories.length > 1) {
                            return 'Split transaction';
                        }
                        if (row.categories.length === 1) {
                            return row.categories[0];
                        }

                        return '';
                    }
                    //investment transaction
                    if (row.transaction_type.type === 'Investment') {
                        if (!row.quantity_operator) {
                            return row.transaction_type.name;
                        }
                        if (!row.transaction_operator) {
                            return row.transaction_type.name + " " + row.quantity;
                        }

                        return row.transaction_type.name + " " + row.quantity + " @ " + numberRenderer(row.price);
                    }

                    return '';
                },
                orderable: false
            },
            {
                title: "Amount",
                render: function (_data, _type, row) {
                    let prefix = '';
                    if (row.transaction_operator == 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_operator == 'plus') {
                        prefix = '+ ';
                    }
                    return prefix + numberRenderer(row.amount_to);
                },
            },
            {
                data: "comment",
                title: "Comment",
                render: function(data, type) {
                    if(type === 'display'){
                       data = truncateString(data, 20);
                    }

                    return data;
                 },
                createdCell: function (td, cellData) {
                    $(td).prop('title', cellData);
                }
            },
            {
                data: "tags",
                title: "Tags",
                render: function (data) {
                    return data.join(', ');
                }
            },
            {
                data: 'id',
                title: "Actions",
                render: function (data, _type, row) {
                    return  dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'delete') +
                            (row.schedule
                             ? '<a href="' + (row.transaction_type.type == 'Standard' ? route('transactions.openStandard', {transaction: data, action: 'enter'}) : route('transactions.openInvestment', {transaction: data, action: 'enter'})) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                               '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> '
                             : '');
                },
                orderable: false
            }
        ],
        createdRow: function( row, data) {
            if (!data.transaction_schedule.next_date) {
                return;
            }

            if (data.transaction_schedule.next_date  < new Date(new Date().setHours(0,0,0,0)) ) {
                $(row).addClass('danger');
            } else if (data.transaction_schedule.next_date  < new Date(new Date().setHours(24,0,0,0)) ) {
                $(row).addClass('warning');
            }
        },
        order: [
            [ 0, "asc" ]
        ],
        deferRender:    true,
        scrollY:        '400px',
        scrollCollapse: true,
        scroller:       true,
        stateSave:      false,
        processing:     true,
        paging:         false,
    });

    $("#table").on("click", ".data-skip", function() {
        let form = document.getElementById('form-skip');
        form.action = route('transactions.skipScheduleInstance', {transaction: this.dataset.id});
        form.submit();
    });

    dataTableHelpers.initializeDeleteButton("#table");
});

// DataTables helper: truncate a string
function truncateString(str, max, add) {
    add = add || '...';
    return (typeof str === 'string' && str.length > max ? str.substring(0, max) + add : str);
}
