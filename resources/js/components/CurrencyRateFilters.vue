<template>
    <div class="card mb-3">
        <div class="card-header">
            <div
                class="card-title collapse-control"
                data-coreui-toggle="collapse"
                data-coreui-target="#cardFilters"
            >
                <i class="fa fa-angle-down"></i>
                {{ __('Filters') }}
            </div>
        </div>
        <div class="collapse card-body show" aria-expanded="true" id="cardFilters">
            <div class="mb-3">
                <label for="table_filter_search_text" class="form-label">{{ __('Search') }}</label>
                <input
                    type="text"
                    class="form-control"
                    id="table_filter_search_text"
                    :placeholder="__('Search')"
                    v-model="searchText"
                    @input="onSearchChange"
                >
            </div>
            <div class="mb-0" ref="dateRangePicker">
                <div class="row">
                    <div class="col-6">
                        <label for="date_from" class="form-label">{{ __('Date from') }}</label>
                        <input
                            type="text"
                            class="form-control"
                            name="date_from"
                            id="date_from"
                            :placeholder="__('Select date')"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-6">
                        <label for="date_to" class="form-label">{{ __('Date to') }}</label>
                        <input
                            type="text"
                            class="form-control"
                            name="date_to"
                            id="date_to"
                            :placeholder="__('Select date')"
                            autocomplete="off"
                        >
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <select
                            id="dateRangePickerPresets"
                            class="form-select"
                            @change="onPresetSelect"
                        >
                            <option
                                v-for="option in presetSelectorOptions"
                                :key="option.value"
                                :value="option.value"
                            >{{ __(option.label) }}</option>
                        </select>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-12">
                        <button
                            class="btn btn-sm btn-outline-danger w-100"
                            @click="clearSelection"
                            :title="__('Clear selection')"
                        >
                            <i class="fa fa-fw fa-times"></i>
                            {{ __('Clear selection') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';

export default {
    name: 'CurrencyRateFilters',
    emits: ['date-change', 'search-change'],
    data() {
        return {
            dateFrom: null,
            dateTo: null,
            searchText: '',
            dateRangePicker: null,
            presetSelectorOptions: [
                {
                    value: 'placeholder',
                    label: 'Select preset',
                    callback: function () {
                        this.clearSelection();
                    },
                },
                {
                    value: 'thisMonth',
                    label: 'This month',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), now.getMonth(), 1),
                            new Date(now.getFullYear(), now.getMonth() + 1, 0)
                        );
                    },
                },
                {
                    value: 'thisQuarter',
                    label: 'This quarter',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1),
                            new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3 + 3, 0)
                        );
                    },
                },
                {
                    value: 'thisYear',
                    label: 'This year',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), 0, 1),
                            new Date(now.getFullYear(), 11, 31)
                        );
                    },
                },
                {
                    value: 'thisMonthToDate',
                    label: 'This month to date',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), now.getMonth(), 1),
                            now
                        );
                    },
                },
                {
                    value: 'thisQuarterToDate',
                    label: 'This quarter to date',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1),
                            now
                        );
                    },
                },
                {
                    value: 'thisYearToDate',
                    label: 'This year to date',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), 0, 1),
                            now
                        );
                    },
                },
                {
                    value: 'previousMonth',
                    label: 'Previous month',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), now.getMonth() - 1, 1),
                            new Date(now.getFullYear(), now.getMonth(), 0)
                        );
                    },
                },
                {
                    value: 'previousMonthToDate',
                    label: 'Previous month to date',
                    callback: function () {
                        const now = new Date();
                        this.dateRangePicker.setDates(
                            new Date(now.getFullYear(), now.getMonth() - 1, 1),
                            now
                        );
                    },
                },
            ],
        };
    },
    mounted() {
        this.dateRangePicker = new DateRangePicker(
            this.$refs.dateRangePicker,
            {
                allowOneSidedRange: true,
                weekStart: 1,
                todayButton: true,
                todayButtonMode: 1,
                todayHighlight: true,
                language: window.YAFFA.language,
                format: 'yyyy-mm-dd',
                autohide: true,
                buttonClass: 'btn',
            }
        );

        // Listen for date changes
        const dateFromInput = this.$refs.dateRangePicker.querySelector('[name="date_from"]');
        const dateToInput = this.$refs.dateRangePicker.querySelector('[name="date_to"]');

        dateFromInput.addEventListener('changeDate', this.onDateChange);
        dateToInput.addEventListener('changeDate', this.onDateChange);
    },
    beforeUnmount() {
        if (this.dateRangePicker) {
            this.dateRangePicker.destroy();
        }
    },
    methods: {
        clearSelection() {
            this.dateRangePicker.setDates({ clear: true }, { clear: true });
            this.dateFrom = null;
            this.dateTo = null;

            // Reset the preset select
            const select = document.getElementById('dateRangePickerPresets');
            select.selectedIndex = 0;

            this.$emit('date-change', { dateFrom: null, dateTo: null });
        },
        onDateChange() {
            if (!this.dateRangePicker) {
                return;
            }

            const dates = this.dateRangePicker.getDates('yyyy-mm-dd');
            this.dateFrom = dates[0] || null;
            this.dateTo = dates[1] || null;
            this.$emit('date-change', { dateFrom: this.dateFrom, dateTo: this.dateTo });
        },
        onPresetSelect(event) {
            const value = event.target.value;
            const option = this.presetSelectorOptions.find(option => option.value === value);

            if (option) {
                option.callback.call(this);
                // Trigger date change event
                this.onDateChange();
            }
        },
        onSearchChange() {
            this.$emit('search-change', this.searchText);
        },
        __: function (string, replace) {
            return window.__(string, replace);
        },
    },
};
</script>
