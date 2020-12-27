require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    $('#table').DataTable({
        data: investments,
        columns: [
        {
            data: "name",
            title: "Name"
        },
        {
            data: "investment_group.name",
            title: "Group",
        },
        {
            data: "quantity",
            title: "Quantity",
            render: function ( data, type, row, meta ) {
                return numberRenderer(data);
            },
        },
        {
            data: "price",
            title: "Latest price",
            render: function ( data, type, row, meta ) {
                return $.fn.dataTable.render.number('&nbsp;', ',', 4, '', '&nbsp;' + row.currency.suffix ).display(data);
            },
        },
        {
            data: "price",
            title: "Value",
            render: function ( data, type, row, meta ) {
                return $.fn.dataTable.render.number('&nbsp;', ',', row.currency.num_digits, '', '&nbsp;' + row.currency.suffix ).display(row.quantity * row.price);
            },
        },
        {
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '';
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
