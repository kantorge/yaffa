import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import { createApp } from 'vue';
import PayeeForm from '../components/PayeeForm.vue';
import Swal from 'sweetalert2';

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

// Initialize Vue app
const vueApp = createApp({
    components: {
        PayeeForm,
    },
    methods: {
        onPayeeCreated(payee) {
            // Add the new payee to the table
            window.payees.push({
                ...payee,
                transactions_count: 0,
                from_count: 0,
                to_count: 0,
                from_min_date: null,
                from_max_date: null,
                to_min_date: null,
                to_max_date: null,
                transactions_min_date: null,
                transactions_max_date: null,
            });
            
            window.table.row.add(window.payees[window.payees.length - 1]).draw();
            
            // Show success notification
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Success'),
                    body: __('Payee added'),
                    toastClass: 'bg-success',
                }
            });
            window.dispatchEvent(notificationEvent);
        },
        onPayeeUpdated(payee) {
            // Find and update the payee in the data array
            const index = window.payees.findIndex(p => p.id === payee.id);
            if (index !== -1) {
                // Preserve transaction counts and dates
                window.payees[index] = {
                    ...window.payees[index],
                    ...payee,
                };
                
                // Redraw the table
                window.table.row((idx, data) => data.id === payee.id).invalidate().draw();
            }
            
            // Show success notification
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Success'),
                    body: __('Payee updated'),
                    toastClass: 'bg-success',
                }
            });
            window.dispatchEvent(notificationEvent);
        },
        showNewPayeeModal() {
            this.$refs.payeeFormNew.show();
        },
        showEditPayeeModal(payeeId) {
            this.$refs.payeeFormEdit.show(payeeId);
        }
    }
});

const app = vueApp.mount('#payeeIndex');

// Loop payees and prepare data for datatable
window.payees = window.payees.map(function(payee) {
    // Summarize all transactions
    payee.transactions_count = payee.from_count + payee.to_count;

    // Parse various dates, if they exist
    payee.from_min_date = payee.from_min_date ? new Date(Date.parse(payee.from_min_date)) : null;
    payee.from_max_date = payee.from_max_date ? new Date(Date.parse(payee.from_max_date)) : null;
    payee.to_min_date = payee.to_min_date ? new Date(Date.parse(payee.to_min_date)) : null;
    payee.to_max_date = payee.to_max_date ? new Date(Date.parse(payee.to_max_date)) : null;

    // Calculate min and max dates, based on the two from and to dates
    payee.transactions_min_date = payee.from_min_date && payee.to_min_date
        ? new Date(Math.min(payee.from_min_date, payee.to_min_date))
        : payee.from_min_date || payee.to_min_date;
    payee.transactions_max_date = payee.from_max_date && payee.to_max_date
        ? new Date(Math.max(payee.from_max_date, payee.to_max_date))
        : payee.from_max_date || payee.to_max_date;

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
                return  '<button class="btn btn-xs btn-primary edit-payee-btn" data-payee-id="' + data + '" title="' + __('Edit') + '"><i class="fa fa-edit"></i></button> ' +
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
                    // Update row in table data source
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
            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));
            let element = $(this);

            // Confirm the action with the user using Sweetalert2
            Swal.fire({
                title: __('Are you sure?'),
                text: __('Are you sure to want to delete this item?'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: __('Yes, delete it!'),
                cancelButtonText: __('Cancel')
            }).then((result) => {
                if (result.isConfirmed) {
                    // Change icon to spinner
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
                            // Update row in table data source
                            window.payees = window.payees.filter(payee => payee.id !== data.accountEntity.id);

                            row.remove().draw();
                            
                            Swal.fire({
                                title: __('Deleted!'),
                                text: __('Payee deleted'),
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        },
                        error: function (_data) {
                            Swal.fire({
                                title: __('Error'),
                                text: __('Error while trying to delete payee'),
                                icon: 'error'
                            });
                        },
                        complete: function (_data) {
                            // Restore button icon
                            element.removeClass('busy');
                        }
                    });
                }
            });
        });

        // Listener for edit button
        $(settings.nTable).on("click", "button.edit-payee-btn", function () {
            const payeeId = $(this).data('payee-id');
            app.showEditPayeeModal(payeeId);
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

// Listener for new payee button
$('#button-new-payee').on('click', function() {
    app.showNewPayeeModal();
});
