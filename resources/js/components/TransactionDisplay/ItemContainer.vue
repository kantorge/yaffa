<template>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <div class="card-title">
                {{ __('Transaction items') }}
            </div>
            <div>
                <div class="btn-group d-sm-none">
                    <button type="button" class="btn btn-sm btn-info" :title="__('Collapse all items')" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" :title="__('Expand items with data')" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" :title="__('Expand all items')" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
                </div>
            </div>
        </div>
        <div class="card-body" id="transaction_item_container">
            <div
                class="list-group"
                v-for="(item) in transactionItems"
                :key="item.id">

                <transaction-item
                    :id="item.id"
                    :amount="Number(item.amount)"
                    :category="item.category || {}"
                    :comment="item.comment"
                    :tags="item.tags || []"
                    :currency="currency"
                ></transaction-item>
            </div>
            <div v-if="transactionItems.length === 0">
                {{ __('No items added') }}
            </div>
        </div>
        <div class="card-footer d-sm-none" v-if="transactionItems.length > 0">
            <div class="text-end">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info" :title="__('Collapse all items')" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" :title="__('Expand items with data')" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" :title="__('Expand all items')" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import TransactionItem from './Item.vue'

    export default {
        components: {
            'transaction-item': TransactionItem,
        },
        props: {
            transactionItems: Array,
            currency: Object,
        },

        mounted() {
            // Set initial state of detail visibility
            this.itemListShow();
        },

        methods: {
            // Item list collapse and expand functionality
            itemListCollapse() {
                $(".transaction_item_row").find(".transaction_detail_container").addClass('d-none');
            },

            itemListShow() {
                // Loop all transaction item components and show or hide if data-has-details attribute is true or false
                $(".transaction_item_row").each(function() {
                    if ($(this).data('has-details')) {
                        $(this).find(".transaction_detail_container").removeClass('d-none');
                    } else {
                        $(this).find(".transaction_detail_container").addClass('d-none');
                    }
                });
            },

            itemListExpand() {
                $(".transaction_item_row").find(".transaction_detail_container").removeClass('d-none');
            }
        }
    }
</script>
