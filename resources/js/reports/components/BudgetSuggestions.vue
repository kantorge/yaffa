<template>
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title mb-0">{{ __('Budget check') }}</div>
    </div>
    <div class="card-body">
      <div class="row g-2 align-items-end mb-3">
        <div class="col-sm-6 col-lg-3">
          <label class="form-label" for="suggestionsDataThrough">
            {{ __('Data is complete through') }}
          </label>
          <input
            id="suggestionsDataThrough"
            v-model="dataThrough"
            type="month"
            class="form-control form-control-sm"
            :max="maxMonth"
          />
        </div>
        <div class="col-sm-3 col-lg-2">
          <label class="form-label" for="suggestionsSensitivity">
            {{ __('Sensitivity') }}
          </label>
          <select
            id="suggestionsSensitivity"
            v-model="sensitivity"
            class="form-select form-select-sm"
          >
            <option value="low">{{ __('Low') }}</option>
            <option value="medium">{{ __('Medium') }}</option>
            <option value="high">{{ __('High') }}</option>
          </select>
        </div>
        <div class="col-sm-3 col-lg-2">
          <label class="form-label" for="suggestionsBias">
            {{ __('Estimate') }}
          </label>
          <select
            id="suggestionsBias"
            v-model="bias"
            class="form-select form-select-sm"
          >
            <option value="neutral">{{ __('Neutral') }}</option>
            <option value="conservative">{{ __('Conservative') }}</option>
          </select>
        </div>
        <div class="col-lg-5 text-lg-end">
          <button
            type="button"
            class="btn btn-sm btn-primary me-1"
            :disabled="loading"
            @click="run('selected')"
          >
            {{ __('Check selected categories') }}
          </button>
          <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            :disabled="loading"
            @click="run('all')"
          >
            {{ __('Review all categories') }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="text-center py-3">
        <div
          class="spinner-border spinner-border-sm text-primary"
          role="status"
        >
          <span class="visually-hidden">{{ __('Loading') + '...' }}</span>
        </div>
      </div>
      <div v-else-if="error" class="alert alert-danger mb-0">{{ error }}</div>
      <div v-else-if="result === null" class="text-muted small">
        {{
          __(
            'Pick the month your data is complete through, then check the selected categories or review all of them. Nothing is changed until you create a budget period.',
          )
        }}
      </div>
      <template v-else>
        <div
          v-if="suggestions.length === 0"
          class="alert alert-success mb-3"
          data-testid="no-suggestions"
        >
          {{ __('No budget changes are recommended.') }}
        </div>

        <ul class="list-group mb-3">
          <li
            v-for="unit in suggestions"
            :key="unit.suggestion.key"
            class="list-group-item"
            data-testid="suggestion"
          >
            <div class="d-flex justify-content-between flex-wrap gap-2">
              <button
                type="button"
                class="btn btn-link p-0 fw-bold text-start"
                :title="__('Show this category on the chart')"
                @click="$emit('focus-category', unit.category_id)"
              >
                {{ unit.category_name }}
              </button>
              <div>
                <span class="text-muted">{{
                  money(shown(unit).current_amount, shown(unit).currency)
                }}</span>
                <i class="fa fa-arrow-right mx-1"></i>
                <strong>{{
                  money(shown(unit).suggested_amount, shown(unit).currency)
                }}</strong>
                <span class="text-muted small">
                  {{
                    __('from :date', {
                      date: shortDate(unit.suggestion.effective_date),
                    })
                  }}
                </span>
              </div>
            </div>

            <div class="small text-muted mt-1">
              {{ __('Recent level') }}:
              {{ money(unit.suggestion.level, base) }} · {{ __('Expected') }}:
              {{ money(unit.suggestion.expected, base) }} ({{
                __('per :period', { period: periodLabel(unit.granularity) })
              }}) ·
              {{
                __(':k of :n periods', {
                  k: unit.suggestion.persistence.k,
                  n: unit.suggestion.persistence.n,
                })
              }}
              ·
              {{
                __('data through :month', { month: result.run.data_through })
              }}
            </div>
            <div class="small text-muted" data-testid="suggestion-impact">
              {{ __('Monthly impact') }}:
              {{ money(unit.suggestion.monthly_impact, base) }}
              {{ __('per month') }}
              <span v-if="unit.suggestion.impact_share !== null">
                ({{
                  __(':share of monthly expenses', {
                    share: percent(unit.suggestion.impact_share),
                  })
                }})
              </span>
            </div>
            <div
              v-if="unit.suggestion.proposal_type === 'create'"
              class="small text-muted"
            >
              {{
                __('New budget, suggested cadence: :cadence', {
                  cadence: periodLabel(unit.granularity),
                })
              }}
              <span v-if="aggregationDiffers(unit)">
                ·
                {{
                  __(
                    "Differs from the category's default aggregation, which is not changed.",
                  )
                }}
              </span>
            </div>

            <div v-if="candidatesOf(unit).length > 1" class="mt-2">
              <label class="form-label small mb-0">{{
                __('Budget row to replace')
              }}</label>
              <select
                v-model="picked[unit.suggestion.key]"
                class="form-select form-select-sm"
              >
                <option
                  v-for="candidate in candidatesOf(unit)"
                  :key="candidate.budget_id"
                  :value="candidate.budget_id"
                >
                  {{ candidate.account_name || __('All accounts') }}:
                  {{ money(candidate.current_amount, candidate.currency) }} →
                  {{ money(candidate.suggested_amount, candidate.currency) }}
                </option>
              </select>
            </div>

            <div class="mt-2">
              <button
                type="button"
                class="btn btn-sm btn-success me-1"
                :disabled="
                  unit.suggestion.proposal_type === 'replace' &&
                  candidatesOf(unit).length === 0
                "
                @click="apply(unit)"
              >
                {{ __('Create new budget period') }}
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary me-1"
                @click="hide(unit)"
              >
                {{ __('Hide') }}
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-danger"
                @click="stopMonitoring(unit)"
              >
                {{ __('Stop monitoring this category') }}
              </button>
            </div>
          </li>
        </ul>

        <details v-if="groups.length > 0">
          <summary>{{ __('Categories without a recommendation') }}</summary>
          <div v-for="group in groups" :key="group.verdict" class="mt-2">
            <div class="fw-bold small">
              {{ verdictLabel(group.verdict) }} ({{ group.units.length }})
            </div>
            <div class="small text-muted">
              {{ group.units.map((unit) => unit.category_name).join(', ') }}
            </div>
          </div>
        </details>
      </template>
    </div>
  </div>
</template>

<script>
  import {
    __,
    toFormattedCurrency,
    toFormattedNumber,
    toFormattedShortDate,
  } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import {
    buildFormPrefill,
    groupUnitsByVerdict,
    lastCompleteMonth,
    loadPrefs,
    savePrefs,
    usableCandidates,
  } from '@/reports/budgetSuggestions';

  export default {
    props: {
      // Ids ticked in the category tree (the tree lives outside Vue)
      getSelectedCategoryIds: { type: Function, required: true },
      // default_aggregation of a category, read from the tree node
      getDefaultAggregation: { type: Function, required: true },
    },

    // focus-category: the user clicked a recommendation; apply: open the prefilled budget form;
    // result: a run finished (series may be null)
    emits: ['focus-category', 'apply', 'result'],

    data() {
      const prefs = loadPrefs();
      return {
        dataThrough: lastCompleteMonth(),
        maxMonth: lastCompleteMonth(),
        sensitivity: prefs.sensitivity || 'medium',
        bias: prefs.bias || 'neutral',
        loading: false,
        error: null,
        result: null,
        hidden: [],
        // suggestion key => picked candidate budget_id
        picked: {},
        // the unit whose form is open, dropped from the list once the budget is saved
        applying: null,
      };
    },

    computed: {
      base() {
        return this.result?.run.base_currency;
      },
      suggestions() {
        return (this.result?.units || []).filter(
          (unit) =>
            unit.suggestion && !this.hidden.includes(unit.suggestion.key),
        );
      },
      groups() {
        return groupUnitsByVerdict(this.result?.units || []);
      },
    },

    methods: {
      run(mode) {
        const params = {
          data_through: this.dataThrough,
          sensitivity: this.sensitivity,
          bias: this.bias,
        };
        const categoryIds = this.getSelectedCategoryIds();
        if (mode === 'all') {
          params.all = 1;
        } else if (categoryIds.length === 0) {
          toastHelpers.showWarningToast(
            __('Select at least one category first.'),
          );
          return Promise.resolve();
        } else {
          params.category_ids = categoryIds;
        }

        savePrefs({ sensitivity: this.sensitivity, bias: this.bias });
        this.loading = true;
        this.error = null;

        return window.axios
          .get(window.route('api.v1.reports.budget-suggestions'), {
            params,
            timeout: 60000,
          })
          .then(({ data }) => {
            this.result = data;
            this.hidden = [];
            this.picked = {};
            data.units.forEach((unit) => {
              if (unit.suggestion) {
                this.picked[unit.suggestion.key] =
                  usableCandidates(unit.suggestion)[0]?.budget_id ?? null;
              }
            });
            this.$emit('result', {
              series: data.series,
              categoryIds: mode === 'all' ? null : categoryIds.map(String),
            });
          })
          .catch((error) => {
            this.result = null;
            this.error =
              error.response?.data?.message ||
              __('Failed to run the budget check: :error', {
                error: error.message,
              });
          })
          .finally(() => {
            this.loading = false;
          });
      },

      // Fetches only the recommended series (the list stays as it is), so the chart can draw it
      // after the user picks a recommendation. It uses the ids the chart loads: the tree has
      // just been set to the clicked category, and checking a parent also checks its children.
      loadSeries() {
        const categoryIds = this.getSelectedCategoryIds();
        return window.axios
          .get(window.route('api.v1.reports.budget-suggestions'), {
            params: {
              category_ids: categoryIds,
              data_through: this.result?.run.data_through,
              sensitivity: this.sensitivity,
              bias: this.bias,
            },
            timeout: 60000,
          })
          .then(({ data }) => {
            this.$emit('result', { series: data.series, categoryIds });
          })
          .catch(() => {
            // The chart still works without the recommended line
            this.$emit('result', { series: null, categoryIds: null });
          });
      },

      candidatesOf(unit) {
        return unit.suggestion.proposal_type === 'replace'
          ? usableCandidates(unit.suggestion)
          : [];
      },

      pickedCandidate(unit) {
        return (
          this.candidatesOf(unit).find(
            (c) => c.budget_id === this.picked[unit.suggestion.key],
          ) || null
        );
      },

      // Amounts and currency shown for a unit: the picked candidate's own, or the
      // suggestion's for a new budget
      shown(unit) {
        return this.pickedCandidate(unit) || unit.suggestion;
      },

      apply(unit) {
        const candidate = this.pickedCandidate(unit);
        const baseId = window.YAFFA.userSettings.baseCurrency?.id;
        const precision =
          this.shown(unit).currency?.id === baseId
            ? (window.YAFFA.userSettings.baseCurrency
                ?.generic_decimal_precision ?? 2)
            : 2;
        this.applying = unit;
        this.$emit('apply', {
          budgetId: candidate?.budget_id ?? null,
          prefill: buildFormPrefill(unit, candidate, precision),
        });
      },

      // Called by the page after the form saved a budget of the given category. True when it
      // was the budget of the recommendation being applied (a cancelled form leaves
      // `applying` set, so an unrelated save must not match).
      markApplied(categoryId) {
        if (
          !this.applying ||
          this.applying.category_id !== Number(categoryId)
        ) {
          return false;
        }
        this.hide(this.applying);
        this.applying = null;
        return true;
      },

      hide(unit) {
        this.hidden.push(unit.suggestion.key);
      },

      stopMonitoring(unit) {
        window.axios
          .patch(
            window.route('api.v1.categories.patch-budget-suggestions', {
              category: unit.category_id,
            }),
            { calculate_budget_suggestions: false },
          )
          .then(() => {
            this.hide(unit);
            toastHelpers.showSuccessToast(
              __(
                'Budget suggestions are off for :category. Switch them back on in the category settings.',
                {
                  category: unit.category_name,
                },
              ),
            );
          })
          .catch((error) => {
            toastHelpers.showErrorToast(
              __('Failed to update the category: :error', {
                error: error.response?.data?.message || error.message,
              }),
            );
          });
      },

      aggregationDiffers(unit) {
        const current = this.getDefaultAggregation(unit.category_id);
        return !!current && current !== unit.granularity;
      },

      percent(fraction) {
        return toFormattedNumber(fraction, window.YAFFA.userSettings.locale, {
          style: 'percent',
          maximumFractionDigits: 1,
        });
      },

      money(value, currency) {
        return value === null || value === undefined
          ? '–'
          : toFormattedCurrency(
              value,
              window.YAFFA.userSettings.locale,
              currency,
            );
      },
      shortDate: (date) => toFormattedShortDate(date),
      periodLabel(granularity) {
        return (
          { month: __('Month'), quarter: __('Quarter'), year: __('Year') }[
            granularity
          ] || granularity
        );
      },
      verdictLabel(verdict) {
        return (
          {
            no_drift: __('No drift'),
            cooldown: __('Budget changed recently'),
            insufficient_data: __('Not enough data'),
            irregular: __('Irregular'),
            missing_rates: __('Missing exchange rates'),
            needs_review: __('Needs manual review'),
            excluded: __('Budget suggestions switched off'),
          }[verdict] || verdict
        );
      },
      __,
    },
  };
</script>
