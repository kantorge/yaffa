<template>
    <div class="modal fade" id="modal-transaction-form-standard" style="display: none;">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title" v-html="modalTitle"></h4>
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
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
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
            };
        },
        methods: {
            onCancel() {
                $('#modal-transaction-form-standard').modal('hide')
            },
            onSuccess(transaction) {
                // Emit a custom event to global scope about the new transaction to be displayed as a notification
                let notificationEvent = new CustomEvent('notification', {
                    detail: {
                        notification: {
                            type: 'success',
                            message: 'Transaction added (#' + transaction.id + ')',
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
                $('#modal-transaction-form-standard').modal('hide')
            },
            onInitiateEnterInstance(transaction) {
                this.action = 'enter';
                this.transaction = transaction;
                $('#modal-transaction-form-standard').modal('show');
            },
            onInitiateCreateDraft(transaction) {
                this.action = 'create';
                this.transaction = transaction;
                $('#modal-transaction-form-standard').modal('show');
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
        },
        computed: {
            modalTitle() {
                var titles = new Map(
                    [
                        ['create', 'Add new transaction'],
                        ['edit', 'Modify existing transaction'],
                        ['clone', 'Clone existing transaction'],
                        ['enter', 'Enter scheduled transaction instance'],
                        ['replace', 'Clone scheduled transaction and close base item'],
                    ]
                );

                return titles.get(this.action);
            },
        },
    };
</script>

<style scoped>
    .modal-xl {
        max-width: 90%;
        width: auto !important;
    }
</style>
