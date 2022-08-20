<template>
    <div class="modal fade" id="modal-quickview" style="display: none;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title">Details of transaction #{{transaction.id}}</h4>
                </div>
                <div class="modal-body">
                    <transaction-show-standard
                        :transaction = transaction
                    ></transaction-show-standard>
                </div>
                <div class="modal-footer">
                    <div class="pull-right" v-if="transaction.id">
                        <button v-if="controls.skip && transaction.schedule" class="btn btn-warning" @click="skipInstance" title="Skip schedule instance"><i class="fa fa-fw fa-fast-forward"></i> Skip instance</button>
                        <button v-if="controls.enter && transaction.schedule" class="btn btn-success enter" @click="enterInstance" title="Enter schedule instance"><i class="fa fa-fw fa-pencil"></i> Enter instance</button>
                        <a v-if="controls.show" :href=" getRoute('show') " class="btn btn-success" title="View details"><i class="fa fa-fw fa-search"></i> Open</a>
                        <a v-if="controls.edit" :href=" getRoute('edit') " class="btn btn-primary" title="Edit"><i class="fa fa-fw fa-edit"></i> Edit</a>
                        <a v-if="controls.clone" :href=" getRoute('clone') " class="btn btn-primary" title="Clone"><i class="fa fa-fw fa-clone"></i> Clone</a>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
</template>

<script>
    import ShowStandard from './ShowStandard.vue'

    export default {
        name: 'TransactionModal',
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
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                })
                .then((response) => response.json())
                .then((data) => {
                    // Update the transaction schedule next date from the response
                    this.transaction.transaction_schedule.next_date = new Date(data.transaction.transaction_schedule.next_date);
                }).catch(error => {
                    console.error(error);
                });
            },
            enterInstance() {
                // Create a new transaction instance with the schedule next date set as date
                let transaction = Object.assign({}, this.transaction, {date: this.transaction.transaction_schedule.next_date});

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

                $('#modal-quickview').modal('show');
            },
        },
        mounted() {
            let $vm = this;

            // Set up global event listener for displaying a transaction in the modal
            window.addEventListener('showTransactionQuickviewModal', function(event) {
                $vm.showTransaction(event.detail.transaction, event.detail.controls);
            });
        },
    };
</script>
