require('datatables.net');
require('datatables.net-bs');

$(function() {
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    $('#investmentSummary').DataTable({
        data: investments,
        columns: [
            {
                data: "name",
                title: "Name",
                render: function(data, type, row) {
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
                render: function(data, type) {
                    if (type == 'filter') {
                        return  (data ? 'Yes' : 'No');
                    }
                    return (  data
                            ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
                            : '<i class="fa fa-square text-danger" title="No"></i>');
                },
                className: "text-center",
            },
            {
                data: "quantity",
                title: "Quantity",
                render: function(data) {
                    return numberRenderer(data);
                },
            },
            {
                data: "price",
                title: "Latest price",
                render: function(data, type, row) {
                    return $.fn.dataTable.render.number('&nbsp;', ',', 4, '', '&nbsp;' + row.currency.suffix ).display(data);
                },
            },
            {
                data: "price",
                title: "Value",
                render: function(data, type, row) {
                    return $.fn.dataTable.render.number('&nbsp;', ',', row.currency.num_digits, '', '&nbsp;' + row.currency.suffix ).display(row.quantity * row.price);
                },
            },
            {
                data: "id",
                title: "Actions",
                render: function(data) {
                    return '<a href="' + route('investment.show', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="View investment details"></i></a> ' +
                           '<a href="' + route('investment-price.list', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-dollar" title="View investment price list"></i></a> ';
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
});
