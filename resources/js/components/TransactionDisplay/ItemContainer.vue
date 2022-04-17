<template>
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Transaction items</h3>
            <div class="box-tools">
                <div class="btn-group d-sm-none">
                    <button type="button" class="btn btn-sm btn-info" title="Collapse all items" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand items with data" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand all items" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
                </div>
            </div>
        </div>
        <!-- /.box-header -->

        <div class="box-body" id="transaction_item_container">
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
            <div v-if="transactionItems.length === 0">No items added</div>
        </div>
        <!-- /.box-body -->

        <div class="box-footer d-sm-none" v-if="transactionItems.length > 0">
            <div class="box-tools pull-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info" title="Collapse all items" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand items with data" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand all items" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
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
                $(".transaction_item_row").find(".transaction_detail_container").addClass('d-xs-none');
            },

            itemListShow() {
                // Loop all transaction item components and show or hide if data-has-details attribute is true or false
                $(".transaction_item_row").each(function() {
                    if ($(this).data('has-details')) {
                        $(this).find(".transaction_detail_container").removeClass('d-xs-none');
                    } else {
                        $(this).find(".transaction_detail_container").addClass('d-xs-none');
                    }
                });
            },

            itemListExpand() {
                $(".transaction_item_row").find(".transaction_detail_container").removeClass('d-xs-none');
            }
        }
    }
</script>

<style scoped>
    @media (min-width: 576px) {
        .d-sm-none {
            display: none;
        }
    }
</style>
