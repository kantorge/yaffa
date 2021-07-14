require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    $('#investmentSummary').DataTable({
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

    $("#investmentSummary").on("click", ".showPriceModal", function() {
        if ($(".showPriceModal i").hasClass("fa-spinner")) {
            return false;
        }

        $(this).find("i").removeClass().addClass('fa fa-spinner fa-spin');

        $.ajax ({
            type: 'GET',
            url: '/api/assets/investment/price/' + $(this).data("id"),
            dataType: "json",
            context: this,
            data: {},
            success: function (data) {
                data.forEach(function(e){
                    e.action = "<button type='button' data-id='" + e.id + "' class='btn btn-xs btn-danger deleteItem'><i class='fa fa-trash' title='Delete item'></i></button>";
                })
                if (typeof window.pricesTable === 'undefined') {
                    window.pricesTable = $("#priceTable").DataTable({
                        data:           data,
                        columns:        [
                            {
                                data:   'date',
                                "orderable": false
                            },
                            {
                                data:   'price',
                                "orderable": false
                            },
                            {
                                data:   'action',
                                "orderable": false
                            }
                        ],
                        searching:      false,
                        //ordering:       false,
                        bInfo:          false,
                        //deferRender:    true,
                        scrollY:        200,
                        scrollCollapse: true,
                        scroller:       true,

                    });
                } else {
                    window.pricesTable.clear();
                    window.pricesTable.rows.add(data);
                    window.pricesTable.draw();
                }

                $("#investment_id").val($(this).data("id"));
                $('#modal-prices').modal('show');

                $(this).find("i").removeClass().addClass('fa fa-line-chart');
            }
        });

    });

});
