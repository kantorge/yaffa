require('datatables.net-bs');
import {
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

$(dataTableSelector).DataTable({
    data: accountGroups,
    columns: [
        {
            data: "id",
            title: "Id"
        },
        {
            data: "name",
            title: "Name"
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'account-group.edit') +
                        genericDataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],
    order: [
        [1, 'asc']
    ]
});

initializeDeleteButtonListener(dataTableSelector, 'account-group.destroy');
