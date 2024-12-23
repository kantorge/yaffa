<template>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Date') }}
            </div>
            <div>
                <button
                        class="btn btn-sm btn-ghost-danger"
                        id="clearDateSelection"
                        @click="clearSelection"
                        :title="__('Clear selection')"
                >
                    <i class="fa fa-fw fa-times"></i>
                </button>
            </div>
        </div>
        <div class="card-body" ref="dateRangePicker">
            <div class="row">
                <div class="col-6">
                    <label for="date_from" class="form-label">{{ __('Date from') }}</label>
                    <input
                            type="text"
                            class="form-control"
                            name="date_from"
                            id="date_from"
                            :placeholder="__('Select date')"
                            @changeDate="onDateChange"
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
                            @changeDate="onDateChange"
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
                                :value="option.value"
                        >{{ __(option.label) }}</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';
import {__ as translator} from "../helpers";

export default {
    name: 'DateRangeSelector',
    emits: ['update'],
    props: {
        initialDateFrom: {
            type: String,
            default: null,
        },
        initialDateTo: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            dateFrom: this.initialDateFrom,
            dateTo: this.initialDateTo,
            dateRangePicker: null,
            presetSelectorOptions: [
                {
                    value: 'placeholder',
                    label: 'Select preset',
                    callback: function () {
                        this.clearSelection();
                    }
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
                    }
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
                    }
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
                    }
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
                    }
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
                    }
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
                    }
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
                    }
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
                    }
                }
            ]
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

        // Set the initial date range from the props
        this.dateRangePicker.setDates(this.dateFrom, this.dateTo);
    },
    methods: {
        clearSelection() {
            this.dateRangePicker.setDates({clear: true}, {clear: true});
            this.dateFrom = null;
            this.dateTo = null;

            // Make sure to reset the preset select
            const select = document.getElementById('dateRangePickerPresets');
            select.selectedIndex = 0;
        },
        onDateChange() {
            if (!this.dateRangePicker) {
                return;
            }

            const dates = this.dateRangePicker.getDates('yyyy-mm-dd');
            this.dateFrom = dates[0];
            this.dateTo = dates[1];
            this.$emit('update', {dateFrom: this.dateFrom, dateTo: this.dateTo});
        },
        onPresetSelect(event) {
            const value = event.target.value;
            const option = this.presetSelectorOptions.find(option => option.value === value);

            if (option) {
                option.callback.call(this);
                this.$emit('update', {dateFrom: this.dateFrom, dateTo: this.dateTo});
            }
        },
        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
    }
}

</script>
