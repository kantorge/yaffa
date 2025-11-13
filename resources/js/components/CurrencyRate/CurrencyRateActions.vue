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
          class="btn btn-link text-decoration-none p-0"
          @click="addNewRate"
          :title="__('Add new rate')"
        >
          {{ __('Add new rate') }}
        </button>
        <button
          class="btn btn-xs btn-primary"
          @click="addNewRate"
          :title="__('Add new rate')"
        >
          <span class="fa fa-plus"></span>
        </button>
      </li>
      <li
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a class="" :href="retrieveLatestUrl" :title="__('Load latest rates')">
          {{ __('Load latest rates') }}
        </a>
        <a
          class="btn btn-xs btn-success"
          :href="retrieveLatestUrl"
          :title="__('Load latest rates')"
        >
          <span class="fa fa-cloud-download"></span>
        </a>
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
            class="spinner-border spinner-border-sm"
          ></span>
          <span v-else class="fa fa-cloud-download"></span>
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __ } from '../../helpers';

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
    computed: {
      retrieveLatestUrl() {
        return window.route('currency-rate.retrieveRate', {
          currency: this.fromCurrency.id,
        });
      },
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

        // Show loading toast
        const loadingEvent = new CustomEvent('toast', {
          detail: {
            body: this.__('Loading missing rates...'),
            toastClass: 'bg-info toast-loading-missing-rates',
            delay: Infinity,
          },
        });
        window.dispatchEvent(loadingEvent);

        try {
          // Call the existing endpoint to retrieve missing rates
          await window.axios.get(
            window.route('currency-rate.retrieveMissing', {
              currency: this.fromCurrency.id,
            }),
          );

          // Show success toast
          const successEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Success'),
              body: this.__('Missing rates loaded successfully'),
              toastClass: 'bg-success',
            },
          });
          window.dispatchEvent(successEvent);

          // Emit event to parent to reload data
          this.$emit('rates-loaded');
        } catch (error) {
          // Show error toast
          const errorEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Error'),
              body:
                error.response?.data?.message ||
                this.__('Failed to load missing rates'),
              toastClass: 'bg-danger',
            },
          });
          window.dispatchEvent(errorEvent);
        } finally {
          this.isLoadingMissing = false;

          // Close loading toast
          setTimeout(() => {
            const toastElement = document.querySelector(
              '.toast-loading-missing-rates',
            );
            if (toastElement) {
              const toastInstance = new window.bootstrap.Toast(toastElement);
              toastInstance.hide();
            }
          }, 250);
        }
      },
      __,
    },
  };
</script>
