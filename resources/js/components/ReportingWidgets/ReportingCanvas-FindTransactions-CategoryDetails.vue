<template>
    <div>
        <div class="row mb-5 mt-5">
            <div class="col-12">
                <reporting-withdrawals-by-parent-category
                    :transactions="transactions"
                    :busy="busy"
                ></reporting-withdrawals-by-parent-category>
            </div>
        </div>
        <div class="row mb-5 mt-5">
            <div class="col-12">
                <reporting-deposits-by-parent-category
                    :transactions="transactions"
                    :busy="busy"
                ></reporting-deposits-by-parent-category>
            </div>
        </div>
    </div>
</template>

<script>

import { __ as translator } from "../../helpers";

// Import all required reporting widgets
import WithdrawalsByParentCategory from './WithdrawalsByParentCategory.vue';
import DepositsByParentCategory from "./DepositsByParentCategory.vue";

export default {
    name: 'ReportingCanvasFindTransactionsCategoryDetails',
    components: {
        'reporting-withdrawals-by-parent-category': WithdrawalsByParentCategory,
        'reporting-deposits-by-parent-category': DepositsByParentCategory,
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
