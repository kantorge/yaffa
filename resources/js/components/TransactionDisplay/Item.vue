<template>
    <div class="list-group-item transaction_item_row">
        <div class="row">
            <div class="col-xs-12 col-sm-4">
                <label>Category</label>
                {{ category.full_name }}
            </div>
            <div class="col-xs-12 col-sm-2">
                <label>
                    Amount
                    <span v-if="currency">({{currency}})</span>
                </label>
                <div class="input-group">
                    {{ amount }}
                </div>
            </div>
            <div class="col-xs-12 col-sm-3 transaction_detail_container d-xs-none">
                <label>Tags</label>
                <span
                  class="label label-info"
                  v-for="tag in tags"
                  :key="tag.id"
                >
                    {{ tag.name }}
                </span>
            </div>
            <div class="col-xs-12 col-sm-2 transaction_detail_container d-xs-none">
                <label>Comment</label>
                {{ comment }}
            </div>
            <div class="col-xs-12 col-sm-1">
                <button
                type="button"
                class="btn btn-sm btn-info d-sm-none"
                title="Show item details"
                @click="toggleItemDetails"
            ><span class="fa fa-edit"></span></button>
            </div>
        </div>
    </div>
</template>

<script>
    export default {
        components: {},

        props: {
            id: Number,
            amount: [Number, String],
            category: Object,
            currency: String,
            comment: String,
            tags: Array,
        },

        data() {
            return {};
        },

        mounted() {},

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
    }

    span.label {
        margin: .25em;
    }
</style>
