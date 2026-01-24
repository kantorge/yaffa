<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Transaction items') }}
      </div>
      <div>
        <div class="btn-group d-sm-none">
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Collapse all items')"
            @click="itemListCollapse"
          >
            <i class="fa fa-compress"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Expand items with data')"
            @click="itemListShow"
          >
            <i class="fa fa-expand"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Expand all items')"
            @click="itemListExpand"
          >
            <i class="fa fa-arrows-alt"></i>
          </button>
        </div>
        <button
          type="button"
          class="btn btn-sm btn-success ms-1"
          dusk="button-add-transaction-item"
          @click="this.$emit('addTransactionItem')"
          :title="__('New transaction item')"
          :disabled="!enabled"
        >
          <i class="fa fa-plus"></i>
        </button>
      </div>
    </div>
    <div class="card-body" id="transaction_item_container">
      <div
        class="list-group"
        v-if="enabled"
        v-for="(item, index) in transactionItems"
        :key="item.id"
      >
        <transaction-item
          @removeItem="removeItem(index)"
          @update:amount="updateItemAmount(index, $event)"
          @update:category_id="updateItemCategory(index, $event)"
          @update:tags="updateItemTag(index, $event)"
          @update:comment="updateItemComment(index, $event)"
          :id="item.id"
          :amount="item.amount"
          :category_id="item.category_id ? Number(item.category_id) : null"
          :category="item.category"
          :tags="item.tags || []"
          :comment="item.comment"
          :currencySymbol="currencySymbol"
          :remainingAmount="remainingAmount"
          :payee="payee"
        ></transaction-item>
      </div>
      <div v-if="!enabled">
        {{ __('Transaction items are disabled for this transaction type') }}
      </div>
      <div
        v-if="enabled && transactionItems.length === 0"
        class="text-muted text-italic"
      >
        {{ __('No items added') }}
      </div>
    </div>

    <div class="card-footer" v-if="transactionItems.length > 0">
      <div class="text-end">
        <div class="btn-group d-sm-none">
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Collapse all items')"
            @click="itemListCollapse"
          >
            <i class="fa fa-compress"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Expand items with data')"
            @click="itemListShow"
          >
            <i class="fa fa-expand"></i>
          </button>
          <button
            type="button"
            class="btn btn-sm btn-info"
            :title="__('Expand all items')"
            @click="itemListExpand"
          >
            <i class="fa fa-arrows-alt"></i>
          </button>
        </div>
        <button
          type="button"
          class="btn btn-sm btn-success ms-1"
          @click="this.$emit('addTransactionItem')"
          :title="__('New transaction item')"
          :disabled="!enabled"
        >
          <span class="fa fa-plus"></span>
        </button>
      </div>
    </div>
  </div>
</template>

<script>
  import TransactionItem from './TransactionItem.vue';
  import { __ } from '@/helpers';

  export default {
    components: {
      'transaction-item': TransactionItem,
    },
    props: {
      transactionItems: Array,
      currencySymbol: String,
      remainingAmount: Number,
      payee: [Number, String],
      enabled: {
        type: Boolean,
        default: true,
      },
    },

    emits: ['addTransactionItem'],

    data() {
      return {};
    },

    mounted() {
      // Set initial state of detail visibility
      this.itemListShow();
    },

    methods: {
      // Remove the provided item from transaction items
      removeItem(index) {
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
        $('.transaction_item_row')
          .find('.transaction_detail_container')
          .addClass('d-xs-none');
      },

      itemListShow() {
        $('.transaction_item_row').each(function () {
          if (
            $(this)
              .find(
                'div.transaction_detail_container input.transaction_item_comment',
              )
              .val() != '' ||
            $(this)
              .find('div.transaction_detail_container select')
              .select2('data').length > 0
          ) {
            $(this)
              .find('.transaction_detail_container')
              .removeClass('d-xs-none');
          } else {
            $(this).find('.transaction_detail_container').addClass('d-xs-none');
          }
        });
      },

      itemListExpand() {
        $('.transaction_item_row')
          .find('.transaction_detail_container')
          .removeClass('d-xs-none');
      },
      __,
    },
  };
</script>
