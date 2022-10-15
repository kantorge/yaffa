<template>
    <div class="list-group-item transaction_item_row" :data-has-details="tags.length > 0 || !!comment">
        <div class="row">
            <button
                type="button"
                class="btn btn-sm btn-info d-sm-none"
                :title="__('Show item details')"
                @click="toggleItemDetails"
            ><span class="fa fa-eye"></span></button>

            <div class="col-xs-12 col-sm-4">
                <label>
                    {{ __('Category') }}
                </label>
                <span class="ml-1" :class="(category.full_name ? '' : 'text-muted')">
                    {{ category.full_name || "Not set" }}
                </span>
            </div>
            <div class="col-xs-12 col-sm-2">
                <label>
                    {{ __('Amount') }}
                </label>
                <span class="ml-1">
                    {{ amount.toLocalCurrency(currency, false) }}
                </span>
            </div>
            <div class="col-xs-12 col-sm-3 transaction_detail_container d-xs-none">
                <label>
                    {{ __('Tags') }}
                </label>
                <span v-if="tags.length > 0">
                    <span
                        class="ml-1 label label-info"
                        v-for="tag in tags"
                        :key="tag.id"
                    >
                        {{ tag.name }}
                    </span>
                </span>
                <span v-else class="text-muted">
                    {{ __('Not set') }}
                </span>
            </div>
            <div class="col-xs-12 col-sm-2 transaction_detail_container d-xs-none">
                <label>
                    {{ __('Comment') }}
                </label>
                <span class="ml-1" :class="(comment ? '' : 'text-muted')">
                    {{ comment || "Not set" }}
                </span>
            </div>
            <div class="col-xs-12 col-sm-1">

            </div>
        </div>
    </div>
</template>

<script>
    export default {
        components: {},

        props: {
            id: Number,
            amount: Number,
            category: Object,
            currency: Object,
            comment: String,
            tags: Array,
        },

        methods: {
            // Toggle the visibility of event details (comment / tags)
            toggleItemDetails() {
                $(this.$el).find(".transaction_detail_container").toggleClass('d-xs-none');
            },
        },
    }
</script>

<style scoped>
    @media (min-width: 576px) {
        .transaction_item_row label {
            display: block;
        }

        .d-sm-none {
            display: none;
        }
    }
    @media (max-width: 575.98px) {
        .d-xs-none {
            display: none;
        }

        .ml-1 {
            margin-left: .25em;
        }
    }

    /* Float show button to upper right corner of container */
    .transaction_item_row button {
        position: absolute;
        top: .25em;
        right: .25em;
        z-index: 10;
    }
</style>
