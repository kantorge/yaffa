export function dataTablesActionButton(id, action) {
    var functions = {
        delete: function() {
            return '<button class="btn btn-xs btn-danger data-delete" data-id="' + id + '" type="button"><i class="fa fa-fw fa-trash" title="Delete"></i></button>';
        },
        standardQuickView: function() {
            return '<button class="btn btn-xs btn-success data-quickview" data-id="' + id + '" type="button"><i class="fa fa-fw fa-eye" title="Quick view"></i></button> ';
        },
        standardShow: function() {
            return '<a href="' + route('transactions.openStandard', {transaction: id, action: 'show'}) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-search" title="View details"></i></a> ';
        },
        standardEdit: function() {
            return '<a href="' + route('transactions.openStandard', {transaction: id, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ';
        },
        standardClone: function() {
            return '<a href="' + route('transactions.openStandard', {transaction: id, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> ';
        }
    }

    return functions[action]();
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
        if (!confirm('Are you sure to want to delete this item?')) {
            return;
        }

        let form = document.getElementById('form-delete');
        form.action = route('transactions.destroy', {transaction: this.dataset.id});
        form.submit();
    });
}

export function booleanToTableIcon (data, type) {
    if (type == 'filter') {
        return  (data ? 'Yes' : 'No');
    }
    return (  data
            ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
            : '<i class="fa fa-square text-danger" title="No"></i>');
}

export function transactionTypeIcon(type, name) {
    if (type === 'Standard') {
        if (name === 'withdrawal') {
            return '<i class="fa fa-minus-square text-danger" data-toggle="tooltip" title="Withdrawal"></i>';
        }
        if (name === 'deposit') {
            return '<i class="fa fa-plus-square text-success" data-toggle="tooltip" title="Deposit"></i>';
        }
        if (name === 'transfer') {
            return '<i class="fa  fa-arrows-h text-primary" data-toggle="tooltip" title="Transfer"></i>';
        }
    } else if (type === 'Investment') {
        return '<i class="fa fa-line-chart text-primary" data-toggle="tooltip" title="' + name + '"></i>';
    }

    return null;
}
