<template>
    <div id="modal-quickview" class="modal fade">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{
                            __('Details of transaction #:transaction', {
                                transaction: transaction.id,
                            })
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
                    <transaction-show-standard
                        v-if="transaction.config_type === 'standard'"
                        :transaction="transaction"
                    ></transaction-show-standard>
                    <transaction-show-investment
                        v-if="transaction.config_type === 'investment'"
                        :transaction="transaction"
                    ></transaction-show-investment>
                </div>
                <div v-if="transaction.id" class="modal-footer">
                    <action-button-bar
                        :transaction="transaction"
                        :controls="controls"
                        :is-modal="true"
                        @transaction-updated="transactionUpdated"
                    ></action-button-bar>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ShowStandard from './ShowStandard.vue';
    import ShowInvestment from './ShowInvestment.vue';
    import ActionButtonBar from './ActionButtonBar.vue';
    import { __ } from '@/shared/lib/i18n';

    export default {
        name: 'QuickViewTransactionModal',
        components: {
            'transaction-show-standard': ShowStandard,
            'transaction-show-investment': ShowInvestment,
            'action-button-bar': ActionButtonBar,
        },
        props: {
            initialControls: {
                type: Object,
                default: () => ({
                    show: true,
                    edit: true,
                    clone: true,
                    skip: false,
                    enter: false,
                    delete: false,
                }),
            },
            originalTransaction: Object,
        },
        data() {
            return {
                transaction: Object.assign({}, this.originalTransaction),
                controls: this.initialControls,
                modal: undefined,
            };
        },
        mounted() {
            // Set up global event listener for displaying a transaction in the modal
            window.addEventListener(
                'showTransactionQuickViewModal',
                this.handleQuickView,
            );

            // Initialize modal
            this.modal = new coreui.Modal(
                document.getElementById('modal-quickview'),
            );
        },
        beforeUnmount() {
            // Clean up event listener when component is destroyed
            window.removeEventListener(
                'showTransactionQuickViewModal',
                this.handleQuickView,
            );
        },
        methods: {
            close() {
                this.$emit('close');
            },
            showTransaction(transaction, controls) {
                this.transaction = transaction;
                this.controls = controls;

                this.modal.show();
            },
            transactionUpdated: function (transaction) {
                // ActionButtonBar already runs the emitted transaction through processTransaction().
                this.transaction = Object.assign({}, transaction);
            },
            handleQuickView(event) {
                this.showTransaction(
                    event.detail.transaction,
                    event.detail.controls,
                );
            },
            __,
        },
    };
</script>
