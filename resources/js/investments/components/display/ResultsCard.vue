<template>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Results') }}
            </div>
            <div>
                <button class="btn btn-sm btn-primary" @click="resetDates">
                    {{ __('Reset dates') }}
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label for="date_from" class="col-6 col-sm-2 col-form-label">
                    {{ __('Date from') }}
                </label>
                <div class="col-6 col-sm-4">
                    <input
                        v-model="dateFromString"
                        type="date"
                        class="form-control"
                        :max="dateToString"
                    />
                </div>
                <label for="date_to" class="col-6 col-sm-2 col-form-label">
                    {{ __('Date to') }}
                </label>
                <div class="col-6 col-sm-4">
                    <input
                        v-model="dateToString"
                        type="date"
                        class="form-control"
                        :min="dateFromString"
                    />
                </div>
            </div>
            <div class="row mb-0">
                <div class="col-sm-6">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('Buying cost') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Buying,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('Added quantity') }}</dt>
                        <dd class="col-6">
                            {{ formatQuantity(summary.Added) }}
                        </dd>
                        <dt class="col-6">{{ __('Removed quantity') }}</dt>
                        <dd class="col-6">
                            {{ formatQuantity(summary.Removed) }}
                        </dd>
                        <dt class="col-6">{{ __('Selling revenue') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Selling,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('Dividend') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Dividend,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('Commissions') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Commission,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('Taxes') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Taxes,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('Quantity') }}</dt>
                        <dd class="col-6">
                            {{ formatQuantity(summary.Quantity) }}
                        </dd>
                        <dt class="col-6">{{ __('Value') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Value,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                    </dl>
                </div>
                <div class="col-sm-6">
                    <dl class="row mb-0">
                        <dt class="col-6">{{ __('Result') }}</dt>
                        <dd class="col-6">
                            {{
                                toFormattedCurrency(
                                    summary.Result,
                                    locale,
                                    investment.currency,
                                )
                            }}
                        </dd>
                        <dt class="col-6">{{ __('ROI') }}</dt>
                        <dd class="col-6">{{ roiString }}</dd>
                        <dt class="col-6">{{ __('Annualized ROI') }}</dt>
                        <dd class="col-6">{{ aroiString }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { toFormattedCurrency, toFormattedNumber } from '@/shared/lib/i18n';
    import * as toastHelpers from '@/shared/lib/toast';
    import { getTransactionTypeConfig } from '@/shared/lib/helpers';
    import { computeInvestmentReturn } from '@/investments/lib/investmentReturn';

    // The default/reset range starts at the earliest recorded transaction and always ends today -
    // not at the latest transaction, which could be in the past (no recent activity) or a future
    // scheduled instance (a projection, not a result).
    function computeDateBounds(transactions) {
        const allDates = transactions.map((t) => new Date(t.date));

        return {
            from: allDates.length
                ? new Date(Math.min(...allDates))
                : new Date(),
            to: new Date(),
        };
    }

    export default {
        name: 'ResultsCard',
        props: {
            investment: { type: Object, required: true },
            transactions: { type: Array, required: true },
            prices: { type: Array, required: true },
            dateFrom: { type: Date, default: null },
            dateTo: { type: Date, default: null },
        },
        emits: ['update:date-from', 'update:date-to'],
        data() {
            const { from: minDate, to: maxDate } = computeDateBounds(
                this.transactions,
            );
            return {
                locale: window.YAFFA
                    ? window.YAFFA.userSettings.locale
                    : navigator.language,
                internalDateFrom: this.dateFrom || minDate,
                internalDateTo: this.dateTo || maxDate,
            };
        },
        computed: {
            dateFromString: {
                get() {
                    return this.internalDateFrom
                        ? this.internalDateFrom.toISOString().slice(0, 10)
                        : '';
                },
                set(val) {
                    if (!val) return;
                    const d = new Date(val);
                    // If the new date is after internalDateTo, revert to previous valid value and show warning
                    if (this.internalDateTo && d > this.internalDateTo) {
                        toastHelpers.showWarningToast(
                            this.__(
                                'The start date cannot be after the end date.',
                            ),
                        );

                        // Force the input value to revert to the last valid value
                        this.$nextTick(() => {
                            // Find the input and set its value back to the valid one
                            const input =
                                this.$el.querySelector('input[type="date"]');
                            if (input)
                                input.value = this.internalDateFrom
                                    .toISOString()
                                    .slice(0, 10);
                        });
                        return;
                    }
                    this.internalDateFrom = d;
                    this.$emit('update:date-from', d);
                },
            },
            dateToString: {
                get() {
                    return this.internalDateTo
                        ? this.internalDateTo.toISOString().slice(0, 10)
                        : '';
                },
                set(val) {
                    if (!val) return;

                    const d = new Date(val);
                    if (this.internalDateFrom && d < this.internalDateFrom) {
                        toastHelpers.showWarningToast(
                            this.__(
                                'The end date cannot be before the start date.',
                            ),
                        );

                        this.$nextTick(() => {
                            // Find the second input and set its value back to the valid one
                            const inputs =
                                this.$el.querySelectorAll('input[type="date"]');
                            if (inputs && inputs[1])
                                inputs[1].value = this.internalDateTo
                                    .toISOString()
                                    .slice(0, 10);
                        });
                        return;
                    }
                    this.internalDateTo = d;
                    this.$emit('update:date-to', d);
                },
            },
            // Total economic return of the position over the selected period - see
            // resources/js/investments/lib/investmentReturn.js for the full method (Modified
            // Dietz, adapted so sale proceeds/dividends count as return rather than a withdrawal).
            investmentReturn() {
                return computeInvestmentReturn({
                    transactions: this.transactions,
                    prices: this.prices,
                    dateFrom: this.internalDateFrom,
                    dateTo: this.internalDateTo,
                    getTypeConfig: getTransactionTypeConfig,
                });
            },
            summary() {
                const r = this.investmentReturn;
                const toNum = (d) => (d == null ? null : d.toNumber());

                return {
                    Buying: toNum(r.buying),
                    Selling: toNum(r.selling),
                    Added: toNum(r.added),
                    Removed: toNum(r.removed),
                    Dividend: toNum(r.dividend),
                    Commission: toNum(r.commission),
                    Taxes: toNum(r.taxes),
                    Quantity: toNum(r.closingQuantity),
                    Value: toNum(r.closingValue),
                    Result: toNum(r.gain),
                };
            },
            roi() {
                return this.investmentReturn.roi;
            },
            roiString() {
                return this.roi == null
                    ? '—'
                    : (this.roi * 100).toFixed(2) + '%';
            },
            aroi() {
                if (this.roi == null) return null;
                const years = this.calculateYears(
                    this.internalDateTo,
                    this.internalDateFrom,
                );
                if (years <= 0) return 0;
                const base = 1 + this.roi;
                // CAGR is undefined for a >100% cumulative loss (no real root); report as a total loss.
                return base > 0 ? Math.pow(base, 1 / years) - 1 : -1;
            },
            aroiString() {
                return this.aroi == null
                    ? '—'
                    : (this.aroi * 100).toFixed(2) + '%';
            },
        },
        watch: {
            dateFrom(val) {
                if (val) this.internalDateFrom = val;
            },
            dateTo(val) {
                if (val) this.internalDateTo = val;
            },
            internalDateFrom(val) {
                if (
                    val &&
                    this.internalDateTo &&
                    val instanceof Date &&
                    this.internalDateTo instanceof Date &&
                    !isNaN(val) &&
                    !isNaN(this.internalDateTo) &&
                    val > this.internalDateTo
                ) {
                    toastHelpers.showWarningToast(
                        this.__('The start date cannot be after the end date.'),
                    );

                    // Only auto-correct if internalDateTo is valid
                    this.internalDateFrom = new Date(this.internalDateTo);
                    this.$emit('update:date-from', this.internalDateTo);
                }
            },
            internalDateTo(val) {
                if (
                    val &&
                    this.internalDateFrom &&
                    val instanceof Date &&
                    this.internalDateFrom instanceof Date &&
                    !isNaN(val) &&
                    !isNaN(this.internalDateFrom) &&
                    val < this.internalDateFrom
                ) {
                    toastHelpers.showWarningToast(
                        this.__(
                            'The end date cannot be before the start date.',
                        ),
                    );

                    // Only auto-correct if internalDateFrom is valid
                    this.internalDateTo = new Date(this.internalDateFrom);
                    this.$emit('update:date-to', this.internalDateFrom);
                }
            },
            // investmentReturn is a freshly computed object on every recalculation (date change,
            // or the transactions/prices props updating), so this fires on every recompute, not
            // just the first time it becomes unresolved.
            investmentReturn(val) {
                if (val.roi === null) {
                    toastHelpers.showWarningToast(
                        this.__(
                            'Result and ROI cannot be calculated for this period: no price is known at or before the start date, and the investment already held a position then. Try a later start date, or add an earlier price.',
                        ),
                    );
                }
            },
        },
        methods: {
            toFormattedCurrency,
            formatQuantity(value) {
                if (value === 0) return '0';
                return toFormattedNumber(value, this.locale, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 4,
                });
            },
            resetDates() {
                const { from, to } = computeDateBounds(this.transactions);
                this.internalDateFrom = from;
                this.internalDateTo = to;
            },
            // Fractional years (Actual/365.25), not whole years - a sub-year interval must still
            // annualize (e.g. a 3-month span isn't "0 years"), and longer spans like 1.5 or 2.9
            // years must use their exact fraction, not be floored to the nearest whole year.
            calculateYears(to, from) {
                const msPerDay = 24 * 60 * 60 * 1000;
                const msPerYear = 365.25 * msPerDay;
                return (to - from) / msPerYear;
            },
        },
    };
</script>
