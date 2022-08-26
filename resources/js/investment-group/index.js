require('datatables.net-bs');
import {
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

$(dataTableSelector).DataTable({
    data: investmentGroups,
    columns: [
        {
            data: "id",
            title: "ID"
        },
        {
            data: "name",
            title: "Name"
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'investment-group.edit') +
                        genericDataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],
    order: [[1, 'asc']]
});

initializeDeleteButtonListener(dataTableSelector, 'investment-group.destroy')
