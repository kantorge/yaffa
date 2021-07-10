<template>
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Transaction items</h3>
            <div class="box-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info" title="Collapse all items" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand items with data" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand all items" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    @click="this.$emit('addTransactionItem')"
                    title="New transaction item"><i class="fa fa-plus"></i></button>
            </div>
        </div>
        <!-- /.box-header -->

        <div class="box-body" id="transaction_item_container">
            <div
                class="list-group"
                v-for="(item, index) in transactionItems"
                :key="item.id">

                <transaction-item
                    @removeTransactionItem="removeTransactionItem(index)"
                    @amountChanged="updateItemAmount(index, $event)"
                    @updateItemCategory="updateItemCategory(index, $event)"
                    @updateItemTag="updateItemTag(index, $event)"
                    @updateItemComment="updateItemComment(index, $event)"
                    :index="index"
                    :amount="item.amount"
                    :category_id="item.category_id ? Number(item.category_id) : null"
                    :category="item.category"
                    :tags="item.tags || []"
                    :currency="currency"
                    :remainingAmount="remainingAmount"
                    :payee="payee"
                ></transaction-item>
            </div>
        </div>
        <!-- /.box-body -->

        <div class="box-footer">
            <div class="box-tools pull-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info" title="Collapse all items" @click="itemListCollapse"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand items with data" @click="itemListShow"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info" title="Expand all items" @click="itemListExpand"><i class="fa fa-arrows-alt"></i></button>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    @click="this.$emit('addTransactionItem')"
                    title="New transaction item"><span class="fa fa-plus"></span></button>
            </div>
        </div>
    </div>
    <!-- /.box -->
</template>

<script>
    import TransactionItem from './TransactionItem.vue'

    export default {
        components: {
            'transaction-item': TransactionItem,
        },
        props: {
            transactionItems: Array,
            currency: String,
            remainingAmount: Number,
            payee: [Number, String],
        },
        data() {
            return {}
        },

        mounted() {
            // Set initial state of detail visibility
            this.itemListShow();
        },

        methods: {
            // Remove the provided item from transaction items
            removeTransactionItem(index) {
                this.transactionItems.splice(index, 1);
            },

            // Update transaction item amount with value received from child component
            updateItemAmount(index, value) {
                this.transactionItems[index].amount = value;
            },

            // Update transaction item category with value received from child component
            updateItemCategory(index, value) {
                this.transactionItems[index].category_id = value;
            },

            // Update transaction item tags with value received from child component
            updateItemTag(index, value) {
                this.transactionItems[index].tags = value;
            },

            // Update transaction item comment with value received from child component
            updateItemComment(index, value) {
                this.transactionItems[index].comment = value;
            },

            // Item list collapse and expand functionality
            itemListCollapse() {
                $(".transaction_item_row").find(".transaction_detail_container").hide();
            },

            itemListShow() {
                $(".transaction_item_row:not(#transaction_item_prototype)").each(function() {
                if(   $(this).find("div.transaction_detail_container input.transaction_item_comment").val() != ""
                    || $(this).find("div.transaction_detail_container select").select2('data').length > 0) {
                        $(this).find(".transaction_detail_container").show();
                    } else {
                        $(this).find(".transaction_detail_container").hide();
                    }
                });
            },

            itemListExpand() {
                $(".transaction_item_row").find(".transaction_detail_container").show();
            }
        }
    }
</script>
