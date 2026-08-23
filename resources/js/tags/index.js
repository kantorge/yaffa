import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import {
    booleanToTableIcon,
    genericDataTablesActionButton
} from '@/shared/lib/datatable';
import { getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import { confirmDelete } from '@/shared/lib/confirm';
import * as toastHelpers from '@/shared/lib/toast';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,

    data: tags,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, type, row) {
                if (type === 'display') {
                    return `<a href="${route('reports.transactions', {tags: [row.id]})}" title="${__('View associated transactions')}">${data}</a>`;
                }
                return data;
            }
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
            data: "transaction_count",
            title: __("Transactions"),
            className: "text-center",
            type: "num",
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  `<a
                                class="btn btn-xs btn-success"
                                href="${route('reports.transactions', {tags: [data]})}"
                                title="${__('View associated transactions')}"
                        >
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </a> ` +
                        genericDataTablesActionButton(data, 'edit', 'tags.edit') +
                        genericDataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc']
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change tag active state
            $.ajax({
                type: 'PATCH',
                url: window.route('api.v1.tags.patch-active', row.data().id),
                data: JSON.stringify({
                    "_token": csrfToken,
                    "active": !row.data().active,
                }),
                contentType: 'application/json',
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.tags.filter(tag => tag.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert(__('Error changing tag active state'));
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    },
});

$(dataTableSelector).on('click', '.data-delete:not(.busy)', function () {
    const button = $(this);
    const id = Number(button.data('id'));

    confirmDelete(__('Are you sure you want to delete this item?')).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        button.addClass('busy');

        axios.delete(window.route('api.v1.tags.destroy', id))
            .then(function () {
                window.tags = window.tags.filter((tag) => tag.id !== id);

                table.row(function (_idx, data) {
                    return data.id === id;
                }).remove().draw();

                toastHelpers.showSuccessToast(__('Tag deleted'));
            })
            .catch(function (error) {
                toastHelpers.showErrorToast(error.response?.data?.error || __('Error while trying to delete tag'));
            })
            .finally(function () {
                button.removeClass('busy');
            });
    });
});

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
