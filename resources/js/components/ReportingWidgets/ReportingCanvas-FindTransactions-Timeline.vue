<template>
    <div class="row mb-3">
        <div class="col-12">
            <monthly-timeline
                :transactions="transactions"
                :busy="busy"
            ></monthly-timeline>
        </div>
    </div>
</template>

<script>

import { __ as translator } from "../../helpers";

// Import all required reporting widgets
import MonthlyTimeline from "./MonthlyTimeline.vue";
export default {
    name: 'ReportingCanvasFindTransactionsTimeline',
    components: {
        'monthly-timeline': MonthlyTimeline,
    },
    props: {
        transactions: {
            type: Array,
            required: false,
            default: () => []
        },
        busy: {
            type: Boolean,
            required: true
        }
    },
    methods: {
        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
    },
    mounted() {}
}
</script>
