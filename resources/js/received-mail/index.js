import "datatables.net-responsive-bs5";

import * as dataTableHelpers from '../components/dataTableHelper';

const dataTableSelector = '#table';

window.mails = window.mails.map(function (mail) {
    mail.date = new Date(mail.created_at);
    return mail;
})

window.table = $(dataTableSelector).DataTable({
    data: window.mails,
    columns: [
        {
            data: 'date',
            title: __('Received at'),
            render: function (data, type) {
                if (type === 'display' && data && data.toLocaleDateString) {
                    return data.toLocaleString(window.YAFFA.locale);
                }

                return data;
            },
            className: "dt-nowrap",
            type: 'date',
        },
        {
            data: "subject",
            title: __('Subject'),
            render: function (data, type, row) {
                // Return name with link for display
                if (type === 'display') {
                    return '<a href="' + window.route('received-mail.show', {received_mail: row.id}) + '" title="' + __('Show details') + '">' + data + '</a>';
                }

                // Raw value is returned otherwise
                return data;
            },
        },
        {
            data: "processed",
            title: __('Processed'),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "handled",
            title: __('Handled'),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "transaction_id",
            title: __('Linked transaction'),
            render: function (data, _type, _row) {
                if (!data) {
                    return __('Not available');
                }

                return dataTableHelpers.dataTablesActionButton(data, 'quickView') +
                    dataTableHelpers.dataTablesActionButton(data, 'show');

            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return '<a href="' + window.route('received-mail.show', {received_mail: data}) + '" class="btn btn-xs btn-success" title="' + __('Show details') + '"><i class="fa fa-magnifying-glass"></i></a> ' +
                    (row.processed && !row.handled ? '<button class="btn btn-xs btn-primary finalizeIcon" data-id="' + data + '" type="button" title="' + __('Finalize transaction') + '"><i class="fa fa-fw fa-edit"></i></button> ' : '') +
                    '<button class="btn btn-xs btn-danger deleteIcon" data-id="' + data + '" type="button" title="' + __('Delete') + '"><i class="fa fa-fw fa-trash"></i></button> ';
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        // Mute the cell if no linked transaction is available
        if (!data.transaction_id) {
            $('td:eq(4)', row).addClass("text-muted text-italic");
        }
    },
    order: [
        [0, 'asc']
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete: function (settings) {
        // Listener for finalize button
        $(settings.nTable).on("click", "td > button.finalizeIcon:not(.busy)", function () {
            // Navigate to the edit page by submitting a form with the available data
            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));
            let form = document.createElement('form');
            form.setAttribute('method', 'POST');
            form.setAttribute('action', window.route('transactions.createFromDraft'));

            // Add csrf token
            let csrfInput = document.createElement('input');
            csrfInput.setAttribute('type', 'hidden');
            csrfInput.setAttribute('name', '_token');
            csrfInput.setAttribute('value', csrfToken);
            form.appendChild(csrfInput);

            // Get the transaction data as JSON from the global mails array and add it to the form
            let transactionData = window.mails.find(mail => mail.id === row.data().id);
            let input = document.createElement('input');
            input.setAttribute('type', 'hidden');
            input.setAttribute('name', 'transaction');
            input.setAttribute('value', JSON.stringify(transactionData.transaction_data));
            form.appendChild(input);

            // Pass the mail id to the form
            input = document.createElement('input');
            input.setAttribute('type', 'hidden');
            input.setAttribute('name', 'mail_id');
            input.setAttribute('value', transactionData.id);
            form.appendChild(input);

            document.body.appendChild(form);
            form.submit();
        });

        // Listener for delete button
        $(settings.nTable).on("click", "td > button.deleteIcon:not(.busy)", function () {
            // Confirm the action with the user
            if (!confirm(__('Are you sure to want to delete this item?'))) {
                return;
            }

            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).addClass('busy');
            $(this).children('i').removeClass().addClass('fa fa-fw fa-spinner fa-spin');

            // Send request to delete the email
            $.ajax({
                type: 'DELETE',
                url: window.route('api.received-mail.destroy', +row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.mails = window.mails.filter(mail => mail.id !== data.receivedMail.id);

                    row.remove().draw();
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Success'),
                            body: __('Email deleted'),
                            toastClass: 'bg-success',
                            delay: 2000,
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                error: function (_data) {
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Error'),
                            body: __('Error while trying to delete email'),
                            toastClass: 'bg-danger',
                        }
                    });
                    window.dispatchEvent(notificationEvent);
                }
            });
        });
    }
});

// Listeners for filters
$('input[name=table_filter_processed]').on("change", function () {
    table.column(2).search(this.value).draw();
});
$('input[name=table_filter_handled]').on("change", function () {
    table.column(3).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function () {
    table.search($(this).val()).draw();
})

// Transaction quick view listener
dataTableHelpers.initializeQuickViewButton(dataTableSelector);

// Initialize Vue for the quick view
import {createApp} from 'vue'

const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'

app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
