<template>
    <div
        class="modal fade"
        id="modalPayeeForm"
        transition="modal"
    >
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- form start -->
                <form
                    accept-charset="UTF-8"
                    @submit.prevent="onSubmit"
                    autocomplete="off"
                >
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">×</span>
                        </button>
                        <h4 class="modal-title" v-if="action == 'new'">Add new payee</h4>
                        <h4 class="modal-title" v-if="action == 'edit'">Edit payee</h4>
                    </div>
                    <div class="modal-body form-horizontal">
                        <AlertErrors :form="form" message="There were some problems with your input." />
                        <AlertSuccess :form="form" message="Your changes have been saved!" />

                        <div class="form-group">
                            <label for="name" class="control-label col-sm-3">
                                Name
                            </label>
                            <div class="col-sm-9">
                                <input
                                    class="form-control"
                                    id="name"
                                    maxlength="255"
                                    type="text"
                                    v-model="form.name"
                                    @keyup="onNameChange"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="active" class="control-label col-sm-3">
                                Active
                            </label>
                            <div class="col-sm-9">
                                <input
                                    id="active"
                                    class="checkbox-inline"
                                    type="checkbox"
                                    value="1"
                                    v-model="form.active"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="category_id" class="control-label col-sm-3">
                                Default category
                            </label>
                            <div class="col-sm-9">
                                <select
                                    class="form-control category"
                                    style="width:100%"
                                    v-model.number="form.config.category_id"
                                >
                                </select>
                            </div>
                        </div>
                        <div class="form-group" v-show="similarPayees.length > 0">
                            <hr>
                            <label for="category_id" class="control-label col-sm-3">
                                Are you looking for any of these payees?
                            </label>
                            <div class="col-sm-9">
                                <ul class="list-unstyled">
                                    <li
                                        class="mt-2"
                                        v-for="payee in similarPayees"
                                        :key="payee.id"
                                    >
                                        <a href="#" @click.prevent="onSelectPayee(payee)">
                                            {{ payee.name }}
                                            <span v-if="!payee.active">(inactive)</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default pull-left closeModal" data-dismiss="modal">Close</button>
                        <Button class="btn btn-primary" :disabled="form.busy" :form="form">Save</Button>
                    </div>
                </form>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
</template>

<script>
    require('select2');

    import Form from 'vform'
    import {Button, AlertErrors, AlertSuccess} from 'vform/src/components/bootstrap4'

    export default {
        components: {
            Button, AlertErrors, AlertSuccess
        },

        props: {
            action: String,
            payee: Object,
        },

        data() {
            let data = {};

            // Main form data
            data.form = new Form({
                config_type: 'payee',
                name: '',
                active: true,
                config: {
                    category_id: '',
                }
            });

            data.similarPayees = [];

            return data;
        },

        mounted() {
            // Add select2 functionality to category
            let elementCategory = $(this.$el).find('select.category');

            elementCategory.select2({
                ajax: {
                    url: '/api/assets/category',
                    dataType: 'json',
                    delay: 150,
                    data: function (params) {
                        return {
                            q: params.term,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data,
                        };
                    },
                    cache: true
                },
                selectOnClose: true,
                placeholder: "Select category",
                allowClear: true
            })
            .on('select2:select', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);
            });
        },

        methods: {
            show() {
                $(this.$el).modal();
            },

            resetForm() {
                // Clear form data
                this.form.name = '';
                this.form.active = true;
                this.form.config.category_id = '';
                $(this.$el).find('select.category').val('').trigger('change');

                // Reset list of similar payees
                this.similarPayees = [];

                // Reset Form status
                this.form.reset();
                this.form.successful = false;
            },

            onNameChange(event) {
                // Get similar payees from API
                fetch('/api/assets/payee/similar?query=' + event.target.value)
                    .then(response => response.json())
                    .then(data => {
                        this.similarPayees = data;
                    });
            },

            onSelectPayee(payee) {
                // If payee is inactive, activate it before adding it to form
                if (!payee.active) {
                    this.form.put(route('api.accountentity.updateActive', {accountEntity: payee.id, active: 1}))
                    .then(response => this.processAfterSubmit(response));
                } else {
                    this.hideAndReset(this)

                    // Let parent know about the new item
                    this.$emit('payeeSelected', payee);
                }
            },

            processAfterSubmit(response) {
                setTimeout(this.hideAndReset(this), 1000);

                // Let parent know about the new item
                this.$emit('payeeSelected', response.data);
            },

            hideAndReset(vm) {
                vm.resetForm();
                $(vm.$el).modal('hide');
            },

            onSubmit() {
                if (this.action === 'new') {
                    this.form.post(route('api.payee.store'), this.form)
                        .then(response => this.processAfterSubmit(response));
                } else {
                    this.form.patch(this.formUrl, this.form)
                        .then(response => this.processAfterSubmit(response));
                }
            },
        }
    }
</script>

<style scoped>
    .mt-2 {
        margin-top: 5px;
    }
</style>
