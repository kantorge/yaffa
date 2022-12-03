require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import {
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: accountGroups,
    columns: [
        {
            data: "name",
            title: __("Name")
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'account-group.edit') +
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
    responsive: true,
});

initializeDeleteButtonListener(dataTableSelector, 'account-group.destroy');
