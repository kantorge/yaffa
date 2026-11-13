const VERDICT_ORDER = [
  'no_drift',
  'cooldown',
  'insufficient_data',
  'irregular',
  'missing_rates',
  'needs_review',
  'excluded',
];

export const PREFS_KEY = 'yaffa.budgetSuggestions.prefs';

/** Last complete calendar month as YYYY-MM. */
export function lastCompleteMonth(now = new Date()) {
  const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/** Units without a suggestion, grouped by verdict in a stable order: [{verdict, units}]. */
export function groupUnitsByVerdict(units) {
  const groups = new Map();
  units
    .filter((unit) => unit.verdict !== 'suggestion')
    .forEach((unit) => {
      if (!groups.has(unit.verdict)) groups.set(unit.verdict, []);
      groups.get(unit.verdict).push(unit);
    });

  return [...groups.entries()]
    .sort(
      ([a], [b]) =>
        ((VERDICT_ORDER.indexOf(a) + 100) % 100) -
        ((VERDICT_ORDER.indexOf(b) + 100) % 100),
    )
    .map(([verdict, list]) => ({ verdict, units: list }));
}

/** Candidates that can be applied (a null amount means the row would flip sign). */
export function usableCandidates(suggestion) {
  return (suggestion.candidates || []).filter(
    (candidate) => candidate.suggested_amount !== null,
  );
}

/**
 * What the BudgetForm needs to open prefilled. `replace` keeps the row's own cadence, so only
 * amount, type and start date are set; `create` also carries the category and cadence.
 */
export function buildFormPrefill(unit, candidate = null, precision = 2) {
  const s = unit.suggestion;
  const signed =
    s.proposal_type === 'replace'
      ? candidate.suggested_amount
      : s.suggested_amount;
  const prefill = {
    amount: Number(Math.abs(signed).toFixed(precision)),
    transaction_type: signed < 0 ? 'withdrawal' : 'deposit',
    start_date: s.effective_date,
  };

  if (s.proposal_type === 'create') {
    prefill.category_id = unit.category_id;
    prefill.category_name = unit.category_name;
    prefill.frequency = s.cadence.frequency;
    prefill.interval = s.cadence.interval;
  }

  return prefill;
}

/** Index the monthly `series` rows by YYYY-MM. */
export function indexSeries(series) {
  const map = new Map();
  (series || []).forEach((row) =>
    map.set(row.period.slice(0, 7), row.recommended),
  );
  return map;
}

export function loadPrefs(storage = globalThis.localStorage) {
  try {
    return JSON.parse(storage.getItem(PREFS_KEY)) || {};
  } catch {
    return {};
  }
}

export function savePrefs(prefs, storage = globalThis.localStorage) {
  try {
    storage.setItem(PREFS_KEY, JSON.stringify(prefs));
  } catch {
    // storage unavailable: the choice is simply not remembered
  }
}
