// Import external libraries
require ('datatables.net-bs5');
import 'datatables.net-responsive-bs5';

import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';
import * as helpers from './../helpers';

import 'select2';

// TODO: translate daterangpicker
// TODO: how to make this dynamic, loading only current language?
//import drpLangHu from 'vanillajs-datepicker/locales/hu';
//Object.assign(DateRangePicker.locales, drpLangHu);

// Import dataTable helper functions
import * as dataTableHelpers from './../components/dataTableHelper'

// Define selector constants
const elementAccountSelector = '#select_account';
const elementCategorySelectSelector = '#select_category';
const elementPayeeSelector = '#select_payee';
const elementTagSelector = '#select_tag';
const tableSelector = '#dataTable';

// Initialize an object which checks if preset filters are populated. This is used to trigger initial dataTable content.
let presetFilters = {
    ready: function() {
        for (let key in presetFilters) {
            if (presetFilters[key] === false) {
                return false;
            }
        }
        return true;
    }
};

// Loop filter object keys and populate presetFilters array.
for (let key in filters) {
    presetFilters[key] = false;
}

// Disable table refresh, if any filters are preset
if (!presetFilters.ready()) {
    document.getElementById('reload').setAttribute('disabled','disabled');
}

// Initialize date range picker
const dateRangePicker = new DateRangePicker(
    document.getElementById('dateRangePicker'),
    {
        allowOneSidedRange: true,
        weekStart: 1,
        todayBtn: true,
        todayBtnMode: 1,
        todayHighlight: true,
        language: window.YAFFA.language,
        format: 'yyyy-mm-dd',
        autohide: true,
        buttonClass: 'btn',
    }
);

window.table = $(tableSelector).DataTable({
    ajax:  {
        url: '/api/transactions',
        data: function(d) {
            const dates = dateRangePicker.getDates('yyyy-mm-dd');

            d.categories = $("#select_category").val() || undefined;
            d.payees = $("#select_payee").val() || undefined;
            d.accounts = $("#select_account").val() || undefined;
            d.tags = $("#select_tag").val() || undefined;
            d.date_from = dates[0];
            d.date_to = dates[1];
        },
        dataSrc: function (json) {
            return json.data.map(helpers.processTransaction);
        }
    },
    processing: true,
    language: {
        processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            title: __('Type'),
            defaultContent: '',
            render: function(_data, _type, row) {
                return dataTableHelpers.transactionTypeIcon(row.transaction_type.type, row.transaction_type.name);
            },
            className: "text-center",
        },
        {
            title: __('From'),
            defaultContent: '',
            data: 'config.account_from.name',
        },
        {
            title: __('To'),
            defaultContent: '',
            data: 'config.account_to.name',
        },
        dataTableHelpers.transactionColumnDefiniton.category,
        dataTableHelpers.transactionColumnDefiniton.amount,
        {
            title: __("Extra"),
            defaultContent: '',
            render: function (_data, type, row) {
                return dataTableHelpers.commentIcon(row.comment, type) + dataTableHelpers.tagIcon(row.tags, type);
            },
            className: "text-center",
            orderable: false,
        },
        {
            data: 'id',
            defaultContent: '',
            title: __("Actions"),
            render: function(data, _type, row) {
                return  dataTableHelpers.dataTablesActionButton(data, 'quickView') +
                        dataTableHelpers.dataTablesActionButton(data, 'show') +
                        dataTableHelpers.dataTablesActionButton(data, 'edit') +
                        dataTableHelpers.dataTablesActionButton(data, 'clone') +
                        dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, "asc"]
    ],
    responsive: true,
});

// Delete transaction icon functionality
dataTableHelpers.initializeAjaxDeleteButton(tableSelector);
dataTableHelpers.initializeQuickViewButton(tableSelector);

// Function to reload table data
function reloadTable() {
    document.getElementById('reload').setAttribute('disabled','disabled');
    table.ajax.reload(function() {
        document.getElementById('reload').removeAttribute('disabled');

        // (Re-)Initialize tooltips in table
        $('[data-toggle="tooltip"]').tooltip();
    });
}

// Reload button functionality
$("#reload").on('click', reloadTable);

$("#clear_dates").on('click', function() {
    dateRangePicker.setDates(
        {clear: true},
        {clear: true}
    );
})

$(".clear-select").on('click', function() {
    $("#" + $(this).data("target")).val(null).trigger('change');
})

// Set initial dates
if (filters.date_from || filters.date_to) {
    const start = (filters.date_from ? filters.date_from : {clear: true});
    const end = (filters.date_to ? filters.date_to : {clear: true});

    dateRangePicker.setDates(
        start,
        end
    );

    presetFilters.date_from = true;
    presetFilters.date_to = true;
    // If all preset filters are ready, reload table data
    if (presetFilters.ready()) {
        reloadTable();
    }
}

// Account filter select2 functionality
$(elementAccountSelector).select2({
    theme: "bootstrap-5",
    multiple: true,
    ajax: {
        url: '/api/assets/account',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            return {
                results: data.map(function(account) {
                    return {
                        id: account.id,
                        text: account.name,
                    }
                }),
            };
        },
        cache: true
    },
    placeholder: __("Select account"),
    allowClear: true
});

// Append preset accounts, if any
if (filters.accounts) {
    filters.accounts.forEach(function(account) {
        $.ajax({
            url:  '/api/assets/account/' + account,
            data: {}
        })
        .done(data => {
            $('#select_account')
            .append(new Option(data.name, data.id, true, true))
            .trigger('change')
            .trigger({
                type: 'select2:select',
                params: {
                    data: {
                        id: data.id,
                        name: data.name,
                    }
                }
            });

            // Set account filter to true
            presetFilters.accounts = true;

            // If all preset filters are ready, reload table data
            if (presetFilters.ready()) {
                reloadTable();
            }
        });
    });
}

// Payee select2 functionality
$(elementPayeeSelector).select2({
    theme: "bootstrap-5",
    multiple: true,
    ajax: {
        url: '/api/assets/payee',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            return {
                results: data.map(function(account) {
                    return {
                        id: account.id,
                        text: account.name,
                    }
                }),
            };
        },
        cache: true
    },
    selectOnClose: false,
    placeholder: __("Select payee"),
    allowClear: true
});

// Append preset payees, if any
if (filters.payees) {
    filters.payees.forEach(function(payee) {
        $.ajax({
            url:  '/api/assets/payee/' + payee,
            data: {},
            success: function(data) {
                $('#select_payee')
                .append(new Option(data.name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.name,
                        }
                    }
                });

                // Set payee filter to true
                presetFilters.payees = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

// Category select2 functionality
$(elementCategorySelectSelector).select2({
    theme: "bootstrap-5",
    multiple: true,
    ajax: {
        url: '/api/assets/category',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            return {
                results: data,
            };
        },
        cache: true
    },
    selectOnClose: false,
    placeholder: __("Select category"),
    allowClear: true
});

// Append preset categories, if any
if (filters.categories) {
    filters.categories.forEach(function(category) {
        $.ajax({
            url:  '/api/assets/category/' + category,
            data: {},
            success: function(data) {
                $('#select_category')
                .append(new Option(data.full_name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.full_name,
                        }
                    }
                });

                // Set category filter to true
                presetFilters.categories = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

// Tag select2 functionality
$(elementTagSelector).select2({
    theme: "bootstrap-5",
    multiple: true,
    ajax: {
        url:  '/api/assets/tag',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
                withInactive: true,
            };
        },
        processResults: function (data) {
            return {
                results: data
            };
        },
        cache: true
    },
    placeholder: __("Select tag(s)"),
    allowClear: true
});

// Append preset tag, if any
if (filters.tags) {
    filters.tags.forEach(function(tag) {
        $.ajax({
            url:  '/api/assets/tag/' + tag,
            data: {},
            success: function(data) {
                $('#select_tag')
                .append(new Option(data.name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.name,
                        }
                    }
                });

                // Set tag filter to true
                presetFilters.tags = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

// Attach event listener to all select2 elements for select and unselect events to update browser url, without reloading page.
let rebuildUrl = function () {
    let params = [];

    const dates = dateRangePicker.getDates('yyyy-mm-dd');
    // Date from
    if (dates[0]) {
        params.push('date_from=' + dates[0]);
    }

    // Date to
    if (dates[1]) {
        params.push('date_to=' + dates[1]);
    }

    // Accounts
    const accounts = $(elementAccountSelector).val().map((item) => 'accounts[]=' + item);
    params.push(...accounts);

    // Categories
    const categories = $(elementCategorySelectSelector).val().map((item) => 'categories[]=' + item);
    params.push(...categories);

    // Payees
    const payees = $(elementPayeeSelector).val().map((item) => 'payees[]=' + item);
    params.push(...payees);

    // Tags
    const tags = $(elementTagSelector).val().map((item) => 'tag[]=' + item);
    params.push(...tags);

    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));
}

$(elementAccountSelector).on('select2:select', rebuildUrl);
$(elementAccountSelector).on('select2:unselect', rebuildUrl);
$(elementCategorySelectSelector).on('select2:select', rebuildUrl);
$(elementCategorySelectSelector).on('select2:unselect', rebuildUrl);
$(elementPayeeSelector).on('select2:select', rebuildUrl);
$(elementPayeeSelector).on('select2:unselect', rebuildUrl);
$(elementTagSelector).on('select2:select', rebuildUrl);
$(elementTagSelector).on('select2:unselect', rebuildUrl);
document.getElementById('date_from').addEventListener('changeDate', rebuildUrl);
document.getElementById('date_to').addEventListener('changeDate', rebuildUrl);

import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
