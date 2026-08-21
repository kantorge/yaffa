<template>
  <div class="modal fade" id="currencyRateModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            {{
              isEditMode ? __('Edit Currency Rate') : __('Add Currency Rate')
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
              <label for="rateDate" class="form-label">{{ __('Date') }}</label>
              <input
                type="date"
                class="form-control"
                id="rateDate"
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
              <label for="rateValue" class="form-label">
                {{ __('Rate') }}
                <small class="text-muted"
                  >({{ fromCurrency.iso_code }} →
                  {{ toCurrency.iso_code }})</small
                >
              </label>
              <input
                type="number"
                step="0.0001"
                min="0.0000000001"
                class="form-control"
                id="rateValue"
                v-model.number="formData.rate"
                :class="{ 'is-invalid': errors.rate }"
                required
              />
              <div class="invalid-feedback" v-if="errors.rate">
                <div v-if="Array.isArray(errors.rate)">
                  <div v-for="error in errors.rate" :key="error">
                    {{ error }}
                  </div>
                </div>
                <div v-else>{{ errors.rate }}</div>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-coreui-dismiss="modal"
          >
            {{ __('Cancel') }}
          </button>
          <button
            type="button"
            class="btn btn-primary"
            @click="submitForm"
            :disabled="isSubmitting"
          >
            <i
              v-if="isSubmitting"
              class="fa fa-spinner fa-spin me-1"
            ></i>
            {{ isEditMode ? __('Update') : __('Add') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { confirmAction } from '@/shared/lib/confirm';

  export default {
    name: 'CurrencyRateModal',
    props: {
      fromCurrency: {
        type: Object,
        required: true,
      },
      toCurrency: {
        type: Object,
        required: true,
      },
      editRate: {
        type: Object,
        default: null,
      },
    },
    emits: ['saved', 'close'],
    data() {
      return {
        formData: {
          date: '',
          rate: null,
        },
        // Snapshot of formData (JSON string) taken whenever the form is in
        // a "clean" state (opened for edit, reset for a new rate) - the
        // dirty check compares the current formData against this.
        originalFormData: null,
        errors: {},
        isSubmitting: false,
        modal: null,
        // Set right before a programmatic hide() so the hide.coreui.modal
        // listener lets it through once without re-running the dirty check.
        forceCloseModal: false,
      };
    },
    computed: {
      isEditMode() {
        return this.editRate !== null;
      },
    },
    watch: {
      editRate: {
        immediate: true,
        handler(rate) {
          if (rate) {
            this.formData.date = rate.date;
            this.formData.rate = rate.rate;
          } else {
            this.resetForm();
          }

          this.originalFormData = JSON.stringify(this.formData);
        },
      },
    },
    mounted() {
      const modalElement = document.getElementById('currencyRateModal');

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

      // Cancelable pre-dismiss hook (backdrop click, Esc, close button, and
      // programmatic hide() alike) - ask for confirmation if there are
      // unsaved changes.
      modalElement.addEventListener('hide.coreui.modal', (event) => {
        if (this.forceCloseModal) {
          this.forceCloseModal = false;
          return;
        }

        if (JSON.stringify(this.formData) === this.originalFormData) {
          return;
        }

        event.preventDefault();

        confirmAction(__('Are you sure you want to discard any changes?'), {
          icon: 'warning',
          confirmButtonText: __('Discard changes'),
        }).then((result) => {
          if (result.isConfirmed) {
            this.hide();
          }
        });
      });
    },
    methods: {
      show() {
        this.modal.show();
      },
      hide() {
        this.forceCloseModal = true;
        this.modal.hide();
      },
      resetForm() {
        this.formData = {
          date: '',
          rate: null,
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
          from_id: this.fromCurrency.id,
          to_id: this.toCurrency.id,
          date: this.formData.date,
          rate: this.formData.rate,
        };

        try {
          let response;
          if (this.isEditMode) {
            response = await window.axios.put(
              this.route('api.v1.currency-rates.update', {
                currencyRate: this.editRate.id,
              }),
              data,
            );
          } else {
            response = await window.axios.post(
              this.route('api.v1.currency-rates.store'),
              data,
            );
          }

          // Emit success event with the rate data
          this.$emit('saved', response.data.rate, response.data.message);

          // Hide modal
          this.hide();
        } catch (error) {
          if (error.response && error.response.status === 422) {
            // Validation errors
            this.errors = error.response.data.errors;
          } else {
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
