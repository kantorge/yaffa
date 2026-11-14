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
            <option value="conservative_rounded">
              {{ __('Conservative and rounded') }}
            </option>
          </select>
        </div>
        <div class="col-lg-5 text-lg-end">
          <button
            type="button"
            class="btn btn-sm btn-primary me-1"
            :disabled="loading"
            @click="run('selected')"
          >
            <i class="fa fa-fw fa-list-check"></i>
            {{ __('Check selected categories') }}
          </button>
          <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            :disabled="loading"
            @click="run('all')"
          >
            <i class="fa fa-fw fa-magnifying-glass"></i>
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
            <div class="d-flex align-items-center gap-2">
              <button
                type="button"
                class="btn btn-link p-0 text-reset text-decoration-none text-start flex-grow-1 d-flex flex-wrap align-items-center column-gap-3"
                :aria-expanded="isExpanded(unit)"
                data-testid="suggestion-toggle"
                @click="toggle(unit)"
              >
                <span class="fw-bold">
                  <i
                    class="fa fa-fw text-muted me-1"
                    :class="
                      isExpanded(unit) ? 'fa-angle-down' : 'fa-angle-right'
                    "
                  ></i
                  >{{ unit.category_name }}
                </span>
                <span class="ms-auto text-nowrap">
                  <span class="text-muted">{{
                    money(shown(unit).current_amount, shown(unit).currency)
                  }}</span>
                  <i class="fa fa-arrow-right mx-1"></i>
                  <strong>{{
                    money(shown(unit).suggested_amount, shown(unit).currency)
                  }}</strong>
                </span>
              </button>
              <button
                type="button"
                class="btn-close"
                :title="__('Hide')"
                :aria-label="__('Hide')"
                @click="hide(unit)"
              ></button>
            </div>

            <div v-if="isExpanded(unit)" class="mt-2 ps-4">
              <div class="row g-3">
                <div
                  :class="
                    unit.suggestion.schedules.length > 0 ? 'col-lg-7' : 'col-12'
                  "
                >
                  <dl class="row small mb-2">
                    <dt class="col-sm-4 col-lg-3 fw-normal text-muted">
                      {{ __('Recent level') }}
                    </dt>
                    <dd class="col-sm-8 col-lg-9 mb-1 fw-bold">
                      {{ money(unit.suggestion.level, base) }}
                      <span class="fw-normal text-muted">
                        ({{
                          __('per :period', {
                            period: periodLabel(unit.granularity),
                          })
                        }})
                      </span>
                    </dd>
                    <dt class="col-sm-4 col-lg-3 fw-normal text-muted">
                      {{ __('Expected') }}
                    </dt>
                    <dd class="col-sm-8 col-lg-9 mb-1 fw-bold">
                      {{ money(unit.suggestion.expected, base) }}
                    </dd>
                    <dt class="col-sm-4 col-lg-3 fw-normal text-muted">
                      {{ __('Monthly impact') }}
                    </dt>
                    <dd
                      class="col-sm-8 col-lg-9 mb-1"
                      data-testid="suggestion-impact"
                    >
                      <strong>{{
                        money(unit.suggestion.monthly_impact, base)
                      }}</strong>
                      {{ __('per month') }}
                      <span
                        v-if="unit.suggestion.impact_share !== null"
                        class="text-muted"
                      >
                        ({{
                          __(':share of monthly expenses', {
                            share: percent(unit.suggestion.impact_share),
                          })
                        }})
                      </span>
                    </dd>
                    <dt class="col-sm-4 col-lg-3 fw-normal text-muted">
                      {{ __('Start date') }}
                    </dt>
                    <dd class="col-sm-8 col-lg-9 mb-1 fw-bold">
                      {{ shortDate(unit.suggestion.effective_date) }}
                    </dd>
                    <template v-if="unit.suggestion.proposal_type === 'create'">
                      <dt class="col-sm-4 col-lg-3 fw-normal text-muted">
                        {{ __('Cadence') }}
                      </dt>
                      <dd class="col-sm-8 col-lg-9 mb-1">
                        <strong>{{ periodLabel(unit.granularity) }}</strong>
                        <span
                          v-if="aggregationDiffers(unit)"
                          class="text-muted"
                        >
                          ·
                          {{
                            __(
                              "Differs from the category's default aggregation, which is not changed.",
                            )
                          }}
                        </span>
                      </dd>
                    </template>
                  </dl>
                  <div v-if="candidatesOf(unit).length > 1" class="mb-2">
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
                        {{
                          money(candidate.current_amount, candidate.currency)
                        }}
                        →
                        {{
                          money(candidate.suggested_amount, candidate.currency)
                        }}
                      </option>
                    </select>
                  </div>

                  <div class="d-flex flex-wrap gap-1">
                    <button
                      type="button"
                      class="btn btn-sm btn-success"
                      :disabled="
                        unit.suggestion.proposal_type === 'replace' &&
                        candidatesOf(unit).length === 0
                      "
                      @click="apply(unit)"
                    >
                      <i class="fa fa-fw fa-plus"></i>
                      {{ __('Create new budget period') }}
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      @click="$emit('focus-category', unit.category_id)"
                    >
                      <i class="fa fa-fw fa-chart-line"></i>
                      {{ __('Show this category on the chart') }}
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-danger"
                      @click="stopMonitoring(unit)"
                    >
                      <i class="fa fa-fw fa-bell-slash"></i>
                      {{ __('Stop monitoring this category') }}
                    </button>
                  </div>
                </div>
                <div
                  v-if="unit.suggestion.schedules.length > 0"
                  class="col-lg-5"
                >
                  <div class="border rounded p-2 small h-100">
                    <div class="fw-semibold mb-1">
                      {{ __('Or adjust the schedule instead') }}
                    </div>
                    <ul class="list-unstyled mb-1">
                      <li
                        v-for="schedule in unit.suggestion.schedules"
                        :key="schedule.transaction_id"
                      >
                        {{ schedule.name }}:
                        {{ money(schedule.amount, schedule.currency) }},
                        {{ cadenceText(schedule.cadence) }}
                        <span v-if="schedule.end_date">
                          ({{
                            __('until :date', {
                              date: shortDate(schedule.end_date),
                            })
                          }})
                        </span>
                        <a
                          :href="scheduleReplaceUrl(schedule)"
                          target="_blank"
                          rel="noopener"
                          class="ms-1"
                        >
                          {{ __('Replace this schedule') }}
                          <i class="fa fa-fw fa-up-right-from-square"></i>
                        </a>
                      </li>
                    </ul>
                    <div class="text-muted">
                      {{
                        __('After changing a schedule, run the check again.')
                      }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>

        <details v-if="groups.length > 0">
          <summary>{{ __('Categories without a recommendation') }}</summary>
          <div v-for="group in groups" :key="group.verdict" class="mt-2">
            <div class="fw-bold small">
              {{ verdictLabel(group.verdict) }} ({{ group.units.length }})
            </div>
            <div v-if="group.verdict === 'no_drift'" class="small text-muted">
              <div
                v-for="unit in group.units"
                :key="unit.category_id"
                class="d-flex align-items-center gap-2 mb-1"
                data-testid="no-drift-unit"
              >
                <span>{{ unit.category_name }}</span>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary py-0"
                  @click="$emit('focus-category', unit.category_id)"
                >
                  <i class="fa fa-fw fa-chart-line"></i>
                  {{ __('Show this category on the chart') }}
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-danger py-0"
                  @click="stopMonitoring(unit)"
                >
                  <i class="fa fa-fw fa-bell-slash"></i>
                  {{ __('Stop monitoring this category') }}
                </button>
              </div>
            </div>
            <div v-else class="small text-muted">
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

    // focus-category: the user asked to show a category on the chart; apply: open the prefilled budget form;
    // result: a run finished (series may be null); reload is set when the page should also
    // load the chart for the same categories
    emits: ['focus-category', 'apply', 'result'],

    data() {
      const prefs = loadPrefs();
      return {
        dataThrough: lastCompleteMonth(),
        maxMonth: lastCompleteMonth(),
        sensitivity: prefs.sensitivity || 'medium',
        bias: prefs.bias || 'neutral',
        loading: false,
        // Bumped when a run or series request starts, so an older response is ignored
        generation: 0,
        // Mode and ids of the displayed run, reused to refresh it
        lastRun: null,
        error: null,
        result: null,
        hidden: [],
        // suggestion key => open (the list starts folded)
        expanded: {},
        // categories whose monitoring was switched off from this list
        stopped: [],
        // suggestion key => picked candidate budget_id
        picked: {},
        // the unit whose form is open, dropped from the list once the budget is saved
        applying: null,
        // sensitivity and bias of the displayed result, reused for its series requests
        runOptions: {
          sensitivity: prefs.sensitivity || 'medium',
          bias: prefs.bias || 'neutral',
        },
      };
    },

    computed: {
      base() {
        return this.result?.run.base_currency;
      },
      suggestions() {
        return (this.result?.units || []).filter(
          (unit) =>
            unit.suggestion &&
            !this.hidden.includes(unit.suggestion.key) &&
            !this.stopped.includes(unit.category_id),
        );
      },
      groups() {
        return groupUnitsByVerdict(
          (this.result?.units || []).filter(
            (unit) => !this.stopped.includes(unit.category_id),
          ),
        );
      },
    },

    methods: {
      run(mode, ids = null) {
        const params = {
          data_through: this.dataThrough,
          sensitivity: this.sensitivity,
          bias: this.bias,
        };
        const categoryIds = ids ?? this.getSelectedCategoryIds();
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
        this.generation++;
        this.lastRun = { mode, ids: mode === 'all' ? null : categoryIds };

        return window.axios
          .get(window.route('api.v1.reports.budget-suggestions'), {
            params,
            timeout: 60000,
          })
          .then(({ data }) => {
            this.result = data;
            this.runOptions = {
              sensitivity: params.sensitivity,
              bias: params.bias,
            };
            this.hidden = [];
            this.expanded = {};
            this.stopped = [];
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
              reload: mode !== 'all',
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
        const generation = ++this.generation;
        return window.axios
          .get(window.route('api.v1.reports.budget-suggestions'), {
            params: {
              category_ids: categoryIds,
              data_through: this.result?.run.data_through,
              sensitivity: this.runOptions.sensitivity,
              bias: this.runOptions.bias,
            },
            timeout: 60000,
          })
          .then(({ data }) => {
            if (generation === this.generation) {
              this.$emit('result', { series: data.series, categoryIds });
            }
          })
          .catch(() => {
            // The chart still works without the recommended line
            if (generation !== this.generation) {
              return;
            }
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
        this.applying = unit;
        this.$emit('apply', {
          budgetId: candidate?.budget_id ?? null,
          prefill: buildFormPrefill(unit, candidate),
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

      isExpanded(unit) {
        return !!this.expanded[unit.suggestion.key];
      },

      toggle(unit) {
        this.expanded[unit.suggestion.key] = !this.isExpanded(unit);
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
            this.stopped.push(unit.category_id);
            // Descendants rolled into this unit are excluded too: rerun the check so the list
            // and the chart's recommended series no longer count them
            if (this.lastRun) {
              this.run(this.lastRun.mode, this.lastRun.ids);
            }
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
      cadenceText(cadence) {
        const n = cadence.interval;
        const every = {
          DAILY: __('Every :n days', { n }),
          WEEKLY: __('Every :n weeks', { n }),
          MONTHLY: __('Every :n months', { n }),
          YEARLY: __('Every :n years', { n }),
        };
        const single = {
          DAILY: __('Daily'),
          WEEKLY: __('Weekly'),
          MONTHLY: __('Monthly'),
          YEARLY: __('Yearly'),
        };
        return (
          (n === 1 ? single : every)[cadence.frequency] || cadence.frequency
        );
      },
      scheduleReplaceUrl(schedule) {
        return window.route('transaction.open', {
          transaction: schedule.transaction_id,
          action: 'replace',
        });
      },
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
