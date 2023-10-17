import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import {
    booleanToTableIcon,
    renderDeleteAssetButton,
} from '../components/dataTableHelper';

const dataTableSelector = '#table';

/**
 * Define the conditions for the delete button, as required by the DataTables helper.
 */
const deleteButtonConditions = [
    {
        property: 'transactions_count',
        value: 0,
        negate: false,
        errorMessage: __('It is already used in transactions.'),
    },
];

let compareDates = function(date1, date2) {
    if (!date1 && !date2) {
        return null;
    } else if (!date1) {
        return date2;
    } else if (!date2) {
        return date1;
    }

    // Compare the Date objects and return the smaller one
    return date1 < date2 ? date1 : date2;
}

// Loop payees and prepare data for datatable
window.payees = window.payees.map(function(payee) {
    // Summarize all transactions
    payee.transactions_count = payee.from_count + payee.to_count;

    // Parse various dates, if they exist
    payee.from_min_date = payee.from_min_date ? new Date(Date.parse(payee.from_min_date)) : null;
    payee.from_max_date = payee.from_max_date ? new Date(Date.parse(payee.from_max_date)) : null;
    payee.to_min_date = payee.to_min_date ? new Date(Date.parse(payee.to_min_date)) : null;
    payee.to_max_date = payee.to_max_date ? new Date(Date.parse(payee.to_max_date)) : null;

    // Calculate min and max dates, based on from and to dates
    payee.transactions_min_date = compareDates(payee.from_min_date, payee.to_min_date);
    payee.transactions_max_date = compareDates(payee.from_max_date, payee.to_max_date);

    return payee;
});

window.table = $(dataTableSelector).DataTable({
    data: payees,
    columns: [
        {
            data: "name",
            title: __('Name')
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "config.category",
            title: __("Default category"),
            render: function(data) {
                return (data ? data.full_name : __('Not set'));
            }
        },
        {
            // Display count of associated transactions
            data: "transactions_count",
            title: __("Transactions"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data > 0 ? data : __('Never used'));
                }
                return data;
            },
            type: 'num',
        },
        {
            // Display first transaction date
            data: "transactions_min_date",
            title: __("First transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            // Display last transaction date
            data: "transactions_max_date",
            title: __("Last transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: 'alias',
            title: __('Import alias'),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.replace('\n', '<br>') : __('Not set'));
                }
                return data;
            }
        },
        {
            data: "id",
            title: __("Actions"),
            render: function(data, _type, row) {
                return  '<a href="' + window.route('account-entity.edit', {type: 'payee', account_entity: data}) + '" class="btn btn-xs btn-primary" title="' + __('Edit') + '"><i class="fa fa-edit"></i></a> ' +
                         renderDeleteAssetButton(row, deleteButtonConditions, __("This payee cannot be deleted.")) +
                        '<a href="' + window.route('payees.merge.form', {payeeSource: data}) + '" class="btn btn-xs btn-primary" title="' + __('Merge into an other payee') + '"><i class="fa fa-random"></i></a> ';
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function(row, data) {
        if (!data.config.category) {
            $('td:eq(2)', row).addClass("text-muted text-italic");
        }
        if (data.transactions_count === 0) {
            $('td:eq(3)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_min_date) {
            $('td:eq(4)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_max_date) {
            $('td:eq(5)', row).addClass("text-muted text-italic");
        }
        if (!data.alias) {
            $('td:eq(6)', row).addClass("text-muted text-italic");
        }
    },
    order: [
        [ 0, 'asc' ]
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete : function(settings) {
        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            var row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

            // Do not request change if previous request is still in progress
            if ($(this).hasClass("fa-spinner")) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change payee active state
            $.ajax ({
                type: 'PUT',
                url: window.route('api.accountentity.updateActive', {accountEntity: row.data().id, active: (row.data().active ? 0 : 1)}),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    payees.filter(payee => payee.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert('Error changing payee active state');
                },
                complete: function(_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });

        // Listener for delete button
        $(settings.nTable).on("click", "td > button.deleteIcon:not(.busy)", function () {
            // Confirm the action with the user
            if (!confirm(__('Are you sure to want to delete this item?'))) {
                return;
            }

            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            let element = $(this);
            element.addClass('busy');

            // Send request to delete payee
            $.ajax({
                type: 'DELETE',
                url: window.route('api.accountentity.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.payees = window.payees.filter(payee => payee.id !== data.accountEntity.id);

                    row.remove().draw();
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'success',
                                message: __('Payee deleted'),
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                error: function (_data) {
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: __('Error while trying to delete payee'),
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function (_data) {
                    // Restore button icon
                    element.removeClass('busy');
                }
            });
        });
    }
});

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
