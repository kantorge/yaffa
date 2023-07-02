import 'datatables.net-bs5'
import 'datatables.net-responsive-bs5'

import * as dataTableHelpers from '../components/dataTableHelper';
import * as helpers from '../helpers';

const tableSelector = '#table';
window.table = $(tableSelector).DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/any',
        type: 'GET',
        dataSrc: function(data) {
            return data.transactions
                .map(helpers.processTransaction)
                .map(helpers.processScheduledTransaction);
        },
        deferRender: true
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('transaction_schedule.start_date', __('Start date'), window.YAFFA.locale),
        {
            data: "transaction_schedule.rule",
            title: __("Schedule settings"),
            render: function (data) {
                // Return human readable format of RRule
                // TODO: translation of rrule strings
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefiniton.iconFromBooleanField('schedule', __('Schedule')),
        dataTableHelpers.transactionColumnDefiniton.iconFromBooleanField('budget', __('Budget')),
        dataTableHelpers.transactionColumnDefiniton.iconFromBooleanField('transaction_schedule.active', __('Active')),
        {
            data: "transaction_type.type",
            title: __("Type"),
            render: function (data, type) {
                if (type === 'filter') {
                    // TODO: this should be translated
                    return  data;
                }
                return (  data === 'standard'
                        ? '<i class="fa fa-money-bill text-primary" title="' + __('Standard') + '"></i>'
                        : '<i class="fa fa-line-chart text-primary" title="' + __('Investment') + '"></i>');
            },
            className: "text-center",
        },
        dataTableHelpers.transactionColumnDefiniton.payee,
        dataTableHelpers.transactionColumnDefiniton.category,
        dataTableHelpers.transactionColumnDefiniton.amount,
        {
            data: 'comment',
            title: __("Comment"),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.commentIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "tags",
            title: __("Tags"),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.tagIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: 'id',
            title: __("Actions"),
            defaultContent: '',
            render: function (data, _type, row) {
                return  dataTableHelpers.dataTablesActionButton(data, 'edit') +
                        dataTableHelpers.dataTablesActionButton(data, 'clone') +
                        dataTableHelpers.dataTablesActionButton(data, 'replace') +
                        dataTableHelpers.dataTablesActionButton(data, 'delete') +
                        (row.schedule && row.transaction_schedule.active
                            ? dataTableHelpers.dataTablesActionButton(data, 'enter') +
                              dataTableHelpers.dataTablesActionButton(data, 'skip_reload')
                            : '');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        $(row).attr('data-id', data.id);

        if (!data.transaction_schedule.next_date) {
            return;
        }

        if (data.transaction_schedule.next_date  < new Date(new Date().setHours(0,0,0,0)) ) {
            $(row).addClass('danger');
        } else if (data.transaction_schedule.next_date  < new Date(new Date().setHours(24,0,0,0)) ) {
            $(row).addClass('warning');
        }
    },
    initComplete: function (_settings, _json) {
        $('[data-toggle="tooltip"]').tooltip();
    },
    order: [
        // Start date is the first column
        [ 0, "asc" ]
    ],
    responsive: true,
    deferRender:    true,
    scrollY:        '500px',
    scrollCollapse: true,
    scroller:       true,
    stateSave:      false,
    processing:     true,
    paging:         false,
});

dataTableHelpers.initializeSkipInstanceButton(tableSelector);
dataTableHelpers.initializeAjaxDeleteButton(tableSelector);

// Listeners for button filters
$('input[name=schedule]').on("change", function() {
    table.column(3).search(this.value).draw();
});

$('input[name=budget]').on("change", function() {
    table.column(4).search(this.value).draw();
});

$('input[name=active]').on("change", function() {
    table.column(5).search(this.value).draw();
});
