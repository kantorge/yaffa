/**
 * Transaction Types Composable
 * Provides access to transaction type constants from the backend API
 */

let transactionTypesCache = null;
let transactionTypesPromise = null;

/**
 * Fetch transaction types from the API
 * @returns {Promise<Object>} Transaction types object
 */
async function fetchTransactionTypes() {
  if (transactionTypesCache) {
    return transactionTypesCache;
  }

  if (transactionTypesPromise) {
    return transactionTypesPromise;
  }

  transactionTypesPromise = window.axios
    .get('/api/transaction-types')
    .then((response) => {
      transactionTypesCache = response.data;
      transactionTypesPromise = null;
      return transactionTypesCache;
    })
    .catch((error) => {
      transactionTypesPromise = null;
      console.error('Failed to fetch transaction types:', error);
      throw error;
    });

  return transactionTypesPromise;
}

/**
 * Get all transaction types
 * @returns {Promise<Object>} All transaction types
 */
export async function getTransactionTypes() {
  return await fetchTransactionTypes();
}

/**
 * Get a specific transaction type by value
 * @param {string} value - The transaction type value (e.g., 'withdrawal', 'deposit')
 * @returns {Promise<Object|null>} The transaction type object or null if not found
 */
export async function getTransactionType(value) {
  const types = await fetchTransactionTypes();
  return types[value] || null;
}

/**
 * Get all standard transaction types
 * @returns {Promise<Array>} Array of standard transaction types
 */
export async function getStandardTransactionTypes() {
  const types = await fetchTransactionTypes();
  return Object.values(types).filter((type) => type.category === 'standard');
}

/**
 * Get all investment transaction types
 * @returns {Promise<Array>} Array of investment transaction types
 */
export async function getInvestmentTransactionTypes() {
  const types = await fetchTransactionTypes();
  return Object.values(types).filter(
    (type) => type.category === 'investment'
  );
}

/**
 * Clear the transaction types cache (useful for testing)
 */
export function clearTransactionTypesCache() {
  transactionTypesCache = null;
  transactionTypesPromise = null;
}
