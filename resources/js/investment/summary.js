require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    $('#table').DataTable({
        data: investments,
        columns: [
        {
            data: "name",
            title: "Name",
            render: function(data, type, row, meta) {
                return '<a href="' + route('investment.show', row.id) + '" class="" title="View investment details">' + data +'</a>';
            },
        },
        {
            data: "investment_group.name",
            title: "Group",
        },
        {
            data: "active",
            title: "Active",
            render: function ( data, type, row, meta ) {
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
                return '' +
                       '<a href="' + route('investment.show', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="View investment details"></i></a> ' +
                       '<button type="button" class="btn btn-xs btn-primary showPriceModal" data-id="' + data + '"><i class="fa fa-line-chart" title="Update price"></i></button>';
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
        /*
        initComplete: function () {
            // Apply the search
            this.api().columns().every( function () {
                var that = this;

                $( 'input', this.footer() ).on( 'keyup change clear', function () {
                    if ( that.search() !== this.value ) {
                        that
                            .search( this.value )
                            .draw();
                    }
                } );
            } );
        }
        */
    });

});
