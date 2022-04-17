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
                        <a :href=" getRoute('show') " class="btn btn-success" title="View details"><i class="fa fa-fw fa-search"></i> Open</a>
                        <a :href=" getRoute('edit') " class="btn btn-primary" title="Edit"><i class="fa fa-fw fa-edit"></i> Edit</a>
                        <a :href=" getRoute('clone') " class="btn btn-primary" title="Clone"><i class="fa fa-fw fa-clone"></i> Clone</a>
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
            element: {
                type: String,
                required: true,
            },
            selector: {
                type: String,
                default: null,
            },
        },
        data() {
            return {
                transaction: {},
            };
        },
        methods: {
            close() {
                this.$emit('close');
            },
            getRoute(action) {
                return route('transactions.openStandard', {transaction: this.transaction.id, action: action})
            }
        },
        mounted() {
            let $vm = this;

            // TODO: this should be more dynamic. Not part of the component, or at least set as a prop
            $(this.element).on("click", this.selector, function() {
                let icon = this.querySelector('i');
                if (icon.classList.contains("fa-spinner")) {
                    return false;
                }

                const originalIconClass = icon.className;
                icon.className = "fa fa-fw fa-spin fa-spinner";

                fetch('/api/transaction/' + this.dataset.id)
                .then(function(response) {
                    if (!response.ok) {
                        throw Error(response.statusText);
                    }
                    return response;
                }).then(response => response.json())
                .then(function(data) {
                    $vm.transaction = data.transaction;

                    $('#modal-quickview').modal();

                    icon.className = originalIconClass;
                })
                .catch((error) => {
                    console.log(error);
                    icon.className = originalIconClass;
                });
            })

        },
    };
</script>
