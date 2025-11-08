<template>
    <div class="modal fade" id="currencyRateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ isEditMode ? __('Edit Currency Rate') : __('Add Currency Rate') }}
                    </h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
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
                            >
                            <div class="invalid-feedback" v-if="errors.date">
                                {{ errors.date }}
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="rateValue" class="form-label">
                                {{ __('Rate') }}
                                <small class="text-muted">({{ fromCurrency.iso_code }} → {{ toCurrency.iso_code }})</small>
                            </label>
                            <input
                                type="number"
                                step="0.0001"
                                min="0.0001"
                                class="form-control"
                                id="rateValue"
                                v-model.number="formData.rate"
                                :class="{ 'is-invalid': errors.rate }"
                                required
                            >
                            <div class="invalid-feedback" v-if="errors.rate">
                                {{ errors.rate }}
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" class="btn btn-primary" @click="submitForm" :disabled="isSubmitting">
                        <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-1"></span>
                        {{ isEditMode ? __('Update') : __('Add') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
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
            errors: {},
            isSubmitting: false,
            modal: null,
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
                        window.route('api.currency-rate.update', { currency_rate: this.editRate.id }),
                        data
                    );
                } else {
                    response = await window.axios.post(
                        window.route('api.currency-rate.store'),
                        data
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
                    // Show generic error toast
                    const notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: this.__('Error'),
                            body: error.response?.data?.message || this.__('An error occurred'),
                            toastClass: 'bg-danger',
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                }
            } finally {
                this.isSubmitting = false;
            }
        },
        __: function (string, replace) {
            return window.__(string, replace);
        },
    },
};
</script>
