<template>
    <div>
        <!-- form start -->
        <form
            accept-charset="UTF-8"
            :action="formUrl"
            autocomplete="off"
            id="formTransaction"
            method="POST"
        >
            <input
                v-if="transactionData.id"
                name="_method"
                type="hidden"
                value="PATCH">

            <div class="row">
                <!-- left column -->
                <div class="col-md-4">
                    <!-- general form elements -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                Transaction properties
                            </h3>
                        </div>
                        <!-- /.box-header -->

                        <div class="box-body">
                            <div class="form-horizontal">
                                <div class="form-group row" id="transaction_type_container">
                                    <label class="control-label col-sm-3">
                                        Type
                                    </label>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_withdrawal"
                                            name="transaction_type"
                                            type="radio"
                                            value="withdrawal"
                                            v-model="transactionData.transaction_type.name"
                                            @change="changeTransactionType"
                                            :checked="transactionData.transaction_type.name == 'withdrawal'"
                                        >
                                        <label
                                            for="transaction_type_withdrawal"
                                            id="transaction_type_withdrawal_label"
                                        >
                                            Withdrawal
                                        </label>
                                    </div>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_deposit"
                                            name="transaction_type"
                                            type="radio"
                                            value="deposit"
                                            v-model="transactionData.transaction_type.name"
                                            @change="changeTransactionType"
                                            :checked="transactionData.transaction_type.name == 'deposit'"
                                        >
                                        <label
                                            for="transaction_type_deposit"
                                            id="transaction_type_deposit_label"
                                        >
                                            Deposit
                                        </label>
                                    </div>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_transfer"
                                            name="transaction_type"
                                            type="radio"
                                            value="transfer"
                                            :disabled="transactionData.budget"
                                            v-model="transactionData.transaction_type.name"
                                            @change="changeTransactionType"
                                            :checked="transactionData.transaction_type.name == 'transfer'"
                                        >
                                        <label
                                            for="transaction_type_transfer"
                                            id="transaction_type_transfer_label"
                                            :disabled="transactionData.budget"
                                        >
                                            Transfer
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="transaction_date" class="control-label col-sm-3">
                                        Date
                                    </label>
                                    <div class="col-sm-6">
                                        <input
                                            class="form-control"
                                            id="transaction_date"
                                            maxlength="10"
                                            name="date"
                                            type="text"
                                            v-model="transactionData.date"
                                        >
                                    </div>
                                    <div class="col-sm-3">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="control-label col-sm-3" id="account_from_label">
                                        <span v-if="transactionData.transaction_type.name == 'withdrawal' || transactionData.transaction_type.name == 'transfer'">
                                            Account from
                                        </span>
                                        <span v-else>
                                            Payee
                                        </span>
                                    </label>
                                    <div class="col-sm-9">
                                        <select
                                            class="form-control"
                                            id="account_from"
                                            name="config[account_from_id]"
                                            v-model="from.account_id">
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="control-label col-sm-3" id="account_to_label">
                                        <span v-if="transactionData.transaction_type.name == 'deposit' || transactionData.transaction_type.name == 'transfer'">
                                            Account to
                                        </span>
                                        <span v-else>
                                            Payee
                                        </span>
                                    </label>
                                    <div class="col-sm-9">
                                        <select
                                            class="form-control"
                                            id="account_to"
                                            name="config[account_to_id]"
                                            v-model="to.account_id">
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label for="transaction_date" class="control-label col-sm-3">
                                        Comment
                                    </label>
                                    <div class="col-sm-9">
                                        <input
                                            class="form-control"
                                            id="transaction_comment"
                                            maxlength="255"
                                            name="comment"
                                            type="text"
                                            v-model="transactionData.comment"
                                        >
                                    </div>
                                </div>

                                <div class="form-group row" id="entry_type_container">
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_schedule"
                                            class="checkbox-inline"
                                            :disabled="transactionData.reconciled"
                                            name="schedule"
                                            type="checkbox"
                                            value="1"
                                            v-model="transactionData.schedule"
                                        >
                                        <label for="entry_type_schedule" class="control-label">
                                            Scheduled
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_budget"
                                            class="checkbox-inline"
                                            :disabled="transactionData.reconciled || transactionData.transaction_type.name == 'transfer'"
                                            name="budget"
                                            type="checkbox"
                                            value="1"
                                            v-model="transactionData.budget"
                                        >
                                        <label for="entry_type_budget" class="control-label">
                                            Budget
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="transaction_reconciled"
                                            class="checkbox-inline"
                                            :disabled="transactionData.schedule || transactionData.budget"
                                            name="reconciled"
                                            type="checkbox"
                                            value="1"
                                            v-model="transactionData.reconciled"
                                        >
                                        <label for="transaction_reconciled" class="control-label">
                                            Reconciled
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.box-body -->

                    </div>
                    <!-- /.box -->

                </div>
                <!--/.col (left) -->

                <!-- right column -->
                <div class="col-md-8">
                    <transaction-item-container
                        @addTransactionItem="addTransactionItem"
                        :transactionItems="transactionData.transaction_items"
                        :currency="from.account_currency"
                    ></transaction-item-container>

                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Amounts</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="form-group col-sm-4">
                                            <label for="transaction_amount_from" class="control-label">
                                                Amount from
                                                <span v-if="from.account_currency">({{from.account_currency}})</span>
                                            </label>
                                            <input
                                                class="form-control"
                                                id="transaction_amount_from"
                                                maxlength="50"
                                                name="config[amount_from]"
                                                type="text"
                                                @change="updateAmount"
                                                v-model.number="transactionData.config.amount_from"
                                            >
                                        </div>
                                        <div
                                            v-show="from.account_currency && to.account_currency && from.account_currency != to.account_currency"
                                            class="col-sm-4">
                                            <span>Exchange rate</span>
                                            {{ exchangeRate }}
                                        </div>
                                        <div
                                            v-show="from.account_currency && to.account_currency && from.account_currency != to.account_currency"
                                            class="form-group col-sm-4 pull-right">
                                            <label for="transaction_amount_slave" class="control-label">
                                                Amount to
                                                <span v-if="to.account_currency">({{to.account_currency}})</span>
                                            </label>
                                            <input
                                                class="form-control"
                                                id="transaction_amount_to"
                                                maxlength="50"
                                                name="config[amount_to]"
                                                type="text"
                                                @change="updateAmount"
                                                v-model="transactionData.config.amount_to"
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <th scope="row" style="border-top:none;">Total allocated:</th>
                                                    <td style="border-top:none;" class="text-right">
                                                        {{ allocatedAmount }}
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                    </td>
                                                </tr>
                                                <tr v-show="payeeCategory.id">
                                                    <th scope="row">
                                                        Remaining amount to payee default:
                                                        <span class="notbold">{{ payeeCategory.text }}</span>
                                                    </th>
                                                    <td class="text-right">
                                                        <span>{{ remainingAmountToPayeeDefault }}</span>
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                        <input
                                                            name="remaining_payee_default_amount"
                                                            id="remaining_payee_default_amount"
                                                            type="hidden"
                                                            v-model="remainingAmountToPayeeDefault"
                                                        >
                                                        <input
                                                            name="remaining_payee_default_category_id"
                                                            id="remaining_payee_default_category_id"
                                                            type="hidden"
                                                            v-model="payeeCategory.id"
                                                        >
                                                    </td>
                                                </tr>
                                                <tr v-show="!payeeCategory.id">
                                                    <th scope="row">Remaining amount not allocated:</th>
                                                    <td class="text-right">
                                                        <span>{{ remainingAmountNotAllocated }}</span>
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- /.box -->

                    <transaction-schedule
                        :isVisible="transactionData.schedule || transactionData.budget"
                    ></transaction-schedule>

                </div>
                <!--/.col (right) -->

            </div>
            <!-- /.row -->

            <input
                name="id"
                type="hidden"
                v-model="transactionData.id"
            >
            <input
                name="action"
                type="hidden"
                v-model="action"
            >
            <input
                name="config_type"
                type="hidden"
                value="transaction_detail_standard"
            >

    <footer class="main-footer navbar-fixed-bottom hidden">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-2">
                    <label class="control-label">After saving</label>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group btn-group-justified" data-toggle="buttons">
                        <label
                            class="btn btn-default"
                            :class="callback == 'newStandard' ? 'active' : ''">
                            <input
                                :checked="callback == 'newStandard'"
                                name="callback"
                                type="radio"
                                value="newStandard"
                                class="radio-inline">
                            Add an other transaction
                        </label>
                        <label
                            class="btn btn-default"
                            :class="callback == 'cloneStandard' ? 'active' : ''">
                            <input
                                :checked="callback == 'cloneStandard'"
                                name="callback"
                                type="radio"
                                value="cloneStandard"
                                class="radio-inline">
                            Clone this transaction
                        </label>
                        <label
                            class="btn btn-default"
                            :class="callback == 'returnToAccount' ? 'active' : ''">
                            <input
                                :checked="callback == 'returnToAccount'"
                                name="callback"
                                type="radio"
                                value="returnToAccount"
                                class="radio-inline">
                            Return to selected account
                        </label>
                        <label
                            class="btn btn-default"
                            :class="callback == 'returnToDashboard' ? 'active' : ''">
                            <input
                                :checked="callback == 'returnToDashboard'"
                                name="callback"
                                type="radio"
                                value="returnToDashboard"
                                class="radio-inline">
                            Return to dashboard
                        </label>
                    </div>
                </div>
                <div class="box-tools col-sm-2">
                    <div class="pull-right">
                        <input type="submit" class="btn btn-sm btn-default" id="cancelButton" onclick="return clickCancel();" value="Cancel">
                        <input class="btn btn-primary" type="submit" value="Save">
                    </div>
                </div>
            </div>
        </div>
    </footer>

        </form>
    </div>
</template>

<script>
    require('daterangepicker');
    let math = require("mathjs");
    require('select2');

    import TransactionItemContainer from './TransactionItemContainer.vue'
    import TransactionSchedule from './TransactionSchedule.vue'

    const datePickerStandardSettings = {
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            format: 'YYYY-MM-DD'
        },
        autoApply: true
    };

    export default {
        components: {
            'transaction-item-container': TransactionItemContainer,
            'transaction-schedule': TransactionSchedule
        },

        props: [
            'action',
            'formUrl',
            'transaction',
            'callback',
        ],

        data() {
            let data = {};

            data.transactionData = {
                    transaction_type: {
                        name: 'withdrawal'
                    },
                    transaction_items: [],
                    config: {},
                };

            //storing all data and references about source account or payee
            //set as withdrawal by default
            data.from = {
                type: 'account',
                account_id : null,
                account_currency : null,
            };

            //storing all data and references about target account or payee
            //set as withdrawal by default
            data.to = {
                type: 'payee',
                account_id : null,
                account_currency : null,
            };

            data.payeeCategory = {
                id: null,
                text: null,
            };

            return data;
        },

        computed: {
            // Calculate the summary of all existing items and their values
            allocatedAmount() {
                return this.transactionData.transaction_items
                    .map(item => Number(item.amount))
                    .reduce((amount, currentValue) => amount + currentValue, 0 );
            },

            remainingAmountToPayeeDefault() {
                if (this.payeeCategory.id && !isNaN(this.transactionData.config.amount_from)) {
                    return this.transactionData.config.amount_from - this.allocatedAmount;
                }
                return 0;
            },

            remainingAmountNotAllocated() {
                if (!this.payeeCategory.id && !isNaN(this.transactionData.config.amount_from)) {
                    return this.transactionData.config.amount_from - this.allocatedAmount;
                }

                return 0;
            },

            exchangeRate() {
                const from = this.transactionData.config.amount_from;
                const to = this.transactionData.config.amount_to;

                if (from && to) {
                    return (Number(to) / Number(from)).toFixed(4);
                }

                return 0;
            }
        },

        created() {
            // Copy values of existing transaction into component data
            if (this.transaction) {
                this.transactionData = this.transaction;

                this.from.account_id = this.transaction.config.account_from_id;
                this.to.account_id = this.transaction.config.account_to_id;

                // Ensure that item amounts are numbers
                this.transactionData.transaction_items.map(item => item.amount = Number(item.amount));
            }
        },

        mounted() {
            let $vm = this;

            // Initilize date field
            $('#transaction_date').daterangepicker(datePickerStandardSettings);

            // Account FROM dropdown functionality
            $("#account_from")
                .select2(this.getAccountSelectConfig('from'))
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    if ($vm.getAccountType('from') == 'account') {
                        $.ajax({
                            url:  '/api/assets/account/currency/' + e.params.data.id,
                        })
                        .done(data => {
                            $vm.from.account_currency = data;
                        });
                    } else {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {payee_id: e.params.data.id}
                        })
                        .done(function( data ) {
                            $vm.payeeCategory.id = data.id;
                            $vm.payeeCategory.text = data.full_name;
                        });
                    }
                })
                .on('select2:unselect', function (e) {
                    $vm.resetAccount('from');
                });

            // Load default value for account FROM
            if (this.from.account_id !== null) {
                const data = this.transactionData.config.account_from;
                // Create the option and append to Select2
                var option = new Option(data.name, data.id, true, true);
                $("#account_from").append(option).trigger('change');

                // Manually trigger the `select2:select` event
                $("#account_from").trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            // Account TO dropdown functionality
            $("#account_to")
                .select2(this.getAccountSelectConfig('to'))
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    if ($vm.getAccountType('to') == 'account') {
                        $.ajax({
                            url:  '/api/assets/account/currency/' + e.params.data.id,
                        })
                        .done(data => {
                            $vm.to.account_currency = data;
                        });
                    } else {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {payee_id: e.params.data.id}
                        })
                        .done(function( data ) {
                            $vm.payeeCategory.id = data.id;
                            $vm.payeeCategory.text = data.full_name;
                        });
                    }
                })
                .on('select2:unselect', function (e) {
                    $vm.resetAccount('to');
                });

            // Load default value for account TO
            if (this.to.account_id !== null) {
                const data = this.transactionData.config.account_to;
                // Create the option and append to Select2
                var option = new Option(data.name, data.id, true, true);
                $("#account_to").append(option).trigger('change');

                // Manually trigger the `select2:select` event
                $("#account_to").trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            // Setup toggle detail functionality for transaction items
            $(".toggle_transaction_detail").on('click', function(){
                $(this).closest(".transaction_item_row").find(".transaction_detail_container").toggle();
            })

            //Setup remaining amount copy function for transaction items
            $(".load_remainder").on('click', function() {
                try {
                    var element = $(this).closest(".transaction_item_row").find("input.transaction_item_amount");
                    var remainingAmount = transactionData.remainingAmountNotAllocated || transactionData.remainingAmountToPayeeDefault;

                    var amount = math.evaluate(element.val() + "+" + remainingAmount);

                    element.val(amount);
                    transactionData.updateTotals();

                } catch (err) {

                }
            });

            //Display fixed footer
            setTimeout(function() {
                $("footer").removeClass("hidden");
            }, 1000);
        },

        methods: {
            changeTransactionType: function (event) {
                // TODO: get user confirmation before actual change

                // Reassign account FROM functionality
                // TODO: do this if actual change is needed (e.g. not from withdrawal -> transfer change)
                $("#account_from").select2('destroy');
                $("#account_from").select2(this.getAccountSelectConfig('from'));

                // Reassign account FROM functionality
                // TODO: do this if actual change is needed (e.g. not from withdrawal -> transfer change)
                $("#account_to").select2('destroy');
                $("#account_to").select2(this.getAccountSelectConfig('to'));

            },

            // Add a new empty item to list of transaction items
            addTransactionItem() {
                this.transactionData.transaction_items.push({});
            },

            // Update TO or FROM amount with math calculation
            updateAmount: function (event) {
                let amount = math.evaluate(event.target.value.replace(/\s/g,""));
                if(amount <= 0) throw Error("Positive number expected");

                // Update field with calculated value
                event.target.value = amount || '';

                // Emit event to update v-model
                event.target.dispatchEvent(new Event('input'));
            },

            // Check if TO or FROM is account or payee
            getAccountType(type) {
                if (this.transactionData.transaction_type.name == 'withdrawal') {
                    return type == 'from' ? 'account' : 'payee';
                }
                if (this.transactionData.transaction_type.name == 'deposit') {
                    return type == 'from' ? 'payee' : 'account';
                }

                // transfer
                return 'account';
            },

            // Get url to payee or account list, based on source or target type
            getAccountApiUrl(type) {
                const accountUrl = '/api/assets/account';
                const payeeUrl = '/api/assets/payee';

                return this.getAccountType(type) == 'account' ? accountUrl : payeeUrl;
            },

            // Account has been removed, its properties need to be removed
            resetAccount(type) {
                this[type].account_id = null;
                this[type].account_currency = null;
            },

            getAccountSelectConfig (type) {
                let $vm = this;

                return {
                    ajax: {
                        url: $vm.getAccountApiUrl(type),
                        dataType: 'json',
                        delay: 150,
                        data: function (params) {
                            return {
                                q: params.term,
                                transaction_type: $vm.transactionData.transaction_type.name,
                                account_type: type
                            };
                        },
                        processResults: function (data) {
                            //TODO: exclude current selection from results
                            //var other = transactionData.elements.toAccountInput.get(0);
                            //var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);
                            var other_id = null;
                            return {
                                results: data.filter(obj => obj.id !== other_id)
                            };
                        },
                        cache: true
                    },
                    selectOnClose: true,
                    //TODO: make placeholder dynamic to transaction type
                    placeholder: "Select account to debit",
                    allowClear: true
                };
            }
        }
    }
</script>
