require('datatables.net-bs');
import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    // initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
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
                return booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "config.currency.name",
            title: "Currency"
        },
        {
            data: "config.opening_balance",
            title: "Opening balance",
            render: function (data, _type, row) {
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
                return  '<a href="' + route('account-entity.edit', {type: 'account', account_entity: data}) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                        genericDataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],
    order: [
        [ 1, 'asc' ]
    ],
    initComplete : function(settings) {
        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            var row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

            // Do not request change if previous request is still in progress
            if ($(this).hasClass("fa-spinner")) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change account active state
            $.ajax ({
                type: 'PUT',
                url: '/api/assets/accountentity/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    accounts.filter(account => account.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    // Emit a custom event to global scope about the problem
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: 'Error while changing account active state',
                                title: null,
                                icon: null,
                                dismissable: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function(_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    }
});

// TODO: can the type parameter be incorporated into the initializeDeleteButtonListener function?
$(dataTableSelector).on("click", ".data-delete", function() {
    if (!confirm('Are you sure to want to delete this item?')) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('account-entity.destroy', {type: 'account', account_entity: this.dataset.id});
    form.submit();
});

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(2).search(this.value).draw();
});
