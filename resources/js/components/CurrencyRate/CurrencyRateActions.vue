<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardActions"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Actions') }}
      </div>
    </div>
    <ul
      class="list-group list-group-flush collapse show"
      aria-expanded="true"
      id="cardActions"
    >
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <button
          class="btn btn-link text-decoration-none p-0 add-currency-rate-button"
          @click="addNewRate"
          :title="__('Add new rate')"
        >
          {{ __('Add new rate') }}
        </button>
        <button
          class="btn btn-xs btn-primary add-currency-rate-button"
          @click="addNewRate"
          :title="__('Add new rate')"
        >
          <span class="fa fa-fw fa-plus"></span>
        </button>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <button
          class="btn btn-link text-decoration-none p-0"
          @click="loadMissingRates"
          :title="__('Load missing rates')"
          :disabled="isLoadingMissing"
        >
          {{ __('Load missing rates') }}
        </button>
        <button
          class="btn btn-xs btn-success"
          @click="loadMissingRates"
          :title="__('Load missing rates')"
          :disabled="isLoadingMissing"
        >
          <span
            v-if="isLoadingMissing"
            class="fa fa-fw fa-spinner fa-spin"
          ></span>
          <span v-else class="fa fa-fw fa-cloud-download"></span>
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '../../helpers';
  import * as toastHelpers from '../../toast';

  export default {
    name: 'CurrencyRateActions',
    props: {
      fromCurrency: {
        type: Object,
        required: true,
      },
      dateFrom: {
        type: String,
        default: null,
      },
      dateTo: {
        type: String,
        default: null,
      },
    },
    emits: ['add-rate', 'rates-loaded'],
    data() {
      return {
        isLoadingMissing: false,
      };
    },
    methods: {
      addNewRate() {
        this.$emit('add-rate');
      },
      async loadMissingRates() {
        if (this.isLoadingMissing) {
          return;
        }

        this.isLoadingMissing = true;

        toastHelpers.showLoaderToast(
          this.__('Loading missing rates...'),
          'toast-loading-missing-rates',
        );

        try {
          // Call the existing endpoint to retrieve missing rates
          await window.axios.get(
            window.route('api.currency-rate.retrieveMissing', {
              currency: this.fromCurrency.id,
            }),
          );

          toastHelpers.showSuccessToast(
            this.__('Missing rates loaded successfully'),
          );

          // Emit event to parent to reload data
          this.$emit('rates-loaded');
        } catch (error) {
          toastHelpers.showErrorToast(
            error.response?.data?.message ||
              this.__('Failed to load missing rates'),
          );
        } finally {
          this.isLoadingMissing = false;
          toastHelpers.hideToast('.toast-loading-missing-rates');
        }
      },
      __,
    },
  };
</script>
