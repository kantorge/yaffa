require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({

    data: tags,
    columns: [
        {
            data: "name",
            title: __("Name")
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center",
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
                                title="${__('Show transactions')}"
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
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
});

initializeDeleteButtonListener(dataTableSelector, 'tag.destroy');

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
