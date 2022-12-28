<template>
    <div class="modal fade" id="modal-transaction-form-standard">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" v-html="modalTitle"></h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <transaction-form-standard
                        :action = action
                        :transaction = transaction
                        :simplified="true"
                        :fromModal="true"
                        @cancel="onCancel"
                        @success="onSuccess"
                    ></transaction-form-standard>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import FormStandard from './../TransactionFormStandard.vue'

    export default {
        name: 'CreateTransactionModal',
        components: {
            'transaction-form-standard': FormStandard,
        },
        props: {},
        data() {
            return {
                transaction: {},
                action: '',
                modal: undefined,
            };
        },
        methods: {
            onCancel() {
                this.modal.hide();
            },
            onSuccess(transaction) {
                // Emit a custom event to global scope about the new transaction to be displayed as a notification
                let notificationEvent = new CustomEvent('notification', {
                    detail: {
                        notification: {
                            type: 'success',
                            message: __('Transaction added (#:transactionId)', {transactionId: transaction.id}),
                            title: null,
                            icon: null,
                            dismissable: true,
                        }
                    },
                });
                window.dispatchEvent(notificationEvent);

                // Emit a custom event about the new transaction to be displayed
                let transactionEvent = new CustomEvent('transaction-created', {
                    detail: {
                        // Pass the entire transaction object to the event
                        transaction: transaction,
                    }
                });
                window.dispatchEvent(transactionEvent);

                // Hide the modal
                this.modal.hide();
            },
            onInitiateEnterInstance(transaction) {
                this.action = 'enter';
                this.transaction = transaction;

                this.modal.show();
            },
            onInitiateCreateDraft(transaction) {
                this.action = 'create';
                this.transaction = transaction;

                this.modal.show();
            },
        },
        mounted() {
            let $vm = this;

            // Set up event listener for global scope about new schedule instance to be opened in modal editor
            window.addEventListener('initiateEnterInstance', function(event) {
                $vm.onInitiateEnterInstance(event.detail.transaction);
            });

            // Set up event listener for global scope about new transaction draft to be opened in modal editor
            window.addEventListener('initiateCreateFromDraft', function(event) {
                $vm.onInitiateCreateDraft(event.detail.transaction);
            });

            // Initialize modal
            this.modal = new coreui.Modal(document.getElementById('modal-transaction-form-standard'));
        },
        computed: {
            modalTitle() {
                var titles = new Map(
                    [
                        ['create', __('Add new transaction')],
                        ['edit', __('Modify existing transaction')],
                        ['clone', __('Clone existing transaction')],
                        ['enter', __('Enter scheduled transaction instance')],
                        ['replace', __('Clone scheduled transaction and close base item')],
                    ]
                );

                return titles.get(this.action);
            },
        },
    };
</script>

<style scoped>
    @media (min-width: 1200px) {
        .modal-xxl {
            --cui-modal-width: 1800px;
        }
    }
</style>
