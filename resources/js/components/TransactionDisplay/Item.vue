<template>
    <div
        class="list-group-item transaction_item_row"
        :data-has-details="tags.length > 0 || !!comment"
    >
        <div class="row">
            <div>
                <button
                    type="button"
                    class="btn btn-sm btn-info d-sm-none"
                    :title="__('Show item details')"
                    @click="toggleItemDetails"
                ><span class="fa fa-eye"></span></button>
            </div>

            <div class="col-12 col-sm-4">
                <dt>
                    {{ __('Category') }}
                </dt>
                <dd :class="(category.full_name ? '' : 'text-muted')">
                    {{ category.full_name || __("Not set") }}
                </dd>
            </div>
            <div class="col-12 col-sm-2">
                <dt>
                    {{ __('Amount') }}
                </dt>
                <dd>
                    {{ toFormattedCurrency(amount, locale, currency) }}
                </dd>
            </div>
            <div class="col-12 col-sm-3 transaction_detail_container d-none d-md-block">
                <dt>
                    {{ __('Tags') }}
                </dt>
                <dd v-if="tags.length > 0">
                    <span
                        class="badge text-bg-info"
                        v-for="tag in tags"
                        :key="tag.id"
                    >
                        {{ tag.name }}
                    </span>
                </dd>
                <dd v-else class="text-muted text-italic">
                    {{ __('Not set') }}
                </dd>
            </div>
            <div class="col-12 col-sm-2 transaction_detail_container d-none d-md-block">
                <dt>
                    {{ __('Comment') }}
                </dt>
                <dd :class="{ 'text-muted text-italic' : !comment }">
                    {{ comment || __("Not set") }}
                </dd>
            </div>
        </div>
    </div>
</template>

<script>
    import * as helpers from '../../helpers';

    export default {
        components: {
            helpers
        },

        props: {
            id: Number,
            amount: Number,
            category: Object,
            currency: Object,
            comment: String,
            tags: Array,
            locale: {
                type: String,
                default: window.YAFFA.locale,
            }
        },

        methods: {
            // Toggle the visibility of event details (comment / tag)
            toggleItemDetails() {
                $(this.$el).find(".transaction_detail_container").toggleClass('d-none');
            },
            toFormattedCurrency(input, locale, currencySettings) {
                return helpers.toFormattedCurrency(input, locale, currencySettings);
            },

            /**
             * Import the translation helper function.
             */
             __: function (string, replace) {
                return helpers.__(string, replace);
            },
        },
    }
</script>

<style scoped>
    /* Float show button to upper right corner of container */
    .transaction_item_row button {
        position: absolute;
        top: .25em;
        right: .25em;
        z-index: 10;
    }
</style>
