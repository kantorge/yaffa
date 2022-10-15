// TODO: better handle __() function, which is now assumed to be present in global scope

export function dataTablesActionButton(id, action, transactionType) {
    var functions = {
        delete: function() {
            return '<button class="btn btn-xs btn-danger data-delete" data-id="' + id + '" type="button"><i class="fa fa-fw fa-trash" title="' + __('Delete') + '"></i></button> ';
        },
        standardQuickView: function() {
            return '<button class="btn btn-xs btn-success transaction-quickview" data-id="' + id + '" type="button"><i class="fa fa-fw fa-eye" title="' + __('Quick view') + '"></i></button> ';
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
        }
    }

    return functions[action](transactionType);
}

export function genericDataTablesActionButton(id, action, route) {
    var functions = {
        delete: function(id) {
            return '<button class="btn btn-xs btn-danger data-delete" data-id="' + id + '" type=submit"><i class="fa fa-fw fa-trash" title="' + __('Delete') + '"></i></button> ';
        },
        edit: function(id, route) {
            return '<a href="' + window.route(route, id) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="' + __('Edit') + '"></i></a> ';
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

export function initializeDeleteButton(selector) {
    $(selector).on("click", ".data-delete", function() {
        if (!confirm(__('Are you sure to want to delete this item?'))) {
            return;
        }

        let form = document.getElementById('form-delete');
        form.action = route('transactions.destroy', {transaction: this.dataset.id});
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
    if (type == 'filter') {
        return  (data ? 'Yes' : 'No');
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
