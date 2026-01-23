<template>
  <div class="modal fade" id="investmentPriceModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{
              isEditMode
                ? __('Edit Investment Price')
                : __('Add Investment Price')
            }}
          </h5>
          <button
            type="button"
            class="btn-close"
            data-coreui-dismiss="modal"
            aria-label="Close"
          ></button>
        </div>
        <div class="modal-body">
          <form @submit.prevent="submitForm">
            <div class="mb-3">
              <label for="priceDate" class="form-label">{{ __('Date') }}</label>
              <input
                type="date"
                class="form-control"
                id="priceDate"
                v-model="formData.date"
                :class="{ 'is-invalid': errors.date }"
                required
              />
              <div class="invalid-feedback" v-if="errors.date">
                <div v-if="Array.isArray(errors.date)">
                  <div v-for="error in errors.date" :key="error">
                    {{ error }}
                  </div>
                </div>
                <div v-else>{{ errors.date }}</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="priceValue" class="form-label">
                {{ __('Investment price') }}
                <small class="text-muted" v-if="investment.currency"
                  >({{ investment.currency.iso_code }})</small
                >
              </label>
              <input
                type="number"
                step="0.0001"
                min="0.0000000001"
                class="form-control"
                id="priceValue"
                v-model.number="formData.price"
                :class="{ 'is-invalid': errors.price }"
                required
              />
              <div class="invalid-feedback" v-if="errors.price">
                <div v-if="Array.isArray(errors.price)">
                  <div v-for="error in errors.price" :key="error">
                    {{ error }}
                  </div>
                </div>
                <div v-else>{{ errors.price }}</div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-coreui-dismiss="modal"
            :disabled="isSubmitting"
          >
            <span
              v-if="isSubmitting"
              class="spinner-border spinner-border-sm me-1"
            ></span>
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn btn-primary"
            id="priceSubmit"
            @click="submitForm"
            :disabled="isSubmitting"
          >
            <span
              v-if="isSubmitting"
              class="spinner-border spinner-border-sm me-1"
            ></span>
            {{ isEditMode ? __('Update') : __('Add') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '../../helpers';
  import * as toastHelpers from '../../toast';

  export default {
    name: 'InvestmentPriceModal',
    props: {
      investment: {
        type: Object,
        required: true,
      },
      editPrice: {
        type: Object,
        default: null,
      },
    },
    emits: ['saved', 'close'],
    data() {
      return {
        formData: {
          date: '',
          price: null,
        },
        errors: {},
        isSubmitting: false,
        modal: null,
      };
    },
    computed: {
      isEditMode() {
        return this.editPrice !== null;
      },
    },
    watch: {
      editPrice: {
        immediate: true,
        handler(price) {
          if (price) {
            // Convert Date object to YYYY-MM-DD string format for input type="date"
            if (price.date instanceof Date) {
              this.formData.date = price.date.toISOString().split('T')[0];
            } else {
              this.formData.date = price.date;
            }
            // Ensure price is a number
            this.formData.price =
              typeof price.price === 'string'
                ? parseFloat(price.price)
                : price.price;
          } else {
            this.resetForm();
          }
        },
      },
    },
    mounted() {
      const modalElement = document.getElementById('investmentPriceModal');

      // Use CoreUI Modal instead of Bootstrap Modal
      if (window.coreui && window.coreui.Modal) {
        this.modal = new window.coreui.Modal(modalElement);
      } else {
        this.modal = new window.bootstrap.Modal(modalElement);
      }

      modalElement.addEventListener('hidden.bs.modal', () => {
        this.resetForm();
        this.$emit('close');
      });

      // Also listen for CoreUI modal events
      modalElement.addEventListener('hidden.coreui.modal', () => {
        this.resetForm();
        this.$emit('close');
      });
    },
    methods: {
      show() {
        this.modal.show();
      },
      hide() {
        this.modal.hide();
      },
      resetForm() {
        this.formData = {
          date: '',
          price: null,
        };
        this.errors = {};
      },
      async submitForm() {
        if (this.isSubmitting) {
          return;
        }

        this.errors = {};
        this.isSubmitting = true;

        const data = {
          investment_id: this.investment.id,
          date: this.formData.date,
          price: this.formData.price,
        };

        // Include id when updating for validation rule to work
        if (this.isEditMode) {
          data.id = this.editPrice.id;
        }

        try {
          let response;
          if (this.isEditMode) {
            response = await window.axios.put(
              window.route('api.investment-price.update', {
                investment_price: this.editPrice.id,
              }),
              data,
            );
          } else {
            response = await window.axios.post(
              window.route('api.investment-price.store'),
              data,
            );
          }

          // Emit success event with the price data
          this.$emit('saved', response.data.price, response.data.message);

          // Hide modal
          this.hide();
        } catch (error) {
          if (error.response && error.response.status === 422) {
            // Validation errors
            this.errors = error.response.data.errors;
          } else {
            // Show generic error toast
            toastHelpers.showErrorToast(
              error.response?.data?.message || this.__('An error occurred'),
            );
          }
        } finally {
          this.isSubmitting = false;
        }
      },
      __,
    },
  };
</script>
