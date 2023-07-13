require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener,
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

// Loop categories and prepare data for datatable
window.categories = window.categories.map(function(category) {
    // Parse first date if it exists
    if (category.transactions_min_date) {
        category.transactions_min_date = new Date(Date.parse(category.transactions_min_date));
    }
    // Parse last date if it exists
    if (category.transactions_max_date) {
        category.transactions_max_date = new Date(Date.parse(category.transactions_max_date));
    }

    return category;
});


window.table = $(dataTableSelector).DataTable({
    data: window.categories,
    columns: [
        {
            data: "name",
            title: __("Name")
        },
        {
            data: "parent",
            title: __("Parent category"),
            render: function (data, type) {
                if (type === 'filter') {
                    return data ? 'child_category' : 'parent_category';
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
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
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
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'categories.edit') +
                        genericDataTablesActionButton(data, 'delete') +
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
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete : function(settings) {
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
                type: 'PUT',
                url: '/api/assets/category/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    categories.filter(category => category.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    // Emit a custom event to global scope about the problem
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: () => __('Error while changing category active state'),
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function(_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    }
});

initializeDeleteButtonListener(dataTableSelector, 'categories.destroy');

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(2).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
});

$('input[name=table_filter_category_level]').on("change", function() {
    // TODO: use regex to search for parent/child categories, instead of predefined strings
    // If parents are needed, then exclude categories without parent
    if (this.value === 'parents') {
        table.column(1).search('parent_category').draw();
    }
    // If children are needed, then exclude categories with parent
    else if (this.value === 'children') {
        table.column(1).search('child_category').draw();
    } else {
        table.column(1).search('').draw();
    }
});
