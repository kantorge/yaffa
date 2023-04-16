// Import dataTable helper functions, used to display transaction icons
import * as dataTableHelpers from './../components/dataTableHelper'

const ApiParameterNames = new Map([
  ['account', 'accounts'],
  ['payee', 'payees'],
  ['category', 'categories'],
  ['tag', 'tags'],
]);

// Common function to get transaction count for specific result types
function getTransactionCount (element) {
  const type = element.dataset.type;
  const apiParameterName = ApiParameterNames.get(type) + '[]';
  const id = element.dataset.id;

  // Get the count from the API
  fetch(`/api/transactions/?only_count=1&${apiParameterName}=${id}`)
  .then(response => response.json())
  .then(data => {
    if (data.count === 0) {
      element.innerHTML = '<span class="badge text-bg-light">' + __('No transactions') + '</span>';
    } else {
      element.innerHTML = `<a href=" ${window.route('reports.transactions', {[apiParameterName]: id})}"
                              title="${__('View transactions')}"
                           >
                              ${data.count} ${(data.count === 1 ? __('transaction') : __('transactions'))}
                           </a>`;
    }
    element.classList.remove('hidden');
  })
  .catch(error => {
    console.log(error);
  });
}

// Loop the span placeholder for all the account results, and get the number of associated transactions for each account.
document.querySelectorAll('#table-search-results-accounts td.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the payee results, and get the number of associated transactions for each payee.
document.querySelectorAll('#table-search-results-payees td.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the tag results, and get the number of associated transactions for each tag.
document.querySelectorAll('#list-search-results-tag span.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for all the category results, and get the number of associated transactions for each category.
document.querySelectorAll('#list-search-results-categories span.transactionCount').forEach(getTransactionCount);

// Loop the span placeholder for transactions, and display the view and quick view icons for each transaction.
document.querySelectorAll('#table-search-results-transactions td.transactionIcon').forEach(function (element) {
    const id = element.dataset.id;
    element.innerHTML = dataTableHelpers.dataTablesActionButton(id, 'quickView') + dataTableHelpers.dataTablesActionButton(id, 'show');
    element.classList.remove('hidden');
});

dataTableHelpers.initializeQuickViewButton('table');

// Transaction quick view modal
import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import QuickViewTransactionModal from './../components/TransactionDisplay/Modal.vue'
app.component('transaction-show-modal', QuickViewTransactionModal)

app.mount('#app')
