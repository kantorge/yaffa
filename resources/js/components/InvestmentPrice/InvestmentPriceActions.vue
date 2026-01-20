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
          class="btn btn-link text-decoration-none p-0 add-investment-price-button"
          @click="addNewPrice"
          :title="__('Add new investment price')"
        >
          {{ __('Add new investment price') }}
        </button>
        <button
          class="btn btn-xs btn-primary add-investment-price-button"
          @click="addNewPrice"
          :title="__('Add new investment price')"
        >
          <span class="fa fa-fw fa-plus"></span>
        </button>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <button
          class="btn btn-link text-decoration-none p-0"
          @click="loadMissingPrices"
          :title="
            !canLoadPrices
              ? __('No price provider configured for this investment')
              : __('Load missing prices')
          "
          :disabled="!canLoadPrices || isLoadingMissing"
        >
          {{ __('Load missing investment prices') }}
        </button>
        <button
          class="btn btn-xs btn-success"
          @click="loadMissingPrices"
          :title="
            !canLoadPrices
              ? __('No price provider configured for this investment')
              : __('Load missing investment prices')
          "
          :disabled="!canLoadPrices || isLoadingMissing"
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

  export default {
    name: 'InvestmentPriceActions',
    props: {
      investment: {
        type: Object,
        required: true,
      },
    },
    emits: ['add-price', 'prices-loaded'],
    data() {
      return {
        isLoadingMissing: false,
      };
    },
    computed: {
      canLoadPrices() {
        return !!this.investment.investment_price_provider;
      },
    },
    methods: {
      addNewPrice() {
        this.$emit('add-price');
      },
      async loadMissingPrices() {
        if (this.isLoadingMissing || !this.canLoadPrices) {
          return;
        }

        this.isLoadingMissing = true;

        // Show loading toast
        const loadingEvent = new CustomEvent('toast', {
          detail: {
            body: this.__('Loading missing prices...'),
            toastClass: 'bg-info toast-loading-missing-prices',
            delay: Infinity,
          },
        });
        window.dispatchEvent(loadingEvent);

        try {
          // Call the API endpoint to retrieve missing prices
          const response = await window.axios.get(
            window.route('api.investment-price.retrieveMissing', {
              investment: this.investment.id,
            }),
          );

          // Show success toast
          const successEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Success'),
              body: response.data.message,
              toastClass: 'bg-success',
            },
          });
          window.dispatchEvent(successEvent);

          // Emit event to parent to reload data
          this.$emit('prices-loaded');
        } catch (error) {
          // Show error toast
          const errorEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Error'),
              body:
                error.response?.data?.message ||
                this.__('Failed to load missing prices'),
              toastClass: 'bg-danger',
            },
          });
          window.dispatchEvent(errorEvent);
        } finally {
          this.isLoadingMissing = false;

          // Close loading toast
          setTimeout(() => {
            const toastElements = document.querySelectorAll(
              '.toast-loading-missing-prices',
            );

            if (toastElements.length > 0) {
              toastElements.forEach((toastElement) => {
                const toastInstance = new window.bootstrap.Toast(toastElement);
                toastInstance.dispose();
              });
            }
          }, 250);
        }
      },
      __,
    },
  };
</script>
