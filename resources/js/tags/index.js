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
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'tag.edit') +
                        genericDataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [[0, 'asc']]
});

initializeDeleteButtonListener(dataTableSelector, 'tag.destroy');

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
