require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from '../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({

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
                        genericDataTablesActionButton(data, 'edit', 'tag.edit') +
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
                type: 'PUT',
                url: '/api/assets/tag/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
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

initializeDeleteButtonListener(dataTableSelector, 'tag.destroy');

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
