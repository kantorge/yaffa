require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: accounts,
        columns: [
        {
            data: "id",
            title: "ID"
        },
        {
            data: "name",
            title: "Name"
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
            data: "config.opening_balance",
            title: "Opening balance"
        },
        {
            data: "config.account_group.name",
            title: "Account group"
        },
        {
            data: "config.currency.name",
            title: "Currency"
        },
        {
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                       '<a href="' + route('account-entity.edit', {type: 'account', account_entity: data}) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                       '<form id="form-delete-' + data + '" action="' + route('account-entity.destroy', {type: 'account', account_entity: data}) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});
