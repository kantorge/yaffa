<template>
    <div>
        <AlertErrors :form="form" message="There were some problems with your input." />

        <!-- form start -->
        <form
            accept-charset="UTF-8"
            @submit.prevent="onSubmit"
            autocomplete="off"
        >

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        Transaction properties
                    </h3>
                </div>
                <!-- /.box-header -->

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group valid">
                                        <label for="transaction_type" class="control-label">Transaction type</label>
                                        <!--TODO: make the list dynamic-->
                                        <select id="transaction_type" class="form-control" v-model="form.transaction_type">
                                            <option
                                                v-for="item in transactionTypes"
                                                :key="item.name"
                                                :value="item.name"
                                            >{{ item.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">Account</label>
                                        <select
                                            class="form-control"
                                            id="account"
                                            v-model="form.config.account_id">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="transaction_date" class="control-label">Date</label>
                                        <date-picker
                                            id="transaction_date"
                                            v-model="form.date"
                                            value-type="format"
                                            format="YYYY-MM-DD"
                                            type="date"
                                            :disabled="form.schedule"
                                        ></date-picker>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <input
                                            id="entry_type_schedule"
                                            class="checkbox-inline"
                                            :disabled="form.reconciled"
                                            type="checkbox"
                                            value="1"
                                            v-model="form.schedule"
                                        >
                                        <label for="entry_type_schedule" class="control-label">Scheduled</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_investment" class="control-label">Investment</label>
                                        <select
                                            class="form-control"
                                            id="transaction_investment"
                                            v-model="form.config.investment_id">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="transaction_comment" class="control-label">Comment</label>
                                        <input
                                            class="form-control"
                                            id="transaction_comment"
                                            maxlength="255"
                                            type="text"
                                            v-model="form.comment"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_quantity" class="control-label">Quantity</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_quantity"
                                            v-model="form.config.quantity"
                                            :disabled="!transactionTypeSettings.quantity"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_price" class="control-label">Price</label>
                                        <input
                                            class="form-control"
                                            id="transaction_price"
                                            maxlength="50"
                                            type="number"
                                            v-model="form.config.price"
                                            :disabled="!transactionTypeSettings.price"
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_commission" class="control-label">Commission</label>
                                        <input
                                            class="form-control"
                                            id="transaction_commission"
                                            maxlength="50"
                                            type="number"
                                            v-model="form.config.commission"
                                        >
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_tax" class="control-label">Tax</label>
                                        <input
                                            class="form-control"
                                            id="transaction_tax"
                                            maxlength="50"
                                            type="number"
                                            v-model="form.config.tax"
                                        >
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_amount" class="control-label">Amount</label>
                                        <input
                                            class="form-control"
                                            id="transaction_amount"
                                            maxlength="50"
                                            type="number"
                                            v-model="form.config.amount"
                                            :disabled="!transactionTypeSettings.amount"
                                        >
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_total" class="control-label">
                                            Total
                                            <span v-if="account_currency">({{account_currency}})</span>
                                        </label>
                                        <input type="text" :value="total" class="form-control" disabled="disabled">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.box-body -->

        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

        </form>

        <transaction-schedule
            :isVisible="form.schedule"
            :schedule="form.schedule_config"
            :form="form"
        ></transaction-schedule>

        <footer class="main-footer navbar-fixed-bottom hidden">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-2">
                        <label class="control-label">After saving</label>
                    </div>
                    <div class="col-sm-8">
                        <div class="btn-group">
                            <button
                                class="btn btn-default"
                                :class="callback == 'new' ? 'active' : ''"
                                type="button"
                                value="new"
                                @click="callback = $event.currentTarget.getAttribute('value')"
                            >
                                Add an other transaction
                            </button>
                            <button
                                class="btn btn-default"
                                :class="callback == 'clone' ? 'active' : ''"
                                type="button"
                                value="clone"
                                @click="callback = $event.currentTarget.getAttribute('value')"
                            >
                                Clone this transaction
                            </button>
                            <button
                                class="btn btn-default"
                                :class="callback == 'returnToAccount' ? 'active' : ''"
                                type="button"
                                value="returnToAccount"
                                @click="callback = $event.currentTarget.getAttribute('value')"
                            >
                                Return to selected account
                            </button>
                            <button
                                class="btn btn-default"
                                :class="callback == 'returnToDashboard' ? 'active' : ''"
                                type="button"
                                value="returnToDashboard"
                                @click="callback = $event.currentTarget.getAttribute('value')"
                            >
                                Return to dashboard
                            </button>
                        </div>
                    </div>
                    <div class="box-tools col-sm-2">
                        <div class="pull-right">
                            <button class="btn btn-sm btn-default" type="button" @click="onCancel">
                                Cancel
                            </button>
                            <Button class="btn btn-primary" :disabled="form.busy" :form="form">Save</Button>
                        </div>
                    </div>
                </div>
            </div>
        </footer>

    </div>
</template>

<script>
    require('select2');

    import MathInput from './MathInput.vue'

    import Form from 'vform'
    import {Button, AlertErrors} from 'vform/src/components/bootstrap5'

    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';

    import TransactionSchedule from './TransactionSchedule.vue'

    export default {
        components: {
            'transaction-schedule': TransactionSchedule,
            MathInput,
            DatePicker,
            Button, AlertErrors
        },

        props: {
            action: String,
            formUrl: String,
            transaction: Object,
        },

        data() {
            let data = {};

            // Main form data
            data.form = new Form({
                transaction_type: 'Buy',
                config_type: 'transaction_detail_investment',
                date: '',
                comment: null,
                schedule: false,
                budget: false,
                reconciled: false,
                config: {},
                schedule_config: {
                    frequency: 'DAILY',
                    interval: 1,
                },
            });

            // TODO: adjust initial callback based on action
            data.callback = 'new';

            return data;
        },

        computed: {
            total() {
                return this.form.config.quantity * this.form.config.price;
            },

            transactionTypeSettings() {
                return this.transactionTypes.filter(item => item.name == this.form.transaction_type)[0];
            },
        },

        created() {
            this.transactionTypes = [
                {
                    name: 'Buy',
                    commission_multiplier: -1,
                    quantity: true,
                    price: true,
                    amount: false,
                },
                {
                    name: 'Sell',
                    commission_multiplier: -1,
                    quantity: true,
                    price: true,
                    amount: false,
                    },
                {
                    name: 'Add shares',
                    commission_multiplier: -1,
                    quantity: true,
                    price: false,
                    amount: false,
                    },
                {
                    name: 'Remove shares',
                    commission_multiplier: -1,
                    quantity: true,
                    price: false,
                    amount: false,
                    },
                {
                    name: 'Dividend',
                    commission_multiplier: -1,
                    quantity: false,
                    price: false,
                    amount: true,
                },
                {
                    name: 'S-Term Cap Gains Dist',
                    commission_multiplier: -1,
                    quantity: false,
                    price: false,
                    amount: true,
                },
                {
                    name: 'L-Term Cap Gains Dist',
                    commission_multiplier: -1,
                    quantity: false,
                    price: false,
                    amount: true,
                },
            ];
        },

        mounted() {
            //Display fixed footer
            setTimeout(function() {
                $("footer").removeClass("hidden");
            }, 1000);
        },

        methods: {

        }
    }
</script>
