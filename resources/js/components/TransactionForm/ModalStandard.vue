<template>
    <div class="modal fade" id="modal-transaction-form-standard">
        <div class="modal-dialog modal-xxl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ modalTitle }}
                    </h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <transaction-form-standard
                            :action="action"
                            :transaction="transactionData"
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
import TransactionFormStandard from './../TransactionFormStandard.vue'
import * as helpers from "../../helpers";

export default {
    name: 'CreateStandardTransactionModal',
    components: {
        TransactionFormStandard,
    },
    props: {
        transaction: {
            type: Object,
            default: {
                transaction_type: {
                    name: 'withdrawal',
                },
                date: new Date(),
                schedule: false,
                budget: false,
                reconciled: false,
                comment: null,
                config: {
                    account_from_id: null,
                    account_to_id: null,
                    amount_from: null,
                    amount_to: null,
                },
            }
        },
    },
    data() {
        let data = {
            action: 'create',
        };
        data.transactionData = Object.assign({}, this.transaction);
        return data;
    },
    methods: {
        onCancel() {
            this.modal.hide();
        },
        onSuccess(transaction) {
            // Emit a custom event to global scope about the new transaction to be displayed as a notification
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Success'),
                    headerSmall: helpers.transactionLink(transaction.id, __('Go to transaction')),
                    body: __('Transaction added.'),
                    toastClass: "bg-success",
                }
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
            this.transactionData = transaction;

            this.modal.show();
        },
        onInitiateCreateDraft(transaction) {
            this.action = 'create';
            this.transactionData = transaction;

            this.modal.show();
        },
    },
    mounted() {
        let $vm = this;

        // Set up event listener for global scope about new schedule instance to be opened in modal editor
        window.addEventListener('initiateEnterInstance', function (event) {
            // Validate that transaction type is standard
            if (event.detail.transaction.config_type !== 'standard') {
                return;
            }

            $vm.onInitiateEnterInstance(event.detail.transaction);
        });

        // Set up event listener for global scope about new transaction draft to be opened in modal editor
        window.addEventListener('initiateCreateFromDraft', function (event) {
            // Validate that transaction type is standard
            if (event.detail.type !== 'standard') {
                return;
            }

            $vm.onInitiateCreateDraft(event.detail.transaction);
        });

        // Initialize modal
        this.modal = new coreui.Modal(document.getElementById('modal-transaction-form-standard'));
    },
    computed: {
        modalTitle() {
            const titles = new Map([
                ['create', __('Add new transaction')],
                ['edit', __('Modify existing transaction')],
                ['clone', __('Clone existing transaction')],
                ['enter', __('Enter scheduled transaction instance')],
                ['replace', __('Clone scheduled transaction and close base item')],
            ]);

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
