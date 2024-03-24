import {renderDeleteAssetButton} from "../components/dataTableHelper";

require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from './../components/dataTableHelper';
import {toFormattedCurrency} from '../helpers';
import 'jstree';
import 'jstree/src/themes/default/style.css'


/**
 * Define the conditions for the delete button, as required by the DataTables helper.
 */
const deleteButtonConditions = [
    {
        property: 'transactions_count',
        value: 0,
        negate: false,
        errorMessage: __('It is already used in transactions.'),
    },
];


let table = $('#investmentSummary').DataTable({
    data: window.investments,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, _type, row) {
                return `<a href="${window.route('investment.show', row.id)}" title="${__('View investment details')}">${data}</a>`;
            },
            type: "html",
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "investment_group.name",
            title: __("Group"),
            type: "string"
        },
        {
            data: "symbol",
            title: __("Symbol"),
        },
        {
            data: "isin",
            title: __("ISIN number"),
        },
        {
            data: "quantity",
            title: __("Quantity"),
            render: function (data, type) {
                if (type === 'display') {
                    return data.toLocaleString(window.YAFFA.locale, {maximumFractionDigits: 2, useGrouping: true});
                }
                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            data: "price",
            title: __("Latest price"),
            render: function (data, type, row) {
                if (type === 'display' && !isNaN(data) && typeof data === "number") {
                    return toFormattedCurrency(data, window.YAFFA.locale, row.currency);
                }

                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            defaultContent: "",
            title: __("Value"),
            render: function (_data, type, row) {
                const value = row.quantity * row.price;

                if (type === 'display') {
                    return toFormattedCurrency(value, window.YAFFA.locale, row.currency);
                }

                return value;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return '<a href="' + route('investment.show', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="' + __('View investment details') + '"></i></a> ' +
                    '<a href="' + route('investment-price.list', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-dollar" title="' + __('View investment price list') + '"></i></a> ' +
                    dataTableHelpers.genericDataTablesActionButton(data, 'edit', 'investment.edit') +
                    renderDeleteAssetButton(row, deleteButtonConditions, __("This investment cannot be deleted."));
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
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change investment active state
            $.ajax({
                type: 'PUT',
                url: '/api/assets/investment/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.investments.filter(investment => investment.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert(__('Error changing investment active state'));
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });

        // Listener for delete button
        $(settings.nTable).on("click", "td > button.deleteIcon:not(.busy)", function () {
            // Confirm the action with the user
            if (!confirm(__('Are you sure to want to delete this item?'))) {
                return;
            }

            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            let element = $(this);
            element.addClass('busy');

            // Send request to change investment active state
            $.ajax({
                type: 'DELETE',
                url: window.route('api.investment.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.investments = window.investments.filter(investment => investment.id !== data.investment.id);

                    // Remove row from table
                    $(settings.nTable).DataTable().row($(this).parents('tr')).remove().draw();

                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Success'),
                            body: __('Investment deleted'),
                            toastClass: 'bg-success',
                        }
                    });
                    window.dispatchEvent(notificationEvent);
                },
                error: function (_data) {
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Error'),
                            body: __('Error while trying to delete investment'),
                            toastClass: 'bg-danger',
                        }
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function (_data) {
                    // Restore button icon
                    element.removeClass('busy');
                }
            });
        });
    },
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});

// Initialize the "tree" for the investment group filter list
const selectorTreeContainer = '#investment-group-tree-container';
let investmentGroups = window.investmentGroups || [];
// Convert the investment groups to the format required by jstree
const treeData = investmentGroups
    .map(group => {
        return {
            id:  group.id,
            parent: 0,
            text: group.name,
            state: {
                selected: false,
            },
        };
    })
    .sort((a, b) => a.text.localeCompare(b.text));

// Artificially add a root node
treeData.push({
    id: 0,
    parent: '#',
    text: __('Investment groups'),
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
    .on('select_node.jstree', filterInvestmentGroup)
    .on('deselect_node.jstree', filterInvestmentGroup);

// Listeners for filters
function filterInvestmentGroup() {
    const selectedNodes = $(selectorTreeContainer).jstree().get_checked(true);
    const selectedInvestmentGroupNames = selectedNodes.map(node => '^' + node.text + '$');
    table.column(2).search(selectedInvestmentGroupNames.join('|'), true, false).draw();
}
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
