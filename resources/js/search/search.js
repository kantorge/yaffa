var pluralize = require('pluralize')

// Import dataTable helper functions, used to display transaction icons
import * as dataTableHelpers from './../components/dataTableHelper'

const ApiParameterNames = new Map([
    ['account', 'accounts'],
    ['payee', 'payees'],
    ['category', 'categories'],
    ['tag', 'tags'],
]);

// Common function to get transaction count for specific result types
var getTransactionCount = function (element) {
    var type = element.dataset.type;
    var apiParameterName = ApiParameterNames.get(type) + '[]';
    var id = element.dataset.id;

    // Get the count from the API
    fetch(`/api/transactions/?only_count=1&${apiParameterName}=${id}`)
    .then(response => response.json())
    .then(data => {
        if (data.count === 0) {
            element.innerHTML = '<span class="label label-default">No transactions</span>';
        } else {
            element.innerHTML = '<a href="' + route('reports.transactions', {[apiParameterName]: id}) + '" title="View transactions" class="label label-info">' + pluralize('transaction', data.count, true)  + '</a>';
        }
        element.classList.remove('hidden');
    })
    .catch(error => {
        console.log(error);
    });
}

// Loop the span placeholder for all the account results, and get the number of associated transactions for each account.
document.querySelectorAll('#accounts td.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the payee results, and get the number of associated transactions for each payee.
document.querySelectorAll('#payees td.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the tag results, and get the number of associated transactions for each tag.
document.querySelectorAll('#tags span.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the category results, and get the number of associated transactions for each category.
document.querySelectorAll('#categories span.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for transactions, and display the view and quick view icons for each transaction.
document.querySelectorAll('#transactions td.transactionIcon').forEach(function (element) {
    const id = element.dataset.id;
    element.innerHTML = dataTableHelpers.dataTablesActionButton(id, 'standardQuickView') + dataTableHelpers.dataTablesActionButton(id, 'standardShow');
    element.classList.remove('hidden');
});

// Transaction quick view modal
import { createApp } from 'vue'
import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
const app = createApp({})
app.component('transaction-show-modal', TransactionShowModal)
app.mount('#app')
