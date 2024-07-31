require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import {
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from '../components/dataTableHelper';

import 'jstree';
import 'jstree/src/themes/default/style.css'

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: requisitions,
    columns: [
        {
            data: "institution_name",
            title: __("Bank")
        },
        {
            data: 'status_label',
            title: __("Status"),
        },
        {
            title: __('Accounts'),
            render: function (data, type, row) {
                return `${row.all_accounts_count} total / ${row.linked_accounts_count} linked`;
            }
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
                        </a> `;
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
    initComplete: function (settings) {},
});

initializeDeleteButtonListener(dataTableSelector, 'gocardless.destroy');

// Listeners for filters
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})

// Initialize the "tree" for the possible statuses
const selectorTreeContainer = '#requisition-status-tree-container';
let requistionStatuses = window.requistionStatuses || [];
// Convert the statuses to a format required by jstree
const treeData = requistionStatuses
    .map(status => {
        return {
            id:  status.id,
            parent: 0,
            text: status.label,
            state: {
                selected: false,
            },
        }
    });

// Artificially add a root node
treeData.push({
    id: 0,
    parent: '#',
    text: __('Status'),
    state: {
        selected: true,
        opened: true,
    }
});

$(selectorTreeContainer)
    .jstree({
        core: {
            data: treeData,
            themes: {
                dots: false,
                icons: false,
            },
        },
        plugins: ['checkbox'],
        checkbox: {
            keep_selected_style: false
        }
    })
    .on('select_node.jstree', filterStatus)
    .on('deselect_node.jstree', filterStatus);

function filterStatus() {
    const selectedNodes = $(selectorTreeContainer).jstree().get_checked(true);
    const selectedStatusNames = selectedNodes.map(node => '^' + node.text + '$');
    table.column(1).search(selectedStatusNames.join('|'), true, false).draw();
}
