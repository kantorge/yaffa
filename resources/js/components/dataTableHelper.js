import { __, toFormattedCurrency as toFormattedCurrencyHelper } from '../helpers';
const route = window.route;

export function dataTablesActionButton(id, action) {
    const functions = {
        delete: function () {
            return `
                <button 
                    class="btn btn-xs btn-danger data-delete"
                    data-delete
                    data-id="${id}"
                    type="button"
                    title="${__('Delete')}"
                >
                    <i class="fa fa-fw fa-spinner fa-spin"></i>
                    <i class="fa fa-fw fa-trash"></i>
                </button> `;
        },
        quickView: function () {
            return `
                <button 
                    class="btn btn-xs btn-success transaction-quickview"
                    data-id="${id}"
                    type="button"
                    title="${__('Quick view')}"
                >
                    <i class="fa fa-fw fa-spinner fa-spin"></i>
                    <i class="fa fa-fw fa-eye"></i>
                </button> `;
        },
        show: function () {
            return `
                <a 
                    href="${route('transaction.open', {
                        transaction: id,
                        action: 'show'
                    })}"
                    class="btn btn-xs btn-success"
                    title="${__('View details')}"
                >
                    <i class="fa fa-fw fa-search"></i>
                </a> `;
        },
        edit: function () {
            return '<a href="' + route('transaction.open', {
                transaction: id,
                action: 'edit'
            }) + '" class="btn btn-xs btn-primary" title="' + __('Edit') + '"><i class="fa fa-fw fa-edit"></i></a> ';
        },
        clone() {
            return '<a href="' + route('transaction.open', {
                transaction: id,
                action: 'clone'
            }) + '" class="btn btn-xs btn-primary" title="' + __('Clone') + '"><i class="fa fa-fw fa-clone"></i></a> ';
        },
        replace() {
            return '<a href="' + route('transaction.open', {
                transaction: id,
                action: 'replace'
            }) + '" class="btn btn-xs btn-primary" title="' + __('Edit and create new schedule') + '"><i class="fa fa-fw fa-calendar"></i></a> ';
        },
        skip: function () {
            return '<button class="btn btn-xs btn-warning" data-skip data-id="' + id + '" type="button" title="' + __('Skip current schedule') + '"><i class="fa fa-fw fa-spinner fa-spin"></i><i class="fa fa-fw fa-forward"></i></button> '
        },
        enter: function () {
            return `
                <a 
                    href="${route('transaction.open', {
                        transaction: id,
                        action: 'enter'
                    })}"
                    class="btn btn-xs btn-success"
                    title="${__('Edit and insert instance')}"
                >
                    <i class="fa fa-fw fa-pencil"></i>
                </a> `;
        }
    }

    return functions[action]();
}

export function genericDataTablesActionButton(id, action, route) {
    const functions = {
        delete: function (id) {
            return '<button class="btn btn-xs btn-danger data-delete" data-id="' + id + '" type="submit" title="' + __('Delete') + '"><i class="fa fa-fw fa-trash"></i></button> ';
        },
        edit: function (id, route) {
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

export function initializeFilterToggle(table, column, name) {
    $('input[name=' + name + ']').on("change", function () {
        table.column(column).search(this.value).draw();
    });
}

export function initializeStandardExternalSearch(table, searchSelector = '#table_filter_search_text') {
    $(searchSelector).on('input', function () {
        table.search(this.value).draw();
    });
}

export function tagIcon(tags, type) {
    if (!tags || tags.length === 0) {
        return '';
    }

    // Currently just the name is used
    tags = tags.map(tag => tag.name);

    if (type === 'filter') {
        return tags.join(', ');
    }

    return ' <i class="fa fa-tag text-primary" data-toggle="tooltip" data-placement="top" title="' + tags.join(', ') + '"></i>';
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

/**
 * This function is used to render a formatted currency value in a DataTables column
 * @param {string} type
 * @param {number} input
 * @param {string} locale
 * @param {Object} currency
 * @returns {number|string}
 */
export function toFormattedCurrency(type, input, locale, currency) {
    if (type === 'filter' || type === 'sort') {
        return input;
    }

    if (isNaN(input) || input === null) {
        return input;
    }

    return toFormattedCurrencyHelper(input, locale, currency);
}

export function initializeSkipInstanceButton(selector) {
    $(selector).on("click", ".data-skip", function () {
        let form = document.getElementById('form-skip');
        form.action = route('transactions.skipScheduleInstance', {transaction: this.dataset.id});
        form.submit();
    });
}

export function booleanToTableIcon(data, type) {
    if (type === 'filter') {
        return (data ? __('Yes') : __('No'));
    }
    return (data
        ? '<i class="fa fa-check-square text-success" title="' + __('Yes') + '"></i>'
        : '<i class="fa fa-square text-danger" title="' + __('No') + '"></i>');
}

export function transactionTypeIcon(type, name, customTitle) {
    if (type === 'standard') {
        if (name === 'withdrawal') {
            customTitle = customTitle || __("Withdrawal");
            return '<i class="fa fa-circle-minus text-danger" data-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (name === 'deposit') {
            customTitle = customTitle || __("Deposit");
            return '<i class="fa fa-circle-plus text-success" data-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (name === 'transfer') {
            customTitle = customTitle || __("Transfer");
            return '<i class="fa fa-exchange-alt text-primary" data-toggle="tooltip" title="' + customTitle + '"></i>';
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
export const transactionColumnDefinition = {
    // Generic date field
    dateFromCustomField: function (fieldName, title, locale) {
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
    iconFromBooleanField: function (fieldName, title) {
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
                    return row.config.account_to?.name;
                }
                if (row.transaction_type.name === 'deposit') {
                    return row.config.account_from?.name;
                }
                if (row.transaction_type.name === 'transfer') {
                    return __('Transfer from :account_from to :account_to', {
                        account_from: row.config.account_from?.name,
                        account_to: row.config.account_to?.name
                    });
                }
            }
            if (row.transaction_type.type === 'investment') {
                return row.config.account.name;
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
        /**
         * @param _data
         * @param {string} type
         * @param {Object} row
         * @property {Object} row.transaction_type
         * @property {string} row.transaction_type.type
         * @property {number} row.transaction_type.quantity_multiplier
         * @property {number} row.transaction_type.amount_multiplier
         */
        render: function (_data, type, row) {
            // Standard transaction
            if (row.transaction_type.type === 'standard') {
                // Empty
                if (row.categories.length === 0) {
                    return __('Not set');
                }

                if (row.categories.length > 1) {
                    return __('Split transaction');
                }

                if (row.categories[0]) {
                    return row.categories[0].full_name;
                }
            }
            // Investment transaction
            if (row.transaction_type.type === 'investment') {
                if (!isNaN(row.transaction_type.quantity_multiplier)) {
                    return row.transaction_type.name;
                }
                if (!isNaN(row.transaction_type.amount_multiplier)) {
                    return row.transaction_type.name + " " + row.config.quantity;
                }

                return row.transaction_type.name + " " + row.config.quantity.toLocaleString(window.YAFFA.locale, {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 4
                }) + " @ " + toFormattedCurrency(type, row.config.price, window.YAFFA.locale, row.transaction_currency);
            }
        },
        orderable: false
    },

    // Amount
    amount: {
        title: __("Amount"),
        defaultContent: '',
        /**
         * @param _data
         * @param type
         * @param {Object} row
         * @property {Object} row.transaction_type
         * @property {string} row.transaction_type.type
         * @property {number} row.transaction_type.quantity_multiplier
         * @property {number} row.transaction_type.amount_multiplier
         * @property {Object} row.transaction_currency
         */
        render: function (_data, type, row) {
            if (type === 'display') {
                let prefix = '';
                if (row.transaction_type.type === 'standard') {
                    if (row.transaction_type.amount_multiplier === -1) {
                        prefix = '- ';
                    }
                    if (row.transaction_type.amount_multiplier === 1) {
                        prefix = '+ ';
                    }

                    return prefix + toFormattedCurrency(
                        type,
                        row.config.amount_to,
                        window.YAFFA.locale,
                        row.transaction_currency
                    );
                }
                if (row.transaction_type.type === 'investment') {
                    let amount = (row.config.quantity ?? 0) * (row.config.price ?? 0) + (row.config.dividend ?? 0);

                    if (row.transaction_type.amount_multiplier === -1) {
                        prefix = '- ';
                        amount = amount + row.config.commission + row.config.tax ;
                        return prefix + toFormattedCurrency(
                            type,
                            amount,
                            window.YAFFA.locale,
                            row.transaction_currency
                        );
                    }
                    if (row.transaction_type.amount_multiplier === 1) {
                        prefix = '+ ';
                        amount = amount - row.config.commission - row.config.tax ;
                        return prefix + toFormattedCurrency(
                            type,
                            amount,
                            window.YAFFA.locale,
                            row.transaction_currency
                        );
                    }
                }
            }

            if (row.transaction_type.type === 'standard') {
                return row.config.amount_to;
            }
        },
        className: 'dt-nowrap',
        type: 'num',
    },

    // Amount referring to the global account currency
    amountCustom:  {
        title: __("Amount"),
        data: 'current_cash_flow',
        defaultContent: '',
        render: function (data, type) {
            if (type === 'display') {
                return toFormattedCurrency(
                    type,
                    data,
                    window.YAFFA.locale,
                    window.account.config.currency
                );
            }

            return data;
        },
        className: 'dt-nowrap',
        type: 'num',
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

    // Comma separated list of tag attached to transaction items
    tags: {
        data: 'tags',
        title: __('Tags'),
        defaultContent: '',
        render: function (data) {
            if (data?.length > 0) {
                return data.map(tag => tag.name).join(', ');
            }
        }
    },

    // Combined icons for comment and tags
    extra: {
        title: __("Extra"),
        defaultContent: '',
        render: function (_data, type, row) {
            return commentIcon(row.comment, type) + tagIcon(row.tags, type);
        },
        className: "text-center",
        orderable: false,
    },

    // Icon for the transaction type
    type: {
        title: __('Type'),
        defaultContent: '',
        render: function(_data, type, row) {
            if (type === 'filter' || type === 'type') {
                return __(row.transaction_type.type) + ' ' + __(row.transaction_type.name);
            }
            if (type === 'sort') {
                return __(row.transaction_type.name);
            }

            return transactionTypeIcon(row.transaction_type.type, row.transaction_type.name);
        },
        className: "text-center",
    },
}

export function initializeAjaxDeleteButton(selector, successCallback) {
    $(selector).on("click", "[data-delete]", function () {
        // Prevent running multiple times in parallel
        if ($(this).hasClass("busy")) {
            return false;
        }

        let id = Number(this.dataset.id);

        $(this).addClass('busy');

        axios.delete(window.route('api.transactions.destroy', {transaction: id}))
            .then(function () {
                // Find and remove original row in schedule table
                let row = $(selector).dataTable().api().row(function (_idx, data) {
                    return data.id === id;
                });

                row.remove().draw();

                // Emit a custom event to global scope about the result
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        header: __('Success'),
                        body: __('Transaction deleted (#:transactionId)', {transactionId: id}),
                        toastClass: "bg-success",
                    }
                });
                window.dispatchEvent(notificationEvent);

                // Execute callback if provided
                if (typeof successCallback === 'function') {
                    successCallback();
                }
            })
            .catch(function (error) {
                // Emit a custom event to global scope about the result
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        header: __('Error'),
                        body: __('Error deleting transaction (#:transactionId): :error', {transactionId: id, error: error}),
                        toastClass: "bg-danger"
                    }
                });
                window.dispatchEvent(notificationEvent);

                $(selector).find(".busy[data-delete]").removeClass('busy')
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
            .then(function (data) {
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

/**
 * This is a generic function to render delete button for assets
 * It receives a row object from DataTables and returns a button
 * It also receives an array of objects with the following properties, representing the requirements for the
 * delete button to be enabled:
 * - property: the name of the property in the row object
 * - value: the value of the property that enables the button
 * - negate: if true, the button is enabled when the property is NOT equal to the value
 * - errorMessage: the message to display when the button is disabled
 *
 * @param {Object} row
 * @param {Object} requirements
 * @param {String} errorMessage
 * @returns {String}
 */
export function renderDeleteAssetButton(row, requirements, errorMessage) {
    let passes = 0;
    let errorMessages = [
        errorMessage + "\n"
    ];

    requirements.forEach(requirement => {
        if (requirement.negate) {
            if (row[requirement.property] !== requirement.value) {
                passes++;
            } else {
                errorMessages.push(requirement.errorMessage);
            }
        } else {
            if (row[requirement.property] === requirement.value) {
                passes++;
            } else {
                errorMessages.push(requirement.errorMessage);
            }
        }
    });

    if (passes === requirements.length) {
        return `
            <button 
                class="btn btn-xs btn-danger deleteIcon"
                data-id="${row.id}"
                type="button"
                title="${__('Delete')}"
            >
                <i class="fa fa-fw fa-spinner fa-spin"></i>
                <i class="fa fa-fw fa-trash"></i>
            </button> `;
    }

    let title = errorMessages.join("\n");

    return `
        <button 
            class="btn btn-xs btn-outline-danger"
            data-id="${row.id}"
            type="button"
            title="${title}"
        >
            <i class="fa fa-fw fa-trash"></i>
        </button> `;
}

// Import the jstree plugin
import 'jstree/src/themes/default/style.css';
import 'jstree';

/**
 * Initialize a jsTree plugin for investment groups
 *
 * @param {string} selector
 * @param {array} data
 * @param {function} changeHandler
 *
 * @returns {void}
 */
export function investmentGroupTree(selector, data, changeHandler) {
    // The data is expected to be an array of objects with the raw properties from the database
    // Convert them to the format expected by the jstree plugin
    const treeData = (data || [])
        .map(group => {
            return {
                id:  group.id,
                parent: 0,
                text: group.name,
                state: {
                    selected: false,
                }
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
            opened: true
        }
    });

    // Initialize the jstree plugin, including the checkbox plugin and the callback for change events
    // Assume the jQuery plugin is available at global scope
    $(selector)
        .jstree({
            core: {
                data: treeData,
                themes: {
                    dots: false,
                    icons: false
                }
            },
            plugins: ['checkbox'],
            checkbox: {
                keep_selected_style: false
            }
        })
        .on('select_node.jstree', changeHandler)
        .on('deselect_node.jstree', changeHandler);
}
