require('datatables.net-bs');
import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener,
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: categories.map(c => { c.parent = c.parent || { name: '' }; return c; }),
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
            data: "parent.name",
            title: "Parent category"
        },
        {
            data: "active",
            title: "Active",
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return  genericDataTablesActionButton(data, 'edit', 'categories.edit') +
                        genericDataTablesActionButton(data, 'delete') +
                        '<a href="' + route('categories.merge.form', { categorySource: data }) + '" class="btn btn-xs btn-primary"><i class="fa fa-random" title="Merge into an other category"></i></a> ';
            },
            orderable: false
        }
    ],
    order: [
        [1, 'asc']
    ],
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
                                message: 'Error while changing category active state',
                                title: null,
                                icon: null,
                                dismissable: true,
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

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(3).search(this.value).draw();
});
