import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-select-bs5';
import 'datatables-contextual-actions';
import { createApp } from 'vue';
import PayeeForm from './components/PayeeForm.vue';
import Swal from 'sweetalert2';

import { booleanToTableIcon } from '@/shared/lib/datatable';
import { escapeHtml, escapeHtmlWithLineBreaks } from '@/shared/lib/helpers';
import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';

import * as toastHelpers from '@/shared/lib/toast';

const dataTableSelector = '#table';
let ajaxIsBusy = false;

function toNumericId(value) {
    const parsedValue = Number(value);

    return Number.isNaN(parsedValue) ? null : parsedValue;
}

function toDateOrNull(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const parsedValue = Date.parse(value);

    return Number.isNaN(parsedValue) ? null : new Date(parsedValue);
}

function normalizePayee(payee) {
    const fromCount = Number(payee.from_count || 0);
    const toCount = Number(payee.to_count || 0);

    const fromMinDate = toDateOrNull(payee.from_min_date);
    const fromMaxDate = toDateOrNull(payee.from_max_date);
    const toMinDate = toDateOrNull(payee.to_min_date);
    const toMaxDate = toDateOrNull(payee.to_max_date);

    const transactionsMinDate = fromMinDate && toMinDate
        ? new Date(Math.min(fromMinDate, toMinDate))
        : fromMinDate || toMinDate;
    const transactionsMaxDate = fromMaxDate && toMaxDate
        ? new Date(Math.max(fromMaxDate, toMaxDate))
        : fromMaxDate || toMaxDate;

    const hasDefaultCategory = Boolean(payee?.config?.category_id || payee?.config?.category?.id);
    const hasCategorySuggestion = !hasDefaultCategory && Boolean(payee.category_suggestion);

    return {
        ...payee,
        config: payee.config || {
            category: null,
            category_id: null,
        },
        category_suggestion: hasDefaultCategory ? null : (payee.category_suggestion || null),
        has_default_category: hasDefaultCategory,
        has_category_suggestion: hasCategorySuggestion,
        from_count: fromCount,
        to_count: toCount,
        transactions_count: fromCount + toCount,
        from_min_date: fromMinDate,
        from_max_date: fromMaxDate,
        to_min_date: toMinDate,
        to_max_date: toMaxDate,
        transactions_min_date: transactionsMinDate,
        transactions_max_date: transactionsMaxDate,
    };
}

function getRowFromEvent(settings, element) {
    const table = $(settings.nTable).DataTable();
    let rowElement = $(element).closest('tr');

    if (rowElement.hasClass('child')) {
        rowElement = rowElement.prev();
    }

    return table.row(rowElement);
}

// Initialize Vue app
const vueApp = createApp({
    components: {
        PayeeForm,
    },
    methods: {
        onPayeeCreated(payee) {
            const payeeId = toNumericId(payee.id);

            const existingIndex = window.payees.findIndex(
                (item) => toNumericId(item.id) === payeeId,
            );
            if (existingIndex !== -1) {
                const normalizedPayee = normalizePayee({
                    ...window.payees[existingIndex],
                    ...payee,
                });

                window.payees[existingIndex] = normalizedPayee;

                const row = window.table.row(
                    (_, data) => toNumericId(data.id) === payeeId,
                );
                if (row.any()) {
                    row.data(normalizedPayee).draw(false);
                }

                const filtersWereReset = this.focusPayeeInTable(payeeId, payee.name);
                if (filtersWereReset) {
                    toastHelpers.showInfoToast(__('Existing payee selected. Filters were reset and the row is highlighted.'));
                } else {
                    toastHelpers.showInfoToast(__('Existing payee selected and highlighted in the list.'));
                }

                return;
            }

            const normalizedPayee = normalizePayee({
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
                category_suggestion: null,
            });

            window.payees.push(normalizedPayee);
            window.table.row.add(normalizedPayee).draw(false);

            const filtersWereReset = this.focusPayeeInTable(payeeId);

            toastHelpers.showSuccessToast(
                filtersWereReset
                    ? __('Payee added. Filters were reset and the new row is highlighted.')
                    : __('Payee added'),
            );
        },
        onPayeeUpdated(payee) {
            const payeeId = toNumericId(payee.id);
            const index = window.payees.findIndex(
                (item) => toNumericId(item.id) === payeeId,
            );
            if (index !== -1) {
                const mergedPayee = {
                    ...window.payees[index],
                    ...payee,
                };
                const normalizedPayee = normalizePayee(mergedPayee);

                window.payees[index] = normalizedPayee;

                const row = window.table.row(
                    (_, data) => toNumericId(data.id) === payeeId,
                );
                if (row.any()) {
                    row.data(normalizedPayee).draw(false);
                }
            }

            toastHelpers.showSuccessToast(__('Payee updated'));
        },
        onPayeeSuggestionAccepted(payeeId, categorySuggestion) {
            const normalizedPayeeId = toNumericId(payeeId);
            const index = window.payees.findIndex(
                (item) => toNumericId(item.id) === normalizedPayeeId,
            );
            if (index === -1) {
                return;
            }

            const updatedPayee = {
                ...window.payees[index],
                config: {
                    ...(window.payees[index].config || {}),
                    category_id: categorySuggestion.max_category_id,
                    category: {
                        id: categorySuggestion.max_category_id,
                        full_name: categorySuggestion.category,
                    },
                },
                category_suggestion: null,
            };

            const normalizedPayee = normalizePayee(updatedPayee);
            window.payees[index] = normalizedPayee;

            const row = window.table.row(
                (_, data) => toNumericId(data.id) === normalizedPayeeId,
            );
            if (row.any()) {
                row.data(normalizedPayee).draw(false);
            }

            toastHelpers.showSuccessToast(__('Default category updated'));
        },
        acceptCategorySuggestion(payee, buttonElement) {
            if (!payee.category_suggestion) {
                return;
            }

            const button = $(buttonElement);
            button.addClass('busy').prop('disabled', true);
            button.find('.fa-check').addClass('d-none');
            button.find('.fa-spinner').removeClass('d-none');

            window.axios
                .post(
                    window.route('api.v1.payees.category-suggestions.accept', {
                        accountEntity: payee.id,
                        category: payee.category_suggestion.max_category_id,
                    }),
                )
                .then(() => {
                    this.onPayeeSuggestionAccepted(payee.id, payee.category_suggestion);
                })
                .catch(() => {
                    toastHelpers.showErrorToast(__('Error while updating default category'));
                })
                .finally(() => {
                    button.find('.fa-spinner').addClass('d-none');
                    button.find('.fa-check').removeClass('d-none');
                    button.removeClass('busy').prop('disabled', false);
                });
        },
        resetPayeeTableFilters() {
            $('input[name=table_filter_active][value=""]').prop('checked', true);
            $('input[name=table_filter_default_category][value=""]').prop('checked', true);
            $('input[name=table_filter_category_suggestion][value=""]').prop('checked', true);
            $('#table_filter_search_text').val('');

            window.table.column(1).search('');
            window.table.column(7).search('');
            window.table.column(8).search('');
            window.table.search('');
            window.table.draw(false);
        },
        focusPayeeInTable(payeeId, payeeName = null) {
            const normalizedPayeeId = toNumericId(payeeId);
            if (normalizedPayeeId === null) {
                return false;
            }

            const isVisibleInFilteredRows = window.table
                .rows({ search: 'applied' })
                .data()
                .toArray()
                .some((row) => toNumericId(row.id) === normalizedPayeeId);

            let filtersWereReset = false;

            if (!isVisibleInFilteredRows) {
                this.resetPayeeTableFilters();

                if (payeeName) {
                    $('#table_filter_search_text').val(payeeName);
                    window.table.search(payeeName).draw(false);
                }

                filtersWereReset = true;
            }

            setTimeout(() => {
                const row = window.table.row(
                    (_, data) => toNumericId(data.id) === normalizedPayeeId,
                );
                if (!row.any()) {
                    return;
                }

                const rowNode = row.node();
                if (!rowNode) {
                    return;
                }

                const scrollBody = $(window.table.table().container()).find('.dt-scroll-body');
                if (scrollBody.length > 0) {
                    const rowPosition = $(rowNode).position();
                    if (rowPosition) {
                        const targetScrollTop = scrollBody.scrollTop() + rowPosition.top - (scrollBody.height() / 2);
                        scrollBody.stop(true).animate({ scrollTop: targetScrollTop }, 200);
                    }
                } else {
                    rowNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }

                $(rowNode).addClass('table-warning');
                setTimeout(() => {
                    $(rowNode).removeClass('table-warning');
                }, 5000);
            }, 50);

            return filtersWereReset;
        },
        deletePayee(payee) {
            if (payee.transactions_count > 0 || ajaxIsBusy) {
                return;
            }

            ajaxIsBusy = true;

            Swal.fire({
                animation: false,
                text: __('Are you sure to want to delete this item?'),
                icon: 'warning',
                showCancelButton: true,
                cancelButtonText: __('Cancel'),
                confirmButtonText: __('Confirm'),
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-outline-secondary ms-3',
                },
            }).then((result) => {
                if (!result.isConfirmed) {
                    ajaxIsBusy = false;
                    return;
                }

                window.axios
                    .delete(window.route('api.v1.account-entities.destroy', payee.id))
                    .then((response) => {
                        const deletedPayeeId = response.data.accountEntity.id;

                        window.payees = window.payees.filter((item) => item.id !== deletedPayeeId);

                        window.table
                            .row((_, data) => data.id === deletedPayeeId)
                            .remove()
                            .draw(false);

                        toastHelpers.showSuccessToast(__('Payee deleted'));
                    })
                    .catch(() => {
                        toastHelpers.showErrorToast(__('Error while trying to delete payee'));
                    })
                    .finally(() => {
                        ajaxIsBusy = false;
                    });
            });
        },
        openMergeForm(payeeId) {
            window.location.href = window.route('payees.merge.form', {
                payeeSource: payeeId,
            });
        },
        openTransactions(payeeId) {
            window.location.href = window.route('reports.transactions', {
                payees: [payeeId],
            });
        },
        getDefaultCategoryCellContent(category, row, type) {
            const defaultCategoryName = category?.full_name || null;
            const suggestion = row.category_suggestion;
            const notSetLabel = __('Not set');

            if (type !== 'display') {
                return defaultCategoryName || suggestion?.category || notSetLabel;
            }

            if (defaultCategoryName) {
                return escapeHtml(defaultCategoryName);
            }

            if (suggestion) {
                return `
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-info" title="${escapeHtml(__('Suggested category'))}">💡 ${escapeHtml(suggestion.category)}</span>
                        <button
                            class="btn btn-xs btn-success accept-payee-category-suggestion"
                            data-payee-id="${escapeHtml(row.id)}"
                            type="button"
                            title="${escapeHtml(__('Accept suggestion'))}"
                        >
                            <i class="fa fa-fw fa-spinner fa-spin d-none"></i>
                            <i class="fa fa-fw fa-check"></i>
                        </button>
                    </div>`;
            }

            return escapeHtml(notSetLabel);
        },
        showNewPayeeModal() {
            this.$refs.payeeFormNew.show();
        },
        showEditPayeeModal(payeeId) {
            this.$refs.payeeFormEdit.show(payeeId);
        },
    },
});

const app = vueApp.mount('#payeeIndex');

// Prepare payee data for datatable
window.payees = window.payees.map((payee) => normalizePayee(payee));

window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: window.payees,
    columns: [
        {
            data: 'name',
            title: __('Name'),
            render: function (data, type) {
                if (type === 'display') {
                    return `<div class="d-flex justify-content-start align-items-center">
                        <i class="hover-icon me-2 fa-fw fa-solid fa-ellipsis-vertical"></i>
                        <span>${escapeHtml(data)}</span>
                    </div>`;
                }

                return data;
            },
        },
        {
            data: 'active',
            title: __('Active'),
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: 'text-center activeIcon',
        },
        {
            data: 'config.category',
            title: __('Default category'),
            render: function (data, type, row) {
                return app.getDefaultCategoryCellContent(data, row, type);
            },
            type: 'html',
        },
        {
            data: 'transactions_count',
            title: __('Transactions'),
            render: function (data, type, row) {
                if (type === 'display') {
                    if (data > 0) {
                        const formattedCount = data.toLocaleString(window.YAFFA.userSettings.locale, {
                            maximumFractionDigits: 0,
                            useGrouping: true,
                        });

                        return `<a href="${escapeHtml(window.route('reports.transactions', { payees: [row.id] }))}" title="${escapeHtml(__('Show transactions'))}">${escapeHtml(formattedCount)}</a>`;
                    }

                    return __('Never used');
                }

                return data;
            },
            type: 'num',
        },
        {
            data: 'transactions_min_date',
            title: __('First transaction'),
            render: function (data, type) {
                if (type === 'display') {
                    return data
                        ? data.toLocaleDateString(window.YAFFA.userSettings.locale)
                        : __('Never used');
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: 'transactions_max_date',
            title: __('Last transaction'),
            render: function (data, type) {
                if (type === 'display') {
                    return data
                        ? data.toLocaleDateString(window.YAFFA.userSettings.locale)
                        : __('Never used');
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: 'alias',
            title: __('Import alias'),
            render: function (data, type) {
                if (type === 'display') {
                    return data ? escapeHtmlWithLineBreaks(data) : escapeHtml(__('Not set'));
                }

                return data;
            },
        },
        {
            data: 'has_default_category',
            title: __('Has default category'),
            visible: false,
            render: function (data, type) {
                if (type === 'filter') {
                    return data ? __('Yes') : __('No');
                }

                return data ? 1 : 0;
            },
            searchable: true,
        },
        {
            data: 'has_category_suggestion',
            title: __('Has category suggestion'),
            visible: false,
            render: function (data, type) {
                if (type === 'filter') {
                    return data ? __('Yes') : __('No');
                }

                return data ? 1 : 0;
            },
            searchable: true,
        },
    ],
    createdRow: function (row, data) {
        if (!data.config?.category && !data.category_suggestion) {
            $('td:eq(2)', row).addClass('text-muted text-italic');
        }
        if (data.transactions_count === 0) {
            $('td:eq(3)', row).addClass('text-muted text-italic');
        }
        if (!data.transactions_min_date) {
            $('td:eq(4)', row).addClass('text-muted text-italic');
        }
        if (!data.transactions_max_date) {
            $('td:eq(5)', row).addClass('text-muted text-italic');
        }
        if (!data.alias) {
            $('td:eq(6)', row).addClass('text-muted text-italic');
        }
    },
    order: [[0, 'asc']],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    select: {
        select: true,
        info: false,
        style: 'os',
    },
    initComplete: function (settings) {
        $(settings.nTable).on('click', 'td.activeIcon > i', function () {
            const row = getRowFromEvent(settings, this);

            // Do not request change if previous request is still in progress
            if ($(this).hasClass('fa-spinner')) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change payee active state
            $.ajax({
                type: 'PATCH',
                url: window.route('api.v1.account-entities.patch-active', {
                    accountEntity: row.data().id,
                }),
                data: JSON.stringify({
                    _token: csrfToken,
                    active: !row.data().active,
                }),
                contentType: 'application/json',
                success: function (data) {
                    const normalizedPayeeId = toNumericId(data.id);
                    const payee = window.payees.find(
                        (item) => toNumericId(item.id) === normalizedPayeeId,
                    );
                    if (payee) {
                        payee.active = data.active;
                    }
                },
                error: function () {
                    toastHelpers.showErrorToast(__('Error while changing payee active state'));
                },
                complete: function () {
                    row.invalidate().draw(false);
                },
            });
        });

        $(settings.nTable).on('click', 'button.accept-payee-category-suggestion:not(.busy)', function () {
            const row = getRowFromEvent(settings, this);
            const payee = row.data();

            if (!payee) {
                return;
            }

            app.acceptCategorySuggestion(payee, this);
        });
    },
});

window.table.contextualActions({
    contextMenuClasses: ['text-primary'],
    deselectAfterAction: true,
    contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: function (selectedRows) {
            const rowData = selectedRows[0];
            return escapeHtml(rowData.name);
        },
        triggerButtonSelector: '.hover-icon',
    },
    buttonList: {
        enabled: false,
    },
    items: [
        {
            type: 'option',
            title: __('Edit'),
            iconClass: 'fa fa-edit',
            contextMenuClasses: ['text-primary'],
            action: function (selectedRows) {
                app.showEditPayeeModal(selectedRows[0].id);
            },
        },
        {
            type: 'option',
            title: __('Show transactions'),
            iconClass: 'fa fa-list',
            contextMenuClasses: ['text-info'],
            action: function (selectedRows) {
                app.openTransactions(selectedRows[0].id);
            },
        },
        {
            type: 'option',
            title: __('Merge into an other payee'),
            contextMenuClasses: ['text-primary'],
            iconClass: 'fa fa-random',
            action: function (selectedRows) {
                app.openMergeForm(selectedRows[0].id);
            },
        },
        {
            type: 'divider',
        },
        {
            type: 'option',
            title: __('Delete'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger'],
            isDisabled: function (row) {
                return ajaxIsBusy;
            },
            isHidden: function (row) {
                return row.transactions_count > 0;
            },
            action: function (selectedRows) {
                app.deletePayee(selectedRows[0]);
            },
        },
        {
            type: 'option',
            title: __('Cannot be deleted, already in use'),
            iconClass: 'fa fa-fw fa-info-circle',
            contextMenuClasses: ['text-muted'],
            isHidden: function (row) {
                return row.transactions_count === 0;
            } ,
            action: function () {
                // No action, just info
            }
        }
    ],
});

// Listeners for filters
$('input[name=table_filter_active]').on('change', function () {
    window.table.column(1).search(this.value).draw();
});

$('input[name=table_filter_default_category]').on('change', function () {
    window.table.column(7).search(this.value).draw();
});

$('input[name=table_filter_category_suggestion]').on('change', function () {
    window.table.column(8).search(this.value).draw();
});

$('#table_filter_search_text').on('input', function () {
    window.table.search($(this).val()).draw();
});

// Listener for new payee button
$('#button-new-payee').on('click', function () {
    app.showNewPayeeModal();
});
