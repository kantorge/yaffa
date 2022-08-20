require('datatables.net');
require('datatables.net-bs');
import * as dataTableHelpers from './../components/dataTableHelper';

var numberRenderer = $.fn.dataTable.render.number('&nbsp;', ',', 0).display;

$('#investmentSummary').DataTable({
    data: investments,
    columns: [
        {
            data: "name",
            title: "Name",
            render: function (data, type, row) {
                return '<a href="' + route('investment.show', row.id) + '" class="" title="View investment details">' + data + '</a>';
            },
        },
        {
            data: "investment_group.name",
            title: "Group",
        },
        {
            data: "active",
            title: "Active",
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "quantity",
            title: "Quantity",
            render: function (data) {
                return numberRenderer(data);
            },
        },
        {
            data: "price",
            title: "Latest price",
            render: function (data, type, row) {
                return $.fn.dataTable.render.number('&nbsp;', ',', 4, '', '&nbsp;' + row.currency.suffix).display(data);
            },
        },
        {
            data: "price",
            title: "Value",
            render: function (data, type, row) {
                return $.fn.dataTable.render.number('&nbsp;', ',', row.currency.num_digits, '', '&nbsp;' + row.currency.suffix).display(row.quantity * row.price);
            },
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return '<a href="' + route('investment.show', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="View investment details"></i></a> ' +
                    '<a href="' + route('investment-price.list', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-dollar" title="View investment price list"></i></a> ';
            },
            orderable: false
        }
    ],
    order: [[0, 'asc']],
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change payee active state
            $.ajax({
                type: 'PUT',
                url: '/api/assets/investment/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    investments.filter(investment => investment.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert('Error changing investment active state');
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    },
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});
