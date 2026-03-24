import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    renderDeleteAssetButton,
} from '@/shared/lib/datatable';

import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import { escapeHtml, initializeBootstrapTooltips } from '@/shared/lib/helpers';
import * as toastHelpers from '@/shared/lib/toast';

const dataTableSelector = '#table';

function recalculateChildrenCounts(categories) {
    const childrenCountByParentId = {};

    categories.forEach(function (category) {
        if (!category.parent || !category.parent.id) {
            return;
        }

        if (!childrenCountByParentId[category.parent.id]) {
            childrenCountByParentId[category.parent.id] = 0;
        }

        childrenCountByParentId[category.parent.id]++;
    });

    categories.forEach(function (category) {
        category.children_count = childrenCountByParentId[category.id] || 0;
    });
}

// Loop categories and prepare data for datatable
window.categories = window.categories.map(function(category) {
    // Parse first date if it exists
    if (category.transactions_min_date) {
        category.transactions_min_date = new Date(category.transactions_min_date);
    }
    // Parse last date if it exists
    if (category.transactions_max_date) {
        category.transactions_max_date = new Date(category.transactions_max_date);
    }

    return category;
});

/**
 * Define the conditions for the delete button, as required by the DataTables helper.
 */
const deleteButtonConditions = [
    {
        property: 'transactions_count_total',
        value: 0,
        negate: false,
        errorMessage: __('It is already used in transactions.'),
    },
    {
        property: 'children_count',
        value: 0,
        negate: false,
        errorMessage: __('It has subcategories assigned.'),
    },
    {
        property: 'payees_defaulting_count',
        value: 0,
        negate: false,
        errorMessage: __('It is used as default category by some payees.'),
    },
    {
        property: 'payees_preferring_count',
        value: 0,
        negate: false,
        errorMessage: __('It is used as preferred category by some payees.'),
    },
    {
        property: 'payees_not_preferring_count',
        value: 0,
        negate: false,
        errorMessage: __('It is used as not preferred category by some payees.'),
    }
];

window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: window.categories,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function(data, type, row) {
                if (type !== 'display') {
                    return data;
                }

                if (!row.description) {
                    return escapeHtml(data);
                }

                return `${escapeHtml(data)} <i class="fa fa-info-circle text-muted ms-1" data-coreui-toggle="tooltip" data-coreui-placement="top" data-coreui-trigger="hover focus" title="${escapeHtml(row.description)}"></i>`;
            },
        },
        {
            data: "parent",
            title: __("Parent category"),
            render: function (data, type) {
                if (type === 'filter') {
                    return (data ? '_child_' + data.name : '_parent_');
                }
                return data ? data.name : __('Not set');
            },
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
            // Display count of associated transactions
            data: "transactions_count",
            title: __("Transactions"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data > 0 ? data : __('Never used'));
                }
                return data;
            },
            type: 'num',
        },
        {
            // Display first transaction date
            data: "transactions_min_date",
            title: __("First transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.userSettings.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            // Display last transaction date
            data: "transactions_max_date",
            title: __("Last transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.userSettings.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return  genericDataTablesActionButton(data, 'edit', 'categories.edit') +
                        renderDeleteAssetButton(row, deleteButtonConditions, __("This category cannot be deleted.")) +
                        '<a href="' + route('categories.merge.form', { categorySource: data }) + '" class="btn btn-xs btn-primary" title="' + __('Merge into an other category') + '"><i class="fa fa-random"></i></a> ';
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function(row, data) {
        if (!data.parent) {
            $('td:eq(1)', row).addClass("text-muted text-italic");
        }
        if (data.transactions_count === 0) {
            $('td:eq(3)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_min_date) {
            $('td:eq(4)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_max_date) {
            $('td:eq(5)', row).addClass("text-muted text-italic");
        }
    },
    order: [
        [0, 'asc']
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    drawCallback: function () {
        initializeBootstrapTooltips(document.querySelector(dataTableSelector));
    },
    initComplete : function(settings) {
        initializeBootstrapTooltips(document.querySelector(dataTableSelector));

        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            var row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

            // Do not request change if previous request is still in progress
            if ($(this).hasClass("fa-spinner")) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change account active state
            $.ajax ({
                type: 'PATCH',
                url: window.route('api.v1.categories.patch-active', row.data().id),
                data: JSON.stringify({
                    "_token": csrfToken,
                    "active": !row.data().active,
                }),
                contentType: 'application/json',
                context: this,
                success: function (data) {
                    // Update row in table data source
                    categories.filter(category => category.id === data.id)[0].active = data.active;

                    toastHelpers.showSuccessToast(
                        __('Category active state changed')
                    );
                },
                error: function (_data) {
                    toastHelpers.showErrorToast(
                        __('Error while changing category active state.')
                    );
                },
                complete: function(_data) {
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
                url: window.route('api.v1.categories.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.categories = window.categories.filter(category => category.id !== data.category.id);
                    recalculateChildrenCounts(window.categories);

                    row.remove();
                    table.rows().invalidate().draw(false);
                    toastHelpers.showSuccessToast(
                        __('Category deleted')
                    );
                },
                error: function (data) {
                    toastHelpers.showErrorToast(
                        __('Error while trying to delete category: ') + data.responseJSON.error
                    );
                },
                complete: function (_data) {
                    // Restore button icon
                    element.removeClass('busy');
                }
            });
        });
    }
});

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(2).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
});

$('input[name=table_filter_category_level]').on("change", function() {
    // If parents are needed, then exclude categories without parent
    if (this.value === 'parents') {
        table.column(1).search('_parent_').draw();
    }
    // If children are needed, then exclude categories with parent
    else if (this.value === 'children') {
        table.column(1).search('_child_').draw();
    } else {
        table.column(1).search('').draw();
    }
});
