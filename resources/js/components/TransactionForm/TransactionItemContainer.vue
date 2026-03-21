<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title d-flex align-items-center gap-2">
        {{ __('Transaction items') }}
        <span
          v-if="autoMergeEnabled"
          class="text-muted"
          :title="
            __(
              'Auto-merge is enabled: items with the same category, same tags, and no comment will be merged automatically.',
            )
          "
        >
          <i class="fa fa-compress-alt fa-sm"></i>
        </span>
        <button
          v-if="canApplySuggestions"
          type="button"
          class="btn btn-sm btn-outline-primary"
          @click="applyStandardTransactionItems"
          :title="
            __('Apply standard transaction items based on past statistics')
          "
          :disabled="isLoadingSuggestions"
        >
          <i
            :class="
              isLoadingSuggestions ? 'fa fa-spinner fa-spin' : 'fa fa-magic'
            "
          ></i>
          {{ __('Apply standard transaction items') }}
        </button>
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
          @update:learnRecommendation="
            updateItemLearnRecommendation(index, $event)
          "
          :id="item.id"
          :amount="item.amount"
          :category_id="item.category_id ? Number(item.category_id) : null"
          :category_full_name="item.category_full_name || null"
          :recommended_category_id="item.recommended_category_id || null"
          :recommended_category_full_name="
            item.recommended_category_full_name || null
          "
          :description="item.description || null"
          :match_type="item.match_type || null"
          :confidence_score="item.confidence_score || null"
          :tags="item.tags || []"
          :comment="item.comment"
          :currencySymbol="currencySymbol"
          :remainingAmount="remainingAmount"
          :payee="payee"
          :dropdown-parent-selector="dropdownParentSelector"
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
  import { __ } from '@/i18n';
  import Swal from 'sweetalert2';
  import * as toastHelpers from '@/toast';
  import { getPayeeCategoryStats } from '@/payee/payee-stats-api';

  export default {
    components: {
      'transaction-item': TransactionItem,
    },
    props: {
      transactionItems: Array,
      currencySymbol: String,
      remainingAmount: Number,
      payee: [Number, String],
      transactionType: {
        type: String,
        default: null,
      },
      amountFrom: {
        type: Number,
        default: null,
      },
      enabled: {
        type: Boolean,
        default: true,
      },
      dropdownParentSelector: {
        type: String,
        default: 'body',
      },
    },

    emits: ['addTransactionItem'],

    computed: {
      autoMergeEnabled() {
        return !!window.YAFFA?.userSettings
          ?.auto_merge_standard_transaction_items;
      },
      canApplySuggestions() {
        const amountFrom = Number(this.amountFrom);

        return (
          this.enabled &&
          Number(this.payee) > 0 &&
          Number.isFinite(amountFrom) &&
          amountFrom > 0
        );
      },
    },

    data() {
      return {
        isLoadingSuggestions: false,
      };
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

      // Update transaction item learn recommendation flag with value received from child component
      updateItemLearnRecommendation(index, value) {
        this.transactionItems[index].learnRecommendation =
          value.learnRecommendation;
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

      async applyStandardTransactionItems() {
        if (this.transactionItems.length > 0) {
          const result = await Swal.fire({
            title: __('Replace items?'),
            text: __(
              'Existing transaction items will be removed and overwritten.',
            ),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: __('Replace'),
            cancelButtonText: __('Cancel'),
          });

          if (!result.isConfirmed) {
            return;
          }
        }

        try {
          this.isLoadingSuggestions = true;

          const data = await getPayeeCategoryStats(
            Number(this.payee),
            this.transactionType,
          );
          const stats = Array.isArray(data?.categories) ? data.categories : [];
          const deferredCategoryIds = Array.isArray(data?.deferred_category_ids)
            ? data.deferred_category_ids.map((id) => Number(id))
            : [];

          const newItems = this.buildItemsFromStats(stats, deferredCategoryIds);

          if (newItems.length === 0) {
            await Swal.fire({
              title: __('No statistics available'),
              text: __(
                'No usable transaction item statistics were found for this payee.',
              ),
              icon: 'warning',
              confirmButtonText: __('OK'),
            });

            return;
          }

          this.transactionItems.splice(
            0,
            this.transactionItems.length,
            ...newItems,
          );

          toastHelpers.showSuccessToast(
            __('Standard transaction items applied successfully.'),
          );
        } catch (error) {
          toastHelpers.showErrorToast(
            __('Failed to apply standard transaction items.'),
          );
          // Keep this for debugging in browser console without breaking UX.
          console.error('Error applying standard transaction items', error);
        } finally {
          this.isLoadingSuggestions = false;
        }
      },

      buildItemsFromStats(stats, deferredCategoryIds) {
        if (!Array.isArray(stats) || stats.length === 0) {
          return [];
        }

        const normalizedStats = stats
          .map((stat) => ({
            category_id: Number(stat.category_id),
            category_full_name: stat.category_full_name || null,
            usage_count: Number(stat.usage_count),
          }))
          .filter(
            (stat) =>
              Number.isInteger(stat.category_id) &&
              stat.category_id > 0 &&
              Number.isFinite(stat.usage_count) &&
              stat.usage_count > 0,
          )
          .filter((stat) => !deferredCategoryIds.includes(stat.category_id));

        if (normalizedStats.length === 0) {
          return [];
        }

        const totalUsageCount = normalizedStats.reduce(
          (sum, stat) => sum + stat.usage_count,
          0,
        );

        if (totalUsageCount <= 0) {
          return [];
        }

        const totalAmount = this.roundAmount(Number(this.amountFrom));
        const mostUsedStat = normalizedStats[0];
        const itemStartId = this.getNextItemId();

        const items = normalizedStats.map((stat, index) => ({
          id: itemStartId + index,
          learnRecommendation: true,
          category_id: stat.category_id,
          category_full_name: stat.category_full_name,
          amount: 0,
          tags: [],
          comment: null,
        }));

        let allocatedAmount = 0;

        items.forEach((item, index) => {
          if (item.category_id === mostUsedStat.category_id) {
            return;
          }

          const usageCount = normalizedStats[index].usage_count;
          const proportionalAmount = this.roundAmount(
            (totalAmount * usageCount) / totalUsageCount,
          );

          item.amount = proportionalAmount;
          allocatedAmount += proportionalAmount;
        });

        const remainderAmount = this.roundAmount(totalAmount - allocatedAmount);
        const mostUsedItem = items.find(
          (item) => item.category_id === mostUsedStat.category_id,
        );

        if (mostUsedItem !== undefined) {
          mostUsedItem.amount = this.roundAmount(
            mostUsedItem.amount + remainderAmount,
          );
        }

        return items.filter((item) => item.amount > 0);
      },

      roundAmount(amount) {
        return Number(Number(amount).toFixed(4));
      },

      getNextItemId() {
        const maxExistingId = this.transactionItems.reduce((maxId, item) => {
          const numericId = Number(item?.id);

          if (Number.isInteger(numericId) && numericId > maxId) {
            return numericId;
          }

          return maxId;
        }, 0);

        return maxExistingId + 1;
      },

      __,
    },
  };
</script>
