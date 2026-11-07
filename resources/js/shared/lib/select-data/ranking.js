// Pure helpers behind the transaction form dropdowns: ranking by recent usage, local search and keeping usage
// current after a save. No Vue, no axios, so they run under plain `node --test` (see ranking.test.js).
// The ranking rules are mirrored for the mobile app in .ai/docs/features/mobile-app-support/architecture.md.

export const GROUP_SIZE = 5;

/** Lower-cased and accent-stripped, so "arpad" finds "Árpád". */
export function normalize(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

/**
 * Sum usage rows into Map<id, {uses, last_used}>.
 * @param {Array} rows usage rows (see ReferenceUsageService)
 * @param {(row) => (number|null)} idOf the id the row counts for, or null to skip it
 */
export function aggregateUsage(rows, idOf) {
  const map = new Map();

  rows.forEach((row) => {
    const id = idOf(row);
    if (id === null || id === undefined) {
      return;
    }
    const entry = map.get(id) ?? { uses: 0, last_used: '' };
    entry.uses += row.uses;
    if (row.last_used > entry.last_used) {
      entry.last_used = row.last_used;
    }
    map.set(id, entry);
  });

  return map;
}

/** Usage of one side of standard transactions, optionally only alongside a given entity on the other side. */
export function standardUsage(
  usage,
  { transactionType, side, anchorId = null },
) {
  const own = side === 'from' ? 'account_from_id' : 'account_to_id';
  const other = side === 'from' ? 'account_to_id' : 'account_from_id';
  const ofType = usage.standard.filter((r) => r.type === transactionType);

  if (anchorId) {
    const anchored = aggregateUsage(ofType, (r) =>
      r[other] === Number(anchorId) ? r[own] : null,
    );
    // No shared history: global usage is a better hint than nothing
    if (anchored.size > 0) {
      return anchored;
    }
  }

  return aggregateUsage(ofType, (r) => r[own]);
}

/** Usage of investments, optionally only for one account. */
export function investmentUsage(usage, { accountId = null } = {}) {
  return aggregateUsage(usage.investment, (r) =>
    !accountId || r.account_id === Number(accountId) ? r.investment_id : null,
  );
}

/** Usage of accounts in investment transactions. */
export function investmentAccountUsage(usage, { investmentId = null } = {}) {
  return aggregateUsage(usage.investment, (r) =>
    !investmentId || r.investment_id === Number(investmentId)
      ? r.account_id
      : null,
  );
}

const byName = (a, b) => a.name.localeCompare(b.name);

/**
 * Split candidates into "most used" and "recent" groups.
 *
 * Most used is picked first by count. Recent then takes the latest-used items NOT already in most used, so an
 * item never shows twice. `fill` tops the list up alphabetically when there is little history.
 *
 * @param {Array<{id:number,name:string}>} candidates already filtered (active, currency, exclusions...)
 * @param {Map} usageMap from aggregateUsage()
 */
export function rankByUsage(candidates, usageMap, { size = GROUP_SIZE } = {}) {
  const used = candidates.filter((c) => usageMap.has(c.id));

  const mostUsed = [...used]
    .sort(
      (a, b) =>
        usageMap.get(b.id).uses - usageMap.get(a.id).uses ||
        usageMap
          .get(b.id)
          .last_used.localeCompare(usageMap.get(a.id).last_used) ||
        byName(a, b),
    )
    .slice(0, size);

  const taken = new Set(mostUsed.map((c) => c.id));

  const recent = used
    .filter((c) => !taken.has(c.id))
    .sort(
      (a, b) =>
        usageMap
          .get(b.id)
          .last_used.localeCompare(usageMap.get(a.id).last_used) ||
        usageMap.get(b.id).uses - usageMap.get(a.id).uses ||
        byName(a, b),
    )
    .slice(0, size);

  recent.forEach((c) => taken.add(c.id));

  const fill = candidates
    .filter((c) => !taken.has(c.id))
    .sort(byName)
    .slice(0, Math.max(0, size * 2 - taken.size));

  return { mostUsed, recent, fill };
}

/** Local search over name and alias. Name matches come first, then alias; active before inactive. */
export function searchEntities(entities, term) {
  const needle = normalize(term).trim();
  if (needle === '') {
    return [];
  }

  const scored = [];
  entities.forEach((entity) => {
    const name = normalize(entity.name);
    const alias = normalize(entity.alias);
    let score = null;
    let via = 'name';

    if (name.startsWith(needle)) {
      score = 0;
    } else if (name.includes(needle)) {
      score = 1;
    } else if (alias.includes(needle)) {
      score = 2;
      via = 'alias';
    }

    if (score !== null) {
      scored.push({ entity, score: score + (entity.active ? 0 : 10), via });
    }
  });

  return scored
    .sort((a, b) => a.score - b.score || byName(a.entity, b.entity))
    .map(({ entity, via }) => ({ ...entity, matchedVia: via }));
}

/** "Parent > Child", resolved from the preloaded categories. */
export function categoryFullName(category, categoriesById) {
  const parent = category.parent_id && categoriesById.get(category.parent_id);

  return parent && parent.id !== category.id
    ? `${parent.name} > ${category.name}`
    : category.name;
}

/**
 * Category suggestions for a payee: its default category first, then its most used ones (deferred excluded).
 * Without a payee, or a payee without history, the most used categories across all payees stand in.
 *
 * @returns {Array<{id:number,name:string,parent_id:?number,active:boolean}>}
 */
export function suggestCategories(
  categories,
  usage,
  { payee = null, size = GROUP_SIZE } = {},
) {
  const byId = new Map(categories.map((c) => [c.id, c]));
  const deferred = new Set(
    usage.deferred_categories
      .filter((d) => payee && d.payee_id === payee.id)
      .map((d) => d.category_id),
  );
  const usable = (id) => byId.get(id)?.active && !deferred.has(id);

  const payeeRows = payee
    ? usage.payee_categories.filter((r) => r.payee_id === payee.id)
    : [];
  const rows = payeeRows.length > 0 ? payeeRows : usage.payee_categories;
  const counts = aggregateUsage(
    rows.map((r) => ({ ...r, last_used: '' })),
    (r) => r.category_id,
  );

  const ids = [...counts.entries()]
    .sort((a, b) => b[1].uses - a[1].uses)
    .map(([id]) => id)
    .filter(usable);

  const defaultId = payee?.category_id;
  const ordered =
    defaultId && usable(defaultId)
      ? [defaultId, ...ids.filter((id) => id !== defaultId)]
      : ids;

  return ordered.slice(0, size).map((id) => byId.get(id));
}

const toYmd = (date) => {
  if (typeof date === 'string') {
    return date.slice(0, 10);
  }
  const d = date instanceof Date ? date : new Date();
  const pad = (n) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

function bump(rows, match, create, date) {
  const row = rows.find(match);
  if (row) {
    row.uses += 1;
    if (date && date > (row.last_used ?? '')) {
      row.last_used = date;
    }
  } else {
    rows.push({ ...create, uses: 1, ...(date ? { last_used: date } : {}) });
  }
}

/**
 * Count a just-saved transaction (the shape processTransaction() returns) into `usage`, so the next entry in the
 * same page session ranks correctly without a refetch. Mutates and returns `usage`.
 */
export function applySavedTransaction(usage, transaction) {
  const date = toYmd(transaction.date);
  const type = transaction.transaction_type;
  const config = transaction.config ?? {};

  if (transaction.schedule) {
    return usage;
  }

  if (transaction.config_type === 'investment') {
    bump(
      usage.investment,
      (r) =>
        r.type === type &&
        r.account_id === config.account_id &&
        r.investment_id === config.investment_id,
      {
        type,
        account_id: config.account_id,
        investment_id: config.investment_id,
      },
      date,
    );

    return usage;
  }

  bump(
    usage.standard,
    (r) =>
      r.type === type &&
      r.account_from_id === config.account_from_id &&
      r.account_to_id === config.account_to_id,
    {
      type,
      account_from_id: config.account_from_id,
      account_to_id: config.account_to_id,
    },
    date,
  );

  const payeeId =
    type === 'withdrawal'
      ? config.account_to_id
      : type === 'deposit'
        ? config.account_from_id
        : null;

  if (payeeId) {
    (transaction.transaction_items ?? [])
      .filter((item) => item.category_id)
      .forEach((item) =>
        bump(
          usage.payee_categories,
          (r) =>
            r.payee_id === payeeId &&
            r.category_id === Number(item.category_id),
          { payee_id: payeeId, category_id: Number(item.category_id) },
          null,
        ),
      );
  }

  return usage;
}
