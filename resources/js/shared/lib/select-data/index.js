// One shared, in-page copy of the reference data the transaction forms need (accounts, payees, investments,
// categories, tags, currencies) plus recent usage. Loaded once and memoized; everything after that (ranking,
// search, currency lookup) is local. Plain module, no store: this app keeps no global state.
import { categoryFullName } from './ranking.js';

export * from './ranking.js';

let pending = null;

function build(reference, usage) {
  const categoriesById = new Map(reference.categories.map((c) => [c.id, c]));

  return {
    accounts: reference.accounts,
    payees: reference.payees,
    investments: reference.investments,
    tags: reference.tags,
    categories: reference.categories.map((c) => ({
      ...c,
      full_name: categoryFullName(c, categoriesById),
    })),
    currencies: new Map(reference.currencies.map((c) => [c.id, c])),
    usage,
  };
}

/** Resolves once the page has fully loaded (after the first render), so the preload never competes with rendering. */
function whenPageIdle() {
  return new Promise((resolve) => {
    const start = () => setTimeout(resolve, 0);

    if (document.readyState === 'complete') {
      start();
    } else {
      window.addEventListener('load', start, { once: true });
    }
  });
}

/**
 * Resolves with the shared data. The request starts only after the page has loaded. A failed load is not
 * remembered, so a retry refetches.
 */
export function loadSelectData() {
  if (pending === null) {
    pending = whenPageIdle()
      .then(() =>
        Promise.all([
          window.axios.get(window.route('api.v1.reference-data')),
          window.axios.get(window.route('api.v1.reference-data.usage')),
        ]),
      )
      .then(([reference, usage]) => build(reference.data, usage.data))
      .catch((error) => {
        pending = null;
        throw error;
      });
  }

  return pending;
}

/** Add or update a row in the loaded data (e.g. a payee just created or reactivated). `kind` is a data key. */
export async function upsertEntity(kind, row) {
  const data = await loadSelectData();
  const index = data[kind].findIndex((e) => e.id === row.id);

  if (index === -1) {
    data[kind].push(row);
  } else {
    data[kind][index] = { ...data[kind][index], ...row };
  }
}
