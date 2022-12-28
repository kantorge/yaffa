// TODO: better handle __() function, which is now assumed to be present in global scope

import * as helpers from "../helpers";

export function dataTablesActionButton(id, action, transactionType) {
    var functions = {
        delete: function() {
            return '<button class="btn btn-xs btn-danger data-delete" data-delete data-id="' + id + '" type="button" title="' + __('Delete') + '"><i class="fa fa-fw fa-spinner fa-spin"></i><i class="fa fa-fw fa-trash"></i></button> ';
        },
        standardQuickView: function() {
            return '<button class="btn btn-xs btn-success transaction-quickview" data-id="' + id + '" type="button" title="' + __('Quick view') + '"><i class="fa fa-fw fa-spinner fa-spin"></i><i class="fa fa-fw fa-eye"></i></button> ';
        },
        standardShow: function() {
            return '<a href="' + route('transactions.open.standard', {transaction: id, action: 'show'}) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="' + __('View details') + '"></i></a> ';
        },
        edit: function(transactionType) {
            return '<a href="' + route('transactions.open.' + transactionType, {transaction: id, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="' + __('Edit') + '"></i></a> ';
        },
        clone(transactionType) {
            return '<a href="' + route('transactions.open.' + transactionType, {transaction: id, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="' + __('Clone') + '"></i></a> ';
        },
        replace(transactionType) {
            return '<a href="' + route('transactions.open.' + transactionType, {transaction: id, action: 'replace'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-calendar" title="' + __('Edit and create new schedule') + '"></i></a> ';
        },
        skip: function() {
            return '<button class="btn btn-xs btn-warning" data-skip data-id="' + id + '" type="button" title="' + __('Skip current schedule') + '"><i class="fa fa-fw fa-spinner fa-spin"></i><i class="fa fa-fw fa-forward"></i></button> '
        }
    }

    return functions[action](transactionType);
}

export function genericDataTablesActionButton(id, action, route) {
    var functions = {
        delete: function(id) {
            return '<button class="btn btn-xs btn-danger data-delete" data-id="' + id + '" type=submit" title="' + __('Delete') + '"><i class="fa fa-fw fa-trash"></i></button> ';
        },
        edit: function(id, route) {
            return '<a href="' + window.route(route, id) + '" class="btn btn-xs btn-primary" title="' + __('Edit') + '"><i class="fa fa-fw fa-pencil"></i></a> ';
        },
    }

    return functions[action](id, route);
}

export function initializeDeleteButtonListener(tableSelector, route) {
    // Generate click listener for the table element provided
    $(tableSelector).on("click", ".data-delete", function () {
        // Confirm the action with the user
        if (!confirm(__('Are you sure to want to delete this item?'))) {
            return;
        }

        // Get the form placed in Blade component
        let form = document.getElementById('form-delete');

        // Adjust form action and submit
        // Ziggy route helper is expected to exist at global scope
        form.action = window.route(route, this.dataset.id);
        form.submit();
    });
}

export function initializeFilterButtonsActive(table, column) {
    $('input[name=active]').on("change", function() {
        table.column(column).search(this.value).draw();
    });
}

export function tagIcon(tags, type) {
    if (!tags || tags.length === 0) {
        return '';
    }

    if (type === 'filter') {
        return tags.join(', ');
    }

    if (tags) {
        return ' <i class="fa fa-tag text-primary" data-toggle="tooltip" data-placement="top" title="' + tags.join(', ') + '"></i>';
    }
}

export function commentIcon(comment, type) {
    if (!comment) {
        return '';
    }

    if (type === 'filter') {
        return comment;
    }

    return ' <i class="fa fa-comment text-primary" data-toggle="tooltip" data-placement="top" title="' + comment + '"></i>';
}

export function toFormattedCurrency(type, input, locale, currency) {
    if (type === 'filter' || type === 'sort') {
        return input;
    }

    if (isNaN(input) || input === null) {
        return input;
    }

    return helpers.toFormattedCurrency(input, locale, currency);
}

export function initializeDeleteButton(selector) {
    $(selector).on("click", ".data-delete", function() {
        if (!confirm(__('Are you sure to want to delete this item?'))) {
            return;
        }

        let form = document.getElementById('form-delete');
        form.action = route('api.transactions.destroy', {transaction: this.dataset.id});
        form.submit();
    });
}

export function initializeSkipInstanceButton(selector) {
    $(selector).on("click", ".data-skip", function() {
        let form = document.getElementById('form-skip');
        form.action = route('transactions.skipScheduleInstance', {transaction: this.dataset.id});
        form.submit();
    });
}

export function booleanToTableIcon (data, type) {
    if (type === 'filter') {
        return  (data ? __('Yes') : __('No'));
    }
    return (  data
            ? '<i class="fa fa-check-square text-success" title="' + __('Yes') + '"></i>'
            : '<i class="fa fa-square text-danger" title="' + __('No') + '"></i>');
}

export function transactionTypeIcon(type, name, customTitle) {
    if (type === 'standard') {
        if (name === 'withdrawal') {
            customTitle = customTitle || __("Withdrawal");
            return '<i class="fa fa-minus-square text-danger" data-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (name === 'deposit') {
            customTitle = customTitle || __("Deposit");
            return '<i class="fa fa-plus-square text-success" data-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (name === 'transfer') {
            customTitle = customTitle || __("Transfer");
            return '<i class="fa  fa-arrows-h text-primary" data-toggle="tooltip" title="' + customTitle + '"></i>';
        }
    } else if (type === 'investment') {
        customTitle = customTitle || name;
        return '<i class="fa fa-line-chart text-primary" data-toggle="tooltip" title="' + customTitle + '"></i>';
    }

    return null;
}

export function muteCellWithValue(column, mutedValue) {
    if (column.text() === mutedValue) {
        column.addClass('text-muted text-italic');
    }
}

// These objects can be used to standard data display in various dataTable column definitions
// The usage assumes that the underlying transaction object has also unified format
export let transactionColumnDefiniton = {
    // Generic date field
    dateFromCustomField: function(fieldName, title, locale) {
        return {
            data: fieldName,
            title: title,
            render: function (data, type) {
                if (type === 'display' && data && data.toLocaleDateString) {
                    return data.toLocaleDateString(locale);
                }

                return data;
            },
            className: "dt-nowrap",
            type: 'date',
        };
    },

    // Generic boolean field to icon
    iconFromBooleanField: function(fieldName, title) {
        return {
            data: fieldName,
            title: title,
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center",
        };
    },

    // Standard payee or investment name
    payee: {
        title: __('Payee'),
        defaultContent: '',
        render: function (_data, _type, row) {
            if (row.transaction_type.type === 'standard') {
                if (row.transaction_type.name === 'withdrawal') {
                    return row.config.account_to.name;
                }
                if (row.transaction_type.name === 'deposit') {
                    return row.config.account_from.name;
                }
                if (row.transaction_type.name === 'transfer') {
                    if (row.transactionOperator === 'minus') {
                        return __('Transfer to :account', {account: row.config.account_to.name});
                    } else {
                        return __('Transfer from :account', {account: row.config.account_from.name});
                    }
                }
            }
            if (row.transaction_type.type === 'investment') {
                return row.config.account_to.name;
            }

            // Special case for history view
            if (row.transaction_type.type === 'Opening balance') {
                return __('Opening balance');
            }

            return '';
        },
    },

    // Standard category or investment summary
    category: {
        title: __('Category'),
        defaultContent: '',
        render: function (_data, _type, row) {
            // Standard transaction
            if (row.transaction_type.type === 'standard') {
                // Empty
                if (row.categories.length === 0) {
                    return __('Not set');
                }

                if (row.categories.length > 1) {
                    return __('Split transaction');
                } else {
                    return row.categories[0];
                }
            }
            // Investment transaction
            if (row.transaction_type.type === 'investment') {
                if (!row.transaction_type.quantity_operator) {
                    return row.transaction_type.name;
                }
                if (!row.transaction_type.amount_operator) {
                    return row.transaction_type.name + " " + row.quantity;
                }

                return row.transaction_type.name + " " + row.quantity.toLocaleString(window.YAFFA.locale, {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 4
                  }) + " @ " + helpers.toFormattedCurrency(row.price, window.YAFFA.locale, row.currency);
            }

            return __('Not set');
        },
        orderable: false
    },

    // Amount
    amount: {
        title: __("Amount"),
        defaultContent: '',
        render: function (_data, type, row) {
            if (type === 'display') {
                let prefix = '';
                if (row.transaction_type.type === 'standard') {
                    if (row.transaction_type.amount_operator === 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_type.amount_operator === 'plus') {
                        prefix = '+ ';
                    }
                }
                return prefix + helpers.toFormattedCurrency(row.config.amount_to, window.YAFFA.locale, row.currency);
            }

            return row.config.amount_to;
        },
        className: 'dt-nowrap',
    },

    // Transaction comment (truncated)
    comment: {
        data: 'comment',
        title: __('Comment'),
        defaultContent: '',
        class: 'text-truncate',
        createdCell: function (td, cellData) {
            $(td).prop('title', cellData || '');
        }
    },

    // Comma separated list of tags attached to transaction items
    tags: {
        data: 'tags',
        title: __('Tags'),
        defaultContent: '',
        render: function (data) {
            return data.join(', ');
        }
    }
}

export function initializeAjaxDeleteButton(selector) {
    $(selector).on("click", "[data-delete]", function() {
        // Prevent running multiple times in parallel
        if ($(this).hasClass("busy")) {
            return false;
        }

        let id = this.dataset.id;

        $(this).addClass('busy');

        axios.delete('/api/transactions/' + id)
        .then(function (_response) {
            // Find and remove original row in schedule table
            var row = $(selector).dataTable().api().row(function (_idx, data, _node) {
                return data.id == id;
            });

            row.remove().draw();

            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('notification', {
                detail: {
                    notification: {
                        type: 'success',
                        message: 'Transaction deleted (#' + id + ')',
                        title: null,
                        icon: null,
                        dismissable: true,
                    }
                },
            });
            window.dispatchEvent(notificationEvent);
        });
    });
}

// Initialize event listener for quick-view button
export function initializeQuickViewButton(selector) {
    $(selector).on('click', 'button.transaction-quickview', function () {
        // Prevent running multiple times in parallel
        if ($(this).hasClass("busy")) {
            return false;
        }

        $(this).addClass('busy');
        let el = $(this);

        fetch('/api/transaction/' + this.dataset.id)
        .then(response => response.json())
        .then(function(data) {
            let transaction = data.transaction;

            // Convert dates to Date objects
            if (transaction.date) {
                transaction.date = new Date(transaction.date);
            }
            if (transaction.transaction_schedule) {
                if (transaction.transaction_schedule.start_date) {
                    transaction.transaction_schedule.start_date = new Date(transaction.transaction_schedule.start_date);
                }
                if (transaction.transaction_schedule.end_date) {
                    transaction.transaction_schedule.end_date = new Date(transaction.transaction_schedule.end_date);
                }
                if (transaction.transaction_schedule.next_date) {
                    transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
                }
            }

            // Emit global event for modal to display
            let event = new CustomEvent('showTransactionQuickviewModal', {
                detail: {
                    transaction: transaction,
                    controls: {
                        show: true,
                        edit: true,
                        clone: true,
                        skip: true,
                        enter: true,
                        delete: true,
                    }
                }
            });
            window.dispatchEvent(event);
        })
        .catch((error) => {
            console.log(error);
        })
        .finally(() => {
            el.removeClass('busy');
        });
    });
}
