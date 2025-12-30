import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import * as dataTableHelpers from './../components/dataTableHelper';
import { toFormattedCurrency } from '../helpers';


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
    data: [],
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
                if (data === null || data === undefined) {
                    return type === 'display' ? '—' : 0;
                }
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
                if (data === null || data === undefined) {
                    return type === 'display' ? '—' : 0;
                }
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
                if (row.quantity === null || row.quantity === undefined || row.price === null || row.price === undefined) {
                    return type === 'display' ? '—' : 0;
                }
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
                    dataTableHelpers.renderDeleteAssetButton(row, deleteButtonConditions, __("This investment cannot be deleted."));
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
                    // Update row in table data source
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
    stateSave: false,
    processing: true,
    paging: false,
});

// Initialize window.investments array
window.investments = [];

// Load investments based on active filter and selected groups
function loadInvestments(activeOnly = true) {
    table.clear();
    table.processing(true);
    
    const params = {};
    if (activeOnly === true) {
        params.active = 1;
    }
    
    $.ajax({
        url: '/api/investments',
        type: 'GET',
        data: params,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        dataType: 'json',
        success: function(data) {
            console.log('Loaded investments:', data.length);
            window.investments = data;
            table.rows.add(data).draw();
            // Apply group filter after loading
            filterInvestmentGroup();
        },
        error: function(xhr, status, error) {
            console.error('Failed to load investments:', {xhr, status, error});
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Error'),
                    body: __('Failed to load investments: ') + (xhr.responseJSON?.message || error),
                    toastClass: 'bg-danger',
                }
            });
            window.dispatchEvent(notificationEvent);
        },
        complete: function() {
            table.processing(false);
        }
    });
}

// Don't load anything on page load - wait for user to select investment groups

// Initialize the "tree" for the investment group filter list
const selectorTreeContainer = '#investment-group-tree-container';
dataTableHelpers.investmentGroupTree(
    selectorTreeContainer,
    window.investmentGroups,
    filterInvestmentGroup
);

// Listeners for filters
function filterInvestmentGroup() {
    const selectedNodes = $(selectorTreeContainer).jstree().get_checked(true);
    
    // If no investments loaded yet and groups are selected, load them
    if (window.investments.length === 0 && selectedNodes.length > 0) {
        // Determine active filter state
        const activeFilter = $('input[name=table_filter_active]:checked').val();
        // Active filter: "Yes" = active only, "" (Any) = all, "No" = inactive only
        const activeOnly = activeFilter === __('Yes') ? true : activeFilter === '' ? false : false;
        loadInvestments(activeOnly);
        return;
    }
    
    // Filter the loaded investments by selected groups
    const selectedInvestmentGroupNames = selectedNodes.map(node => '^' + node.text + '$');
    table.column(2).search(selectedInvestmentGroupNames.join('|'), true, false).draw();
}
$('input[name=table_filter_active]').on("change", function() {
    const filterValue = this.value;
    
    // Only reload if data is already loaded
    if (window.investments.length > 0) {
        // filterValue: "Yes" = active only, "" (Any) = all, "No" = inactive only
        const activeOnly = filterValue === __('Yes') ? true : false;
        loadInvestments(activeOnly);
    }
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
document.getElementById('table_filter_search_text_clear').addEventListener('click', function() {
    document.getElementById('table_filter_search_text').value = '';
    table.search($(this).val()).draw() ;
});
