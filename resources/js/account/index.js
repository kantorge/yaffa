require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
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
            render: function (data, type) {
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
            data: "config.currency.name",
            title: "Currency"
        },
        {
            data: "config.opening_balance",
            title: "Opening balance",
            render: function (data, _type, row) {
                // Apply currency format to the data from currency config
                return data.toLocalCurrency(row.config.currency);
            },
        },
        {
            data: "config.account_group.name",
            title: "Account group"
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return '' +
                       '<a href="' + route('account-entity.edit', {type: 'account', account_entity: data}) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ';
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function() {
        if (!confirm('Are you sure to want to delete this item?')) {
            return;
        }

        let form = document.getElementById('form-delete');
        form.action = route('account-entity.destroy', {type: 'account', account_entity: this.dataset.id});
        form.submit();
    });
});
