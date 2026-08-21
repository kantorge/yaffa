import { __, toFormattedCurrency as toFormattedCurrencyHelper, toFormattedDate } from '@/shared/lib/i18n';
import {
  escapeHtml,
  getTransactionTypeConfig,
  processTransaction,
} from '@/shared/lib/helpers';
import * as toastHelpers from '@/shared/lib/toast';
import { confirmDelete } from '@/shared/lib/confirm';

const route = window.route;

// DataTables defaults to a blocking window.alert() on ajax/other errors (errMode: 'alert'),
// which freezes the whole page behind a native dialog on a transient network hiccup.
// Route errors through a dismissible toast instead.
if ($.fn.dataTable) {
    $.fn.dataTable.ext.errMode = function (settings, techNote, message) {
        toastHelpers.showErrorToast(message);
    };
}

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

    return ' <i class="fa fa-tag text-primary" data-bs-toggle="tooltip" data-placement="top" title="' + tags.join(', ') + '"></i>';
}

export function commentIcon(comment, type) {
    if (!comment) {
        return '';
    }

    if (type === 'filter') {
        return comment;
    }

    return ' <i class="fa fa-comment text-primary" data-bs-toggle="tooltip" data-placement="top" title="' + comment + '"></i>';
}

/**
 * This function is used to render a formatted currency value in a DataTables column
 * @param {string} type
 * @param {number} input
 * @param {string} locale
 * @param {Object} currency
 * @param {'generic'|'detailed'} [precision='generic']
 * @returns {number|string}
 */
export function toFormattedCurrency(type, input, locale, currency, precision = 'generic') {
    if (type === 'filter' || type === 'sort') {
        return input;
    }

    if (isNaN(input) || input === null) {
        return input;
    }

    return toFormattedCurrencyHelper(input, locale, currency, precision);
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

export function transactionTypeIcon(transactionType, customTitle) {
    const typeConfig = getTransactionTypeConfig(transactionType);

    if (typeConfig.category === 'standard') {
        if (transactionType === 'withdrawal') {
            customTitle = customTitle || __("Withdrawal");
            return '<i class="fa fa-circle-minus text-danger" data-bs-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (transactionType === 'deposit') {
            customTitle = customTitle || __("Deposit");
            return '<i class="fa fa-circle-plus text-success" data-bs-toggle="tooltip" title="' + customTitle + '"></i>';
        }
        if (transactionType === 'transfer') {
            customTitle = customTitle || __("Transfer");
            return '<i class="fa fa-exchange-alt text-primary" data-bs-toggle="tooltip" title="' + customTitle + '"></i>';
        }
    }

    if (typeConfig.category === 'investment') {
        customTitle = customTitle || typeConfig.label;
        return '<i class="fa fa-line-chart text-primary" data-bs-toggle="tooltip" title="' + customTitle + '"></i>';
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
                if (type === 'display') {
                    return toFormattedDate(data, locale, data);
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
            const typeConfig = getTransactionTypeConfig(row.transaction_type);

            if (typeConfig.category === 'standard') {
                if (row.transaction_type === 'withdrawal') {
                    return row.config.account_to?.name;
                }
                if (row.transaction_type === 'deposit') {
                    return row.config.account_from?.name;
                }
                if (row.transaction_type === 'transfer') {
                    return __('Transfer from :account_from to :account_to', {
                        account_from: row.config.account_from?.name,
                        account_to: row.config.account_to?.name
                    });
                }
            }

            if (typeConfig.category === 'investment') {
                return row.config.account.name;
            }

            // Special case for history view
            if (typeConfig.category === 'Opening balance') {
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
         * @property {string} row.transaction_type - Transaction type enum value
         */
        render: function (_data, type, row) {
            const typeConfig = getTransactionTypeConfig(row.transaction_type);

            // Standard transaction
            if (typeConfig.category === 'standard') {
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
            if (typeConfig.category === 'investment') {
                if (!isNaN(typeConfig.quantity_multiplier)) {
                    return typeConfig.label;
                }
                if (!isNaN(typeConfig.amount_multiplier)) {
                    return typeConfig.label + " " + row.config.quantity;
                }

                return typeConfig.label + " " + row.config.quantity.toLocaleString(window.YAFFA.userSettings.locale, {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 4
                }) + " @ " + toFormattedCurrency(type, row.config.price, window.YAFFA.userSettings.locale, row.transaction_currency);
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
         * @property {string} row.transaction_type - Transaction type enum value
         * @property {Object} row.transaction_currency
         */
        render: function (_data, type, row) {
            const typeConfig = getTransactionTypeConfig(row.transaction_type);

            if (type === 'display') {
                let prefix = '';
                if (typeConfig.category === 'standard') {
                    if (typeConfig.amount_multiplier === -1) {
                        prefix = '- ';
                    }
                    if (typeConfig.amount_multiplier === 1) {
                        prefix = '+ ';
                    }

                    return prefix + toFormattedCurrency(
                        type,
                        row.config.amount_to,
                        window.YAFFA.userSettings.locale,
                        row.transaction_currency
                    );
                }
                if (typeConfig.category === 'investment') {
                    let amount = (row.config.quantity ?? 0) * (row.config.price ?? 0) + (row.config.dividend ?? 0);

                    if (typeConfig.amount_multiplier === -1) {
                        prefix = '- ';
                        amount = amount + row.config.commission + row.config.tax ;
                        return prefix + toFormattedCurrency(
                            type,
                            amount,
                            window.YAFFA.userSettings.locale,
                            row.transaction_currency
                        );
                    }
                    if (typeConfig.amount_multiplier === 1) {
                        prefix = '+ ';
                        amount = amount - row.config.commission - row.config.tax ;
                        return prefix + toFormattedCurrency(
                            type,
                            amount,
                            window.YAFFA.userSettings.locale,
                            row.transaction_currency
                        );
                    }
                }
            }

            if (typeConfig.category === 'standard') {
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
                    window.YAFFA.userSettings.locale,
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
    type: function(withIcon = false) {
        return {
            title: __('Type'),
            defaultContent: '',
            data: 'transaction_type',
            render: function(data, type, _row) {
                const typeConfig = getTransactionTypeConfig(data);

                if (type === 'filter' || type === 'type') {
                    return __(typeConfig.category) + ' ' + __(typeConfig.label);
                }
                if (type === 'sort') {
                    return __(typeConfig.label);
                }

                if (withIcon) {
                    return transactionTypeIcon(data);
                }

                return typeConfig ? typeConfig.label : (data.charAt(0).toUpperCase() + data.slice(1));
            },
            className: (withIcon ? "text-center" : ""),
        }
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

        axios.delete(window.route('api.v1.transactions.destroy', {transaction: id}))
            .then(function () {
                // Find and remove original row in schedule table
                let row = $(selector).dataTable().api().row(function (_idx, data) {
                    return data.id === id;
                });

                row.remove().draw();

                // Emit a custom event to global scope about the result
                toastHelpers.showSuccessToast(__('Transaction deleted (#:transactionId)', {transactionId: id}));

                // Execute callback if provided
                if (typeof successCallback === 'function') {
                    successCallback();
                }
            })
            .catch(function (error) {
                // Emit a custom event to global scope about the result
                toastHelpers.showErrorToast(__('Error deleting transaction (#:transactionId): :error', {transactionId: id, error: error}));

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

        fetch('/api/v1/transactions/' + this.dataset.id)
            .then(response => response.json())
            .then(function (data) {
                // Normalizes dates and the decimal-string Money/BigDecimal fields (price,
                // quantity, amount_from/to, etc.) back to plain JS values - same as every
                // other consumer of a /api/v1/transactions/* response.
                let transaction = processTransaction(data.transaction);

                // Emit global event for modal to display
                let event = new CustomEvent('showTransactionQuickViewModal', {
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

/**
 * Wires the click handler for the `.deleteIcon` button rendered by renderDeleteAssetButton():
 * confirm -> axios DELETE -> toast, with the busy-class re-entrancy guard shared by every
 * such delete flow (see currencies/index.js and tags/index.js for the `.data-delete`
 * equivalent). Row/array bookkeeping is left to the caller via `onDeleted`, since it differs
 * per entity (e.g. categories also recalculates sibling children counts and needs a full
 * table invalidate rather than a single row removal).
 *
 * @param {string} tableSelector - DataTables container selector, e.g. '#table'
 * @param {string} routeName - e.g. 'api.v1.account-groups.destroy'
 * @param {string} successMessage
 * @param {function(number, jQuery): void} onDeleted - called with the deleted id and the
 *   button's `<tr>` after a successful delete, to remove the row / update local arrays
 */
export function initializeDeleteAssetButtonListener(tableSelector, routeName, successMessage, onDeleted) {
    $(tableSelector).on('click', 'td > button.deleteIcon:not(.busy)', function () {
        const button = $(this);
        const id = Number(button.data('id'));

        button.addClass('busy');

        confirmDelete(__('Are you sure to want to delete this item?')).then((result) => {
            if (!result.isConfirmed) {
                button.removeClass('busy');
                return;
            }

            axios.delete(window.route(routeName, id))
                .then(function () {
                    onDeleted(id, button.parents('tr'));
                    toastHelpers.showSuccessToast(successMessage);
                })
                .catch(function (error) {
                    toastHelpers.showErrorToast(error.response?.data?.error || __('Error while trying to delete item'));
                })
                .finally(function () {
                    button.removeClass('busy');
                });
        });
    });
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
                    selected: false
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

/**
 * Initialize a jsTree plugin for categories: hierarchical (parent/child) and
 * checkbox-selectable, fetched from the categories API. A simpler sibling of the bespoke
 * category tree on the budget chart page (resources/js/reports/budgetchart.js) - that one also
 * drives server-side aggregation (selected IDs as query params, per-node default_aggregation
 * metadata, URL-param preset restoration), which this plain DataTables-filter use case doesn't
 * need, so it isn't reused here beyond the same jstree init shape.
 *
 * No per-node icon is set (matches the icon-less investment group tree above) - a generic folder/
 * checkmark icon on every node added no information beyond the node's own text/active styling.
 *
 * @param {string} selector
 * @param {function} changeHandler
 * @param {number[]} [presetSelectedIds]
 * @param {Object} [options]
 * @param {boolean} [options.syncUrl] When true, checking/unchecking a node rewrites the page URL's
 *   `categories[]` query params to match the tree's current selection (pushState), the same
 *   preset-filter convention budgetchart.js's own category tree already follows.
 *
 * @returns {void}
 */
export function categoryTree(selector, changeHandler, presetSelectedIds = [], options = {}) {
    const { syncUrl = false } = options;

    function handleChange() {
        if (syncUrl) {
            const treeInstance = $(selector).jstree(true);
            const checked = treeInstance ? treeInstance.get_checked() : [];
            const url = new URL(window.location.href);
            url.searchParams.delete('categories[]');
            checked.forEach(id => url.searchParams.append('categories[]', id));
            window.history.pushState('', '', url.toString());
        }

        changeHandler();
    }

    $(selector)
        .jstree({
            core: {
                data: function (_obj, callback) {
                    fetch('/api/v1/categories?withInactive=1&q=*')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`Failed to load categories: ${response.status}`);
                            }

                            return response.json();
                        })
                        .then(data => {
                            const categories = data.map(function (category) {
                                return {
                                    id: category.id,
                                    parent: category.parent_id || '#',
                                    text: category.active
                                        ? escapeHtml(category.name)
                                        : '<span class="text-muted" title="' + __('Inactive') + '">' + escapeHtml(category.name) + '</span>',
                                    state: {
                                        selected: presetSelectedIds.includes(category.id)
                                    },
                                };
                            });
                            callback.call(this, categories);
                        })
                        .catch(() => {
                            toastHelpers.showErrorToast(__('Failed to load categories'));
                            callback.call(this, []);
                        });
                },
                themes: {
                    dots: false,
                    icons: false
                }
            },
            plugins: ['checkbox'],
            checkbox: {
                keep_selected_style: false
            },
        })
        .on('select_node.jstree', handleChange)
        .on('deselect_node.jstree', handleChange)
        .on('check_all.jstree', handleChange)
        .on('uncheck_all.jstree', handleChange)
        .on('ready.jstree', function () {
            if (presetSelectedIds.length > 0) {
                handleChange();
            }
        });
}
