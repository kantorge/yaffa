require('datatables.net-bs');
import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

$(dataTableSelector).DataTable({

    data: tags,
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
            data: "active",
            title: "Active",
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'tag.edit') +
                        genericDataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],
    order: [[1, 'asc']]
});

initializeDeleteButtonListener(dataTableSelector, 'tag.destroy');
