import TomSelect from 'tom-select/base';
import clearButton from 'tom-select/plugins/clear_button/plugin.js';
import dropdownInput from 'tom-select/plugins/dropdown_input/plugin.js';
import removeButton from 'tom-select/plugins/remove_button/plugin.js';
import { escape_html as escape } from 'tom-select/utils';
import { __ } from '@/shared/lib/i18n/translate';
import { toQueryString } from './queryString';

TomSelect.define('clear_button', clearButton);
TomSelect.define('dropdown_input', dropdownInput);
TomSelect.define('remove_button', removeButton);

// Debounce for search requests
const REQUEST_DELAY_MS = 150;

// Set while setSelected()/clearSelect() run with `silent: true`, so the item_add/change listeners
// below skip the user callbacks. Safe as a module flag: those calls are fully synchronous.
let muted = false;

const resolve = (value) => (typeof value === 'function' ? value() : value);

const withQuery = (url, params) => {
  const query = toQueryString(params);
  if (!query) {
    return url;
  }

  return url + (url.includes('?') ? '&' : '?') + query;
};

/**
 * Create a Tom Select instance that searches a remote endpoint (the shared replacement for the
 * jQuery-plugin remote select pattern).
 *
 * - Every dropdown open and every keystroke (debounced) fetches again: there is no query cache,
 *   because several selects filter by state that changes at runtime.
 * - The dropdown shows exactly the last server response, in server order (no client re-scoring).
 * - The underlying <select> stays in sync, so form submission and `select.value` keep working.
 * - The dropdown stays inside the control's wrapper (Tom Select's default), so inside a modal it is
 *   within the modal's focus trap without a separate `dropdownParent`.
 *
 * @param {HTMLSelectElement|string} element
 * @param {Object} options
 * @param {string|function(): string} options.url Evaluated on every request.
 * @param {function(string): Object} [options.params] Query params for a search term, evaluated on
 *   every request. `undefined` values are omitted, `null` is sent empty (see queryString.js).
 * @param {function(Object): Object} [options.mapResult] Maps an API row to `{ id, text, ...extra }`.
 * @param {function(Object[]): Object[]} [options.filterResults] Applied to mapped rows on every
 *   response (e.g. to exclude another select's current value).
 * @param {string} [options.placeholder]
 * @param {boolean} [options.multiple]
 * @param {boolean} [options.allowClear] Show a clear button. Default: true.
 * @param {function(): string} [options.searchLabel] Label of the search results group. Default: "Matches".
 * @param {boolean} [options.create] Offer to create an option from the typed text (tags). Its value
 *   and label are the raw text. Not offered when an option with that label (case-insensitive) exists.
 * @param {function(Object, function): string} [options.renderOption] Dropdown option HTML. Receives
 *   Tom Select's `escape`, which must wrap every piece of API data.
 * @param {function(Object, function): string} [options.renderItem] Selected item HTML, as renderOption.
 * @param {function(Object): void} [options.onSelect] Called with the selected item's data when an
 *   item is added by the user or by a non-silent setSelected().
 * @param {function(): void} [options.onClear] Called once whenever the value becomes empty (clear
 *   button, removing the last item, non-silent clearSelect()).
 * @param {function(string|string[]): void} [options.onChange] Called with the new value.
 * @returns {TomSelect}
 */
export function createRemoteSelect(
  element,
  {
    url,
    params = (term) => ({ q: term }),
    mapResult = (item) => ({ id: item.id, text: item.text ?? item.name }),
    filterResults = (results) => results,
    placeholder = '',
    multiple = false,
    allowClear = true,
    create = false,
    renderOption,
    renderItem,
    onSelect,
    onClear,
    onChange,
  },
) {
  // Option ids of the last server response, in server order; the only options the dropdown lists
  let resultIds = [];
  let requestTimer = null;
  let abortController = null;
  // Whether a load() call has not yet called back (Tom Select counts pending loads in `loading`)
  let loadPending = false;

  const plugins = { dropdown_input: {} };
  if (allowClear) {
    plugins.clear_button = { title: escape(__('Clear selection')) };
  }
  if (multiple) {
    plugins.remove_button = { title: escape(__('Remove')) };
  }

  // Tom Select only returns an array value and keeps several <option>s selected on a <select multiple>
  if (multiple) {
    (typeof element === 'string'
      ? document.querySelector(element)
      : element
    ).multiple = true;
  }

  const ts = new TomSelect(element, {
    plugins,
    mode: multiple ? 'multi' : 'single',
    placeholder,
    valueField: 'id',
    labelField: 'text',
    searchField: [],
    maxOptions: null,
    highlight: false,
    // An empty term is a valid search: it's what an open dropdown with no input sends
    shouldLoad: () => true,
    // Debounced in load() instead, so a superseded request can be aborted and its stale options
    // dropped at once; no extra input throttle on top of that delay
    loadThrottle: null,
    refreshThrottle: 0,
    load(term, callback) {
      window.clearTimeout(requestTimer);
      abortController?.abort();
      if (loadPending) {
        // The superseded load will never call back, so release its slot in Tom Select's counter
        this.loading = Math.max(this.loading - 1, 0);
      }
      loadPending = true;

      // Drop the previous results right away (keeps selected options), so a stale list is never
      // shown while the next request runs
      resultIds = [];
      this.clearOptions();

      requestTimer = window.setTimeout(() => {
        abortController = new AbortController();
        window.axios
          .get(withQuery(resolve(url), params(term)), {
            signal: abortController.signal,
          })
          .then(({ data }) => {
            const results = filterResults(
              (Array.isArray(data) ? data : []).map(mapResult),
            );
            resultIds = results.map((item) => String(item.id));
            loadPending = false;
            callback(results);
          })
          .catch((error) => {
            if (window.axios.isCancel(error)) {
              return;
            }
            loadPending = false;
            callback();
          });
      }, REQUEST_DELAY_MS);
    },
    create,
    createFilter(input) {
      const label = input.toLowerCase();

      return (
        this.options[input] === undefined &&
        !Object.values(this.options).some(
          (option) => String(option.text).toLowerCase() === label,
        )
      );
    },
    render: {
      // Only set when given: an undefined entry would replace Tom Select's default template
      ...(renderOption && { option: renderOption }),
      ...(renderItem && { item: renderItem }),
      option_create: (data, escape) =>
        `<div class="create">${escape(data.input)} <em>${escape(__('(new)'))}</em></div>`,
      no_results: () =>
        `<div class="no-results">${escape(__('No results found'))}</div>`,
      loading: () =>
        `<div class="no-results">${escape(__('Searching...'))}</div>`,
    },
  });

  // List exactly the last response, in server order, instead of Tom Select's client-side search
  ts.hook('instead', 'search', function (query) {
    const items = resultIds
      .filter(
        (id) =>
          this.options[id] !== undefined &&
          !(this.settings.hideSelected && this.items.includes(id)),
      )
      .map((id) => ({ id, score: 1 }));

    return { query, tokens: [], items, total: items.length };
  });

  // Opening with an empty search runs a request (e.g. to show the most-used items)
  ts.on('dropdown_open', () => {
    ts.load(ts.inputValue());
    ts.refreshOptions(false);
  });

  ts.control_input.placeholder = __('Type to search...');

  // A pending request must not call back into a destroyed instance (e.g. one recreated on a
  // transaction type change)
  ts.hook('before', 'destroy', () => {
    window.clearTimeout(requestTimer);
    abortController?.abort();
  });

  ts.on('item_add', (value) => {
    if (!muted) {
      onSelect?.(ts.options[value]);
    }
  });

  // Tom Select fires 'change' once per value change (not for silent updates)
  ts.on('change', () => {
    if (muted) {
      return;
    }
    onChange?.(ts.getValue());
    if (ts.items.length === 0) {
      onClear?.();
    }
  });

  return ts;
}

/**
 * Create a single-select Tom Select that works entirely on data already in the browser: no requests.
 *
 * With an empty search the dropdown shows the `suggestions()` groups (e.g. "Most used" and "Recent"), in the order
 * given. Once the user types, it shows the single list `search(term)` returns, in that order (the caller does the
 * matching and ranking, see shared/lib/select-data). Both are evaluated on every open and keystroke, so they can
 * depend on other fields' current values.
 *
 * Items are `{ id, text, ...extra }`; `active: false` renders an "inactive" badge, and `matchedVia: 'alias'` shows
 * the item's `alias` next to its name. Selection, silent presets and the synced <select> behave as in
 * createRemoteSelect, so setSelected()/clearSelect() work on the returned instance.
 *
 * @param {HTMLSelectElement|string} element
 * @param {Object} options
 * @param {function(): Array<{id: string, label: string, items: Object[]}>} options.suggestions
 * @param {function(string): Object[]} options.search
 * @param {string} [options.placeholder]
 * @param {boolean} [options.allowClear] Show a clear button. Default: true.
 * @param {function(Object, function): string} [options.renderOption] Replaces the default option HTML.
 * @param {function(Object): void} [options.onSelect]
 * @param {function(): void} [options.onClear]
 * @param {function(string|string[]): void} [options.onChange]
 * @returns {TomSelect}
 */
export function createLocalSelect(
  element,
  {
    suggestions,
    search,
    placeholder = '',
    allowClear = true,
    searchLabel = () => __('Matches'),
    multiple = false,
    create = false,
    renderOption,
    onSelect,
    onClear,
    onChange,
  },
) {
  // Option ids currently listed, in display order; the only options the dropdown shows
  let resultIds = [];

  const plugins = { dropdown_input: {} };
  if (allowClear) {
    plugins.clear_button = { title: escape(__('Clear selection')) };
  }

  if (multiple) {
    plugins.remove_button = { title: escape(__('Remove')) };
    (typeof element === 'string'
      ? document.querySelector(element)
      : element
    ).multiple = true;
  }

  const defaultOption = (data, esc) =>
    `<div>${esc(data.text)}` +
    (data.matchedVia === 'alias' && data.alias
      ? ` <span class="text-muted small">(${esc(data.alias)})</span>`
      : '') +
    (data.active === false
      ? ` <span class="badge text-bg-secondary ms-1">${esc(__('inactive'))}</span>`
      : '') +
    '</div>';

  const ts = new TomSelect(element, {
    plugins,
    mode: multiple ? 'multi' : 'single',
    placeholder,
    valueField: 'id',
    labelField: 'text',
    optgroupField: 'optgroup',
    optgroupValueField: 'id',
    optgroupLabelField: 'label',
    lockOptgroupOrder: true,
    searchField: [],
    maxOptions: null,
    highlight: false,
    shouldLoad: () => true,
    loadThrottle: null,
    refreshThrottle: 0,
    // Synchronous "load": swap in the list for the current term. clearOptions() keeps the selected option.
    load(term, callback) {
      const groups = term.trim()
        ? [{ id: 'matches', label: searchLabel(), items: search(term) }]
        : suggestions();

      this.clearOptions();
      this.clearOptionGroups();
      resultIds = [];

      groups.forEach((group) => {
        if (group.items.length === 0) {
          return;
        }
        this.addOptionGroup(group.id, { id: group.id, label: group.label });
        group.items.forEach((item) => {
          this.addOption({ ...item, optgroup: group.id });
          resultIds.push(String(item.id));
        });
      });

      callback();
    },
    create,
    createFilter(input) {
      const label = input.toLowerCase();

      return (
        this.options[input] === undefined &&
        !Object.values(this.options).some(
          (option) => String(option.text).toLowerCase() === label,
        )
      );
    },
    render: {
      option_create: (data, esc) =>
        `<div class="create">${esc(data.input)} <em>${esc(__('(new)'))}</em></div>`,
      option: renderOption ?? defaultOption,
      optgroup_header: (data, esc) =>
        `<div class="optgroup-header">${esc(data.label)}</div>`,
      no_results: () =>
        `<div class="no-results">${escape(__('No results found'))}</div>`,
    },
  });

  // List exactly what load() put in, in its order, instead of Tom Select's own search
  ts.hook('instead', 'search', function (query) {
    const items = resultIds
      .filter(
        (id) =>
          this.options[id] !== undefined &&
          !(this.settings.hideSelected && this.items.includes(id)),
      )
      .map((id) => ({ id, score: 1 }));

    return { query, tokens: [], items, total: items.length };
  });

  ts.on('dropdown_open', () => {
    ts.load(ts.inputValue());
    ts.refreshOptions(false);
  });

  ts.control_input.placeholder = __('Type to search...');

  // Marks the grouped layout, so the taller dropdown styling applies to these selects only
  ts.dropdown.classList.add('ts-dropdown-grouped');

  ts.on('item_add', (value) => {
    if (!muted) {
      onSelect?.(ts.options[value]);
    }
  });

  ts.on('change', () => {
    if (muted) {
      return;
    }
    onChange?.(ts.getValue());
    if (ts.items.length === 0) {
      onClear?.();
    }
  });

  return ts;
}

/** Disable a select while its data loads, showing `text` as the placeholder. */
export function lockSelect(ts, text) {
  ts.settings.placeholder = text;
  ts.control_input.setAttribute('placeholder', text);
  ts.lock();
}

/** Re-enable a select locked by lockSelect(). */
export function unlockSelect(ts, placeholder) {
  ts.settings.placeholder = placeholder;
  ts.control_input.setAttribute('placeholder', placeholder);
  ts.unlock();
}

/**
 * Add option(s) and select them.
 * Unless silent, this runs the same onSelect/onChange path a user selection runs.
 *
 * @param {TomSelect} ts
 * @param {Object|Object[]} items `{ id, text, ...extra }`
 * @param {{ silent?: boolean }} [options]
 */
export function setSelected(ts, items, { silent = false } = {}) {
  muted = silent;
  try {
    (Array.isArray(items) ? items : [items]).forEach((item) => {
      ts.addOption(item);
      ts.addItem(String(item.id), silent);
    });
  } finally {
    muted = false;
  }
}

/**
 * Clear the value and all options (replaces `.empty().val(null).trigger('change')`).
 * Unless silent, onChange and onClear run once.
 *
 * @param {TomSelect} ts
 * @param {{ silent?: boolean }} [options]
 */
export function clearSelect(ts, { silent = false } = {}) {
  muted = silent;
  try {
    ts.clear(silent);
    ts.clearOptions();
  } finally {
    muted = false;
  }
}
