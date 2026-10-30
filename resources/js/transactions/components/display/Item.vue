<template>
  <div
    class="list-group-item transaction_item_row"
    :data-has-details="hasDetails"
  >
    <div class="row">
      <div v-if="hasDetails">
        <button
          type="button"
          class="btn btn-sm btn-info d-sm-none"
          :title="__('Show item details')"
          @click="toggleItemDetails"
        >
          <span class="fa fa-eye"></span>
        </button>
      </div>

      <!-- On mobile, labels are dropped: the category and the formatted amount are self-explanatory -->
      <div class="col col-sm-4">
        <dt class="d-none d-sm-block">
          {{ __('Category') }}
        </dt>
        <dd class="mb-1 mb-sm-3" :class="{ 'not-set': !category.full_name }">
          {{ category.full_name || __('Not set') }}
        </dd>
      </div>
      <div
        class="col-auto col-sm-2 text-end text-sm-start text-nowrap"
        :class="{ 'pe-5': hasDetails }"
      >
        <dt class="d-none d-sm-block">
          {{ __('Amount') }}
        </dt>
        <dd class="mb-1 mb-sm-3">
          {{ toFormattedCurrency(amount, locale, currency) }}
        </dd>
      </div>
      <!-- Tags and comment: shown on mobile only when toggled, and only if they have a value; icons replace labels -->
      <div
        class="col-12 col-sm-3 transaction_detail_container d-none d-md-block"
      >
        <dt class="d-none d-md-block">
          {{ __('Tags') }}
        </dt>
        <dd v-if="tags.length > 0" class="mb-1 mb-md-3">
          <i
            class="fa fa-tag fa-fw text-muted d-md-none"
            :title="__('Tags')"
          ></i>
          <span
            v-for="tag in tags"
            :key="tag.id"
            class="badge text-bg-info me-1"
          >
            {{ tag.name }}
          </span>
        </dd>
        <dd v-else class="not-set d-none d-md-block">
          {{ __('Not set') }}
        </dd>
      </div>
      <div
        class="col-12 col-sm-2 transaction_detail_container d-none d-md-block"
      >
        <dt class="d-none d-md-block">
          {{ __('Comment') }}
        </dt>
        <dd v-if="comment" class="mb-1 mb-md-3">
          <i
            class="fa fa-comment fa-fw text-muted d-md-none"
            :title="__('Comment')"
          ></i>
          {{ comment }}
        </dd>
        <dd v-else class="not-set d-none d-md-block">
          {{ __('Not set') }}
        </dd>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';

  export default {
    components: {},

    props: {
      id: Number,
      amount: Number,
      category: Object,
      currency: Object,
      comment: String,
      tags: Array,
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
    },

    computed: {
      hasDetails() {
        return this.tags.length > 0 || !!this.comment;
      },
    },

    methods: {
      // Toggle the visibility of event details (comment / tag)
      toggleItemDetails() {
        $(this.$el).find('.transaction_detail_container').toggleClass('d-none');
      },
      toFormattedCurrency(input, locale, currencySettings) {
        return toFormattedCurrency(input, locale, currencySettings);
      },

      __,
    },
  };
</script>

<style scoped>
  /* Float show button to upper right corner of container */
  .transaction_item_row button {
    position: absolute;
    top: 0.25em;
    right: 0.25em;
    z-index: 10;
  }
</style>
