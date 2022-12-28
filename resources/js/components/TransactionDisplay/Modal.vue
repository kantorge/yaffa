<template>
    <div class="modal fade" id="modal-quickview">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ __('Details of transaction #:transaction', {transaction: transaction.id}) }}
                    </h5>
                    <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <transaction-show-standard
                        :transaction = transaction
                    ></transaction-show-standard>
                </div>
                <div class="modal-footer d-grid gap-2 d-md-block justify-content-md-end" v-if="transaction.id">
                    <button v-if="controls.skip && transaction.schedule" class="btn btn-warning" @click="skipInstance" :title="__('Skip schedule instance')"><i class="fa fa-fw fa-fast-forward"></i> {{ __('Skip instance') }}</button>
                    <button v-if="controls.enter && transaction.schedule" class="btn btn-success enter" @click="enterInstance" :title="__('Enter schedule instance')"><i class="fa fa-fw fa-pencil"></i> {{ __('Enter instance') }}</button>
                    <a v-if="controls.show" :href=" getRoute('show') " class="btn btn-success" :title="__('View details')"><i class="fa fa-fw fa-search"></i> {{ __('Open') }}</a>
                    <a v-if="controls.edit" :href=" getRoute('edit') " class="btn btn-primary" :title="__('Edit')"><i class="fa fa-fw fa-edit"></i> {{ __('Edit') }}</a>
                    <a v-if="controls.clone" :href=" getRoute('clone') " class="btn btn-primary" :title="__('Clone')"><i class="fa fa-fw fa-clone"></i> {{ __('Clone') }}</a>
                    <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal" data-coreui-target="#modal-quickview">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import ShowStandard from './ShowStandard.vue'

    export default {
        name: 'QuickViewTransactionModal',
        components: {
            'transaction-show-standard': ShowStandard,
        },
        props: {
            initialControls: {
                type: Object,
                default: {
                    show: true,
                    edit: true,
                    clone: true,
                    skip: false,
                    enter: false,
                    delete: false,
                },
            }
        },
        data() {
            return {
                transaction: {},
                controls: this.initialControls,
                modal: undefined,
            };
        },
        methods: {
            close() {
                this.$emit('close');
            },
            getRoute(action) {
                return route('transactions.open.standard', {transaction: this.transaction.id, action: action})
            },
            skipInstance() {
                let url = route('api.transactions.skipScheduleInstance', {transaction: this.transaction.id});
                fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': window.csrfToken,
                    },
                })
                .then((response) => response.json())
                .then((data) => {
                    // Update the transaction schedule next date from the response
                    this.transaction.transaction_schedule.next_date = new Date(data.transaction.transaction_schedule.next_date);
                })
                .catch((error) => {
                    console.error(error);
                });
            },
            enterInstance() {
                // Create a new transaction instance based on the schedule instance
                let transaction = Object.assign(
                    {},
                    this.transaction,
                    {
                        date: this.transaction.transaction_schedule.next_date, // TODO: for importing, this should be the date of the draft
                        schedule: false,
                        budget: false
                    }
                );

                // Emit a custom event to global scope about the new transaction to be opened in modal editor
                // TODO: how to avoid using global scope?
                let event = new CustomEvent('initiateEnterInstance', {
                    detail: {
                        // Pass the entire transaction object to the event
                        transaction: transaction,
                    }
                });
                window.dispatchEvent(event);

            },
            showTransaction(transaction, controls) {
                this.transaction = transaction;
                this.controls = controls;

                this.modal.show();
            },
        },
        mounted() {
            let $vm = this;

            // Set up global event listener for displaying a transaction in the modal
            window.addEventListener('showTransactionQuickviewModal', function(event) {
                $vm.showTransaction(event.detail.transaction, event.detail.controls);
            });

            // Initialize modal
            this.modal = new coreui.Modal(document.getElementById('modal-quickview'));
        },
    };
</script>
