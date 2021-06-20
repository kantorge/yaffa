<template>
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Transaction items</h3>
            <div class="box-tools">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info itemListCollapse" title="Collapse all items"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info itemListShow" title="Expand items with data"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info itemListExpand" title="Expand all items"><i class="fa fa-arrows-alt"></i></button>
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
                    @updateItemAmount="updateItemAmount(index, $event)"
                    :index="index"
                    :amount="item.amount ? Number(item.amount) : null"
                    :category_id="item.category_id ? Number(item.category_id) : null"
                    :category="item.category"
                    :tags="item.tags"
                    :currency="currency">
                </transaction-item>
            </div>
        </div>
        <!-- /.box-body -->

        <div class="box-footer">
            <div class="box-tools pull-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-info itemListCollapse" title="Collapse all items"><i class="fa fa-compress"></i></button>
                    <button type="button" class="btn btn-sm btn-info itemListShow" title="Expand items with data"><i class="fa fa-expand"></i></button>
                    <button type="button" class="btn btn-sm btn-info itemListExpand" title="Expand all items"><i class="fa fa-arrows-alt"></i></button>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-success"
                    @click="this.$emit('addTransactionItem')"
                    title="New transaction item"><i class="fa fa-plus"></i></button>
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
        },
        data() {
            return {}
        },

        mounted() {
            // Item list collapse and expand functionality
            $(".itemListCollapse").on('click', function(){
                $(".transaction_item_row").find(".transaction_detail_container").hide();
            });
            $(".itemListShow").on('click', function(){
                $(".transaction_item_row:not(#transaction_item_prototype)").each(function() {
                if(   $(this).find("div.transaction_detail_container input.transaction_item_comment").val() != ""
                    || $(this).find("div.transaction_detail_container select").select2('data').length > 0) {
                        $(this).find(".transaction_detail_container").show();
                    } else {
                        $(this).find(".transaction_detail_container").hide();
                    }
                });
            });
            $(".itemListExpand").on('click', function(){
                $(".transaction_item_row").find(".transaction_detail_container").show();
            });

            // Click the selective show button once, to set up initial view
            document.querySelector('.itemListShow').click();
        },

        methods: {
            // Remove the provided item from transaction items
            removeTransactionItem(index) {
                this.transactionItems.splice(index, 1);
            },

            // Update transaction item amount with value receeived from child component
            updateItemAmount(index, value) {
                this.transactionItems[index].amount = value;
            },
        }
    }
</script>
