<template>
    <div>
        <div class="row">
            <div class="col-6">
                <reporting-withdrawals-by-parent-category
                    :transactions="transactions"
                ></reporting-withdrawals-by-parent-category>
            </div>
        </div>
    </div>
</template>

<script>

import { __ as translator } from "../../helpers";

// Import all required reporting widgets
import WithdrawalsByParentCategory from './WithdrawalsByParentCategory.vue';

export default {
    name: 'ReportingCanvasFindTransactionsCategoryDetails',
    components: {
        'reporting-withdrawals-by-parent-category': WithdrawalsByParentCategory,
    },
    props: {
        transactions: {
            type: Array,
            required: false,
            default: () => []
        },
    },
    data() {
        return {}
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
