import { __ } from '@/shared/lib/i18n';
import { toFormattedCurrency } from '@/shared/lib/i18n';
import * as toastHelpers from '@/shared/lib/toast';

const table = document.getElementById('advancedReconcileDashboard');
const typeSelect = document.getElementById('advancedReconcileType');
const displaySelect = document.getElementById('advancedReconcileDisplay');
const reloadButton = document.getElementById('advancedReconcileReload');

const statusLabels = {
    matched: __('Matched'),
    reconcile_required: __('Reconcile required'),
    no_checkpoint: __('No checkpoint'),
};

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';
    return element.innerHTML;
}

function formatAmount(value, currency) {
    if (value === null || value === undefined) {
        return '';
    }

    return toFormattedCurrency(value, window.YAFFA.userSettings.locale, currency || window.YAFFA.userSettings.baseCurrency);
}

function cellClass(status) {
    if (status === 'matched') {
        return 'table-success';
    }
    if (status === 'reconcile_required') {
        return 'table-warning';
    }
    return 'table-light text-muted';
}

function reconcileUrl(accountId, cell) {
    return window.route('account-entity.show', {
        account_entity: accountId,
        date_from: cell.date_from,
        date_to: cell.date_to,
    });
}

function render(data) {
    const thead = table.querySelector('thead');
    const tbody = table.querySelector('tbody');

    thead.innerHTML = '<tr><th>' + __('Account') + '</th>' +
        data.months.map(month => '<th class="text-end">' + month.label + '</th>').join('') +
        '</tr>';

    tbody.innerHTML = data.rows.map((row) => {
        const cells = data.months.map((month) => {
            const cell = row.months[month.key];
            const href = reconcileUrl(row.account.id, cell);
            const content = displaySelect.value === 'balance'
                ? formatAmount(cell.checkpoint?.balance, row.account.currency)
                : (cell.status === 'reconcile_required'
                    ? statusLabels[cell.status] + ' (' + formatAmount(cell.variance, row.account.currency) + ')'
                    : statusLabels[cell.status]);

            return '<td class="text-end ' + cellClass(cell.status) + '">' +
                '<a href="' + href + '" class="link-dark text-decoration-none d-block">' + content + '</a>' +
                '</td>';
        }).join('');

        return '<tr><th scope="row">' + escapeHtml(row.account.name) + '</th>' + cells + '</tr>';
    }).join('');
}

function loadDashboard() {
    reloadButton.disabled = true;

    const url = window.route('api.v1.reports.advanced-reconcile', {
        checkpoint_type: typeSelect.value,
        display: displaySelect.value,
    });

    fetch(url)
        .then(response => response.json())
        .then(render)
        .catch((error) => {
            toastHelpers.showErrorToast(error.message);
        })
        .finally(() => {
            reloadButton.disabled = false;
        });
}

typeSelect.addEventListener('change', loadDashboard);
displaySelect.addEventListener('change', loadDashboard);
reloadButton.addEventListener('click', loadDashboard);

loadDashboard();
