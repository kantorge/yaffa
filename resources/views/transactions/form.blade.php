@extends('adminlte::page')

@section('classes_body', "layout-footer-fixed")

@section('title', 'Transaction')

@section('content_header')
<h1>Transaction</h1>
@stop

@section('content')

    <!-- form start -->
    @if(isset($transaction))
        {{
            Form::model($tag, [
                'route'         => ['transactions.update', $transaction->id],
                'method'        => 'patch',
                'id'		    => "formTransaction",
                'autocomplete'  => "off"
            ])
        }}
    @else
        {{ Form::open([
                'route'         => 'transactions.store',
                'id'		    => "formTransaction",
                'autocomplete'  => "off"
            ])
        }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($transaction))
                    Modify transaction
                @else
                    Add transaction
                @endif
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">

	<div class="row">
        <!-- left column -->
        <div class="col-md-4">
            <!-- general form elements -->
            <div class="card ">
                <div class="card-header with-border">
                    <h3 class="card-title">
                        Transaction properties
                    </h3>
                </div>
                <!-- /.card-header -->

                <div class="card-body">
                    <div class="form-horizontal">
                        <div class="form-group row" id="transaction_type_container">
                            <label for="transaction_type" class="control-label col-sm-3">
                                Type
                            </label>
                            <div class="col-sm-9">
                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                    <label class="btn btn-primary" id="transaction_type_withdrawal_label">
                                        <input id="transaction_type_withdrawal" class="radio-inline" name="transaction_type" type="radio" value="withdrawal">
                                        Withdrawal
                                    </label>
                                    <label class="btn btn-primary" id="transaction_type_deposit_label">
                                        <input id="transaction_type_deposit" class="radio-inline" name="transaction_type" type="radio" value="deposit">
                                        Deposit
                                    </label>
                                    <label class="btn btn-primary" id="transaction_type_transfer_label">
                                        <input id="transaction_type_transfer" class="radio-inline" name="transaction_type" type="radio" value="transfer">
                                        Transfer
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="transaction_date" class="control-label col-sm-3">
                                Date
                            </label>
                            <div class="col-sm-6">
                                {{ Form::text(
                                    'date',
                                    old('date'),
                                    [
                                        'class' => 'form-control',
                                        'id'            => 'transaction_date',
                                        'maxlength'     => '10',
                                    ])
                                }}
                            </div>
                            <div class="col-sm-3">
                            </div>
                        </div>

                        <div class="form-group row" id="transaction_account_from_container">
                            <label for="transaction_account_from" class="control-label col-sm-3" id="account_from_label">
                                Account from
                            </label>
                            <div class="col-sm-9">
                                {{ Form::select(
                                    'account_from',
                                    [],
                                    null,
                                    [
                                        'id'            => 'transaction_account_from',
                                        'class'			=> 'form-control'
                                    ])
                                }}
                            </div>
                        </div>

                        <div class="form-group row" id="transaction_account_to_container">
                            <label for="transaction_account_to" class="control-label col-sm-3" id="account_to_label">
                                Payee
                            </label>
                            <div class="col-sm-9">
                                {{ Form::select(
                                    'account_to',
                                    [],
                                    null,
                                    [
                                        'id'            => 'transaction_account_to',
                                        'class'			=> 'form-control'
                                    ])
                                }}
                            </div>
                        </div>

                        <div class="form-group row">
                            <label for="transaction_date" class="control-label col-sm-3">
                                Comment
                            </label>
                            <div class="col-sm-9">
                                {{ Form::text(
                                    'comment',
                                    old('comment'),
                                    [
                                        'class' => 'form-control',
                                        'id'            => 'transaction_comment',
                                        'maxlength'     => '255',
                                    ])
                                }}
                            </div>
                        </div>

                        <div class="form-group row" id="entry_type_container">
                            <div class="col-sm-4">
                                {{ Form::checkbox(
                                    'entry_type_schedule',
                                    'schedule',
                                    0,
                                    [
                                        'id'            => 'entry_type_schedule',
                                        'class'			=> 'checkbox-inline',
                                    ])
                                }}
                                <label for="entry_type_schedule" class="control-label">
                                    Scheduled
                                </label>
                            </div>
                            <div class="col-sm-4">
                                {{ Form::checkbox(
                                    'entry_type_budget',
                                    'budget',
                                    0,
                                    [
                                        'id'            => 'entry_type_budget',
                                        'class'			=> 'checkbox-inline',
                                    ])
                                }}
                                <label for="entry_type_budget" class="control-label">
                                    Budget
                                </label>
                            </div>
                            <div class="col-sm-4">
                                {{ Form::checkbox(
                                    'reconciled',
                                    '1',
                                    0,
                                    [
                                        'id'            => 'transaction_reconciled',
                                        'class'			=> 'checkbox-inline',
                                    ])
                                }}
                                <label for="transaction_reconciled" class="control-label">
                                    Reconciled
                                </label>
                            </div>
                        </div>
				    </div>
                </div>
                <!-- /.card-body -->

                <!--div class="card-footer">
                </div-->
            </div>
          <!-- /.card -->

        </div>
        <!--/.col (left) -->

        <!-- right column -->
        <div class="col-md-8">
			<!-- general form elements -->
			<div class="card">
				<div class="card-header with-border">
                    <h3 class="card-title">Transaction items</h3>
                    <div class="card-tools">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-info" id="itemListCollapse" title="Collapse all items"><i class="fa fa-compress"></i></button>
                            <button type="button" class="btn btn-sm btn-info" id="itemListShow" title="Expand items with data"><i class="fa fa-expand"></i></button>
                            <button type="button" class="btn btn-sm btn-info" id="itemListExpand" title="Expand all items"><i class="fa fa-arrows-alt"></i></button>
                        </div>
                        <button type="button" class="btn btn-sm btn-success new_transaction_item" title="New transaction item"><i class="fa fa-plus"></i></button>
                    </div>
				</div>
				<!-- /.card-header -->
				<div class="box-body" id="transaction_item_container">
                    <div class="list-group">
                    </div>
                </div>
                <!-- /.card-body -->

                <div class="box-footer">
					<div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="form-group col-sm-4" id="amount_from_group">
                                    <label for="transaction_amount_from" class="control-label">
                                        Amount <span class='transaction_currency_from'></span>
                                    </label>
                                    {{ Form::text(
                                        'amount_from',
                                        old('amount_from'),
                                        [
                                            'class'         => 'form-control',
                                            'id'            => 'transaction_amount_from',
                                            'maxlength'     => '20',
                                        ])
                                    }}
                                </div>
                                <div class="col-sm-4" id="transfer_exchange_rate_group">
                                    <span>Exchange rate</span>
                                    <span id="transfer_exchange_rate"></span>
                                </div>
                                <div class="form-group col-sm-4 pull-right" id="amount_slave_group">
                                    <label for="transaction_amount_slave" class="control-label">
                                        Amount to <span class='transaction_currency_to'></span>
                                    </label>
                                    {{ Form::text(
                                        'amount_slave',
                                        old('amount_slave'),
                                        [
                                            'class' => 'form-control',
                                            'id'            => 'transaction_amount_slave',
                                            'maxlength'     => '20',
                                        ])
                                    }}
                                </div>
                            </div>
                        </div>
						<div class="col-md-5">
							<div class="table">
								<table class="table">
									<tbody>
										<tr>
											<th style="border-top:none;">Total allocated:</th>
											<td style="border-top:none;" class="text-right">
												<span id="transaction_item_total">0</span>
												<span class='transaction_currency_from_nowrap'></span>
											</td>
										</tr>
										<tr id="remaining_payee_default_container">
											<th>Remaining amount to payee default: <span class="notbold" id="payee_category_name"></span></th>
											<td class="text-right">
												<span id="remaining_payee_default">0</span>
												<span class='transaction_currency_from_nowrap'></span>
                                                {{ Form::text(
                                                    'remaining_payee_default',
                                                    '',
                                                    [
                                                        'type'		=> 'hidden',
                                                        'id'            => 'remaining_payee_default_input',
                                                    ])
                                                }}
											</td>
										</tr>
										<tr id="remaining_not_allocated_container">
											<th>Remaining amount not allocated:</th>
											<td class="text-right">
												<span id="remaining_not_allocated">0</span>
												<span class='transaction_currency_from_nowrap'></span>
                                                {{ Form::text(
                                                    'remaining_not_allocated',
                                                    '',
                                                    [
                                                        'type'		=> 'hidden',
                                                        'id'            => 'remaining_not_allocated_input',
                                                    ])
                                                }}
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="col-md-1">
							<button type="button" class="btn btn-success pull-right new_transaction_item" title="New transaction item"><i class="fa fa-plus"></i></button>
						</div>
					</div>
				</div>

			</div>
			<!-- /.card -->

			<!-- schedule settings -->
			<div class="card" id="schedule_container">
				<div class="card-header with-border">
					<h3 class="card-title">Schedule</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body" id="">
                    <div class="row">
                        <div class="col-md-4">
                            {{ Form::label('schedule_frequency', 'Frequency', ['class' => 'control-label']) }}
                            {{ Form::select(
                                'schedule_frequency',
                                [
                                    'DAILY'		=> 'Daily',
                                    'WEEKLY'  	=> 'Weekly',
                                    'MONTHLY' 	=> 'Monthly',
                                    'YEARLY'      => 'Yearly'
                                ],
                                old('schedule_frequency'),
                                [
                                    'id'            => 'schedule_frequency',
			                        'class'			=> 'form-control'
                                ])
                            }}
                        </div>
                        <div class="col-md-4">
                            {{ Form::label('schedule_start', 'Start date', ['class' => 'control-label']) }}
                            {{ Form::text(
                                'schedule_start',
                                old('schedule_start'),
                                [
                                    'class'		=> 'form-control',
                                    'id'        => 'schedule_start',
                                ])
                            }}
                        </div>
                        <div class="col-md-4 form-group">
                            {{ Form::label('schedule_interval', 'Interval', ['class' => 'control-label']) }}
                            {{ Form::text(
                                'schedule_interval',
                                old('schedule_interval'),
                                [
                                    'class'     => 'form-control',
                                    'id'        => 'schedule_interval',
                                ])
                            }}
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            {{ Form::label('schedule_count', 'Count', ['class' => 'control-label']) }}
                            {{ Form::text(
                                'schedule_count',
                                old('schedule_count'),
                                [
                                    'class'		=> 'form-control',
                                    'id'        => 'schedule_count',
                                ])
                            }}
                        </div>
                        <div class="col-md-4 form-group">
                            {{ Form::label('schedule_end', 'End date', ['class' => 'control-label']) }}
                            {{ Form::text(
                                'schedule_end',
                                old('schedule_end'),
                                [
                                    'class'		=> 'form-control',
                                    'id'        => 'schedule_end',
                                ])
                            }}
                        </div>
                        <div class="col-md-4">
                            <span id="rruleText">
                            </span>
                            {{ Form::text(
                                'schedule_rrule',
                                old('schedule_rrule'),
                                [
                                    'type'		=> 'hidden',
                                    'id'        => 'schedule_rrule',
                                ])
                            }}
                        </div>
                    </div>
                </div>
                <!-- /.card-body -->

                <!-- div class="card-footer">

				</div !-->
			</div>
			<!-- /.card -->


        </div>
        <!--/.col (right) -->

    </div>
	<!-- /.row -->

	                <!-- transaction item prototype start -->
                    <div class="list-group-item hidden transaction_item_row" id="transaction_item_prototype">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    {{ Form::label('', 'Category', ['class' => 'control-label']) }}
                                    {{ Form::select(
                                        'item[#][category]',
                                        [],
                                        null,
                                        [
                                            'style'			=> 'width: 100%',
                                            'class'			=> 'form-control category'
                                        ])
                                    }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="" class="control-label">
                                        Amount <span class='transaction_currency_from'></span>
                                    </label>
                                    <div class="input-group">
                                        {{ Form::text(
                                            'item[#][amount]',
                                            null,
                                            [
                                                'class'		=> 'form-control transaction_item_amount',
                                            ])
                                        }}
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-info load_remainder" title="Assign remaining amount to this item"><i class="fa fa-copy"></i></button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    {{ Form::label('', "&nbsp;") }}
                                    <button type="button" class="btn btn-info toggle_transaction_detail" title="Show item details"><i class="fa fa-edit"></i></button>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group">
                                    {{ Form::label('', "&nbsp;") }}
                                    <button type="button" class="btn btn-danger remove_transaction_item" title="Remove transaction item"><i class="fa fa-minus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="row transaction_detail_container" style="display:none;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    {{ Form::label('', 'Comment', ['class' => 'control-label']) }}
                                    {{ Form::text(
                                        'item[#][comment]',
                                        null,
                                        [
                                            'class'		=> 'form-control transaction_item_comment',
                                        ])
                                    }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    {{ Form::label('', 'Tags', ['class' => 'control-label']) }}
                                    {{ Form::select(
                                        'item[#][tags][]',
                                        [],
                                        null,
                                        [
                                            'style'			=> 'width: 100%',
                                            'class'			=> 'form-control tag',
                                            'multiple'      => 'multiple',
                                        ])
                                    }}
                                </div>
                            </div>
                        </div>
					</div>
	                <!-- transaction item prototype end -->

    <footer class="main-footer layout-footer-fixed">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-2">
                    {{ Form::label('callback', 'After saving', ['class' => 'control-label']) }}
                </div>
                <div class="col-sm-8">
                    <div class="btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-default" id="callback_new_label">
                            <input checked="checked" name="callback" type="radio" value="new" id="callback">

                            Add an other transaction
                        </label>
                        <label class="btn btn-default active" id="callback_clone_label">
                            <input checked="checked" name="callback" type="radio" value="clone" id="callback">

                            Clone this transaction
                        </label>
                        <label class="btn btn-default" id="callback_returnToAccount_label">
                            <input checked="checked" name="callback" type="radio" value="returnToAccount" id="callback">

                            Return to selected account
                        </label>
                        <label class="btn btn-default active" id="callback_returnToDashboard_label">
                            <input checked="checked" name="callback" type="radio" value="returnToDashboard" id="callback">

                            Return to dashboard
                        </label>
                    </div>
                </div>
            <div class="box-tools col-sm-2">
                <div class="pull-right">
                    {{ Form::submit(
                        'Cancel',
                        [
                            'type'			=> 'cancel',
                            'class'			=> 'btn btn-sm btn-default',
                            'id'            => 'cancelButton',
                            'onClick'       => "return clickCancel();"
                        ])
                    }}
                    {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
                </div>
            </div>
        </div>
    </footer>

    </div>
        <!-- /.card-body -->
        <div class="card-footer">
            {{ Form::hidden('id', old('id')) }}
        </div>
        <!-- /.card-footer -->
    </div>
    <!-- /.card -->

    {{ Form::close() }}

@endsection

@section('css')
    <!-- Daterange picker -->
    <link rel="stylesheet" type="text/css" href="{{ asset('vendor/daterangepicker/daterangepicker.css')}}" />


    <link rel="stylesheet" type="text/css" href="{{ asset('vendor/select2/css/select2.css')}}" />

    <style>
        .hidden { display: none;}
    </style>
@endsection

@section('js')
    <!-- daterangepicker -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="{{ asset('vendor/daterangepicker/daterangepicker.js')}}"></script>


    <script type="text/javascript" src="{{ asset('vendor/select2/js/select2.min.js')}}"></script>
    <script type="text/javascript" src="{{ asset('vendor/math.min.js')}}"></script>
    <script type="text/javascript" src="{{ asset('vendor/rrule.min.js')}}"></script>
    <script type="text/javascript" src="{{ asset('vendor/jquery.validate.min.js')}}"></script>
    <script type="text/javascript" src="{{ asset('vendor/additional-methods.min.js')}}"></script>




<script>

var transactionData = {
	elements : {},

	itemRowCounter : 0,
    itemData: {},

    account_from: {
        amount : 0,
        currency : '',
        api_url : '/api/assets/account'
    },

    account_to: {
        amount : 0,
        currency : '',
        api_url : '/api/assets/payee'
    },

	payeeData: null,
	payeeCategory : {
		id: null,
		text: null
	},
	amount: {
		from : 0,
		to : 0
	},
	currency: {
		from: '',
		to: ''
	},
	itemTotal: 0,
	remainingAmountToPayeeDefault : 0,
	remainingAmountNotAllocated: 0,
	set newPayeeCategory(x) {
		if (x) {
			this.payeeCategory.id = x.id;
			this.payeeCategory.text = x.text;
		} else {
			this.payeeCategory.id = null;
			this.payeeCategory.text = null;
		}

		$("#payee_category_name").html((this.payeeCategory.id ? "</br>(" + this.payeeCategory.text + ")" : ""));
		this.updateTotals();
	},
	set newAmountFrom (x) {
		this.amount.from = x;

		if (!this.isToCurrencyPresent()) {
			if (this.elements.slaveAmountInput) {
				this.elements.slaveAmountInput.val(x);
			}
			//this.newAmountSlave = x;
			//return;
		}

		this.updateTotals();
		this.updateExchangeRate();
	},
	set newAmountTo (x) {
		this.amount.to = x;

		this.updateTotals();
		this.updateExchangeRate();
	},
	set newCurrencyFrom (x) {
		this.currency.from = x;

		this.updateCurrencies();
		this.updateExchangeRate();
	},
	set newCurrencyTo (x) {
		this.currency.to = x;

		this.updateCurrencies();
		this.updateExchangeRate();
	},
	updateTotals: function() {
		//get all amounts for items
		var total_amount = 0;

		$("div.transaction_item_row:not(.hidden) .transaction_item_amount").each(function() {
			try {
				var current_amount = math.eval(this.value.replace(/\s/g,""));
			} catch(err) {
				var current_amount = 0;
			}
			if (!isNaN(current_amount)) {
				total_amount += current_amount;
			}
		});
		this.itemTotal = total_amount;

		//calculate remaining value
		if (this.payeeCategory.id) {
            //default payee available
			this.remainingAmountToPayeeDefault = math.subtract(math.bignumber(this.amount.from), math.bignumber(this.itemTotal)).toNumber();
            this.remainingAmountNotAllocated = 0;
            $("#remaining_payee_default_container").show();
            $("#remaining_not_allocated_container").hide();
		} else {
            //default payee NOT available
			this.remainingAmountNotAllocated = math.subtract(math.bignumber(this.amount.from), math.bignumber(this.itemTotal)).toNumber();
            this.remainingAmountToPayeeDefault = 0;
            $("#remaining_payee_default_container").hide();
            $("#remaining_not_allocated_container").show();
		}

		//display and distribute results
		$("#transaction_item_total").html(this.itemTotal );
		$("#remaining_payee_default").html(this.remainingAmountToPayeeDefault);
		$("#remaining_payee_default_input").val(this.remainingAmountToPayeeDefault);
		$("#remaining_not_allocated").html(this.remainingAmountNotAllocated);
		$("#remaining_not_allocated_input").val(this.remainingAmountNotAllocated);

        //update remaining copy buttons
        $(".transaction_item_row button.load_remainder").prop('disabled', this.remainingAmountNotAllocated <= 0 && this.remainingAmountToPayeeDefault <= 0);

		//update warning states
	},

	isToCurrencyPresent() {
		return (   this.currency.from !== this.currency.to
				&& this.currency.to);
	},

	/**
	 * check if amount to should be visible, and take care of visibility
	 * field set is visible, if
	 * - transaction type is transfer
	 * - both accounts are set
	 * - currency of accounts is different
	 */
	updateExchangeRate() {
		//prevent running before having elements set
		if (!Object.keys(this.elements).length) {
			return false;
		}

		if (this.isToCurrencyPresent()) {
			if (this.amount.from !== 0 && this.amount.to !== 0) {
				$("#transfer_exchange_rate").html((this.amount.to / this.amount.from).toFixed(4));
				$('#transfer_exchange_rate_group').show();
			}

			transactionData.elements.slaveAmountGroup.show();
		} else {
			$("#transfer_exchange_rate").html();
			$('#transfer_exchange_rate_group').hide();
			transactionData.elements.slaveAmountGroup.hide();
		}
	},

	/**
	 * update currency labels accross the form
	 *
	 * @returns false on failure, true on success
	 * (not used currently)
	 */
	updateCurrencies() {
		//prevent running before having target elements set
		if (!Object.keys(this.elements).length) {
			return false;
		}

		$(".transaction_currency_from").html(
			(this.currency.from
			? "(" + this.currency.from + ")"
			: "")
		);
		$(".transaction_currency_to").html(
			(this.currency.to
			? "(" + this.currency.to + ")"
			: "")
		);

		$(".transaction_currency_from_nowrap").html(this.currency.from);

		return true;
	}

};
var firstRun = true;

$(document).ready(function() {
	//assign various key elements to transaction variable
	transactionData.elements.toAccountInput = $("#transaction_account_to");
	transactionData.elements.toAmountGroup = $("#amount_to_group");
	transactionData.elements.toAmountInput = $("#transaction_amount_to");
	transactionData.elements.fromAccountInput = $("#transaction_account_from");

	//attach transaction type selection events to change visibility of selects
    $("#transaction_type_withdrawal_label").click(function() {
        //get confirmation if not set by script on first run
		if (!window.firstRun) {
			//ignore click if already selected
			if (transactionData.transactionType == $("#transaction_type_withdrawal").val()) {
				return false;
			}
			if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
				return false;
			}
		} else {
			window.firstRun = false;
		}
    });

    $("#transaction_type_withdrawal").change(function() {
		transactionData.transactionType = 'withdrawal';

        //from must be an account
        $('#account_from_label').html("Account from");

        //to must be a payee
        $('#account_to_label').html("Payee");

        transactionData.elements.toAccountInput.val(null).trigger('change');

        //no currency exchange is expected, hide relevant display
		$('#transfer_exchange_rate_group').hide();
		$(".transaction_currency_to").html("");

	});

    $("#transaction_type_deposit_label").click(function(event) {
        //get confirmation if not set by script on first run
        if (!window.firstRun) {
            //ignore click if already selected
            if (transactionData.transactionType == $("#transaction_type_deposit").val()) {
                return false;
            }
            if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
                return false;
            }
        } else {
            window.firstRun = false;
        }
    });

	$("#transaction_type_deposit").change(function(event) {
		transactionData.transactionType = 'deposit';

        //from must be a payee
        $('#account_from_label').html("Payee");

        //to must be an account
        $('#account_to_label').html("Account to");

        transactionData.elements.toAccountInput.val(null).trigger('change');

        //no currency exchange is expected, hide relevant display
		$('#transfer_exchange_rate_group').hide();
		$(".transaction_currency_to").html("");

	});

    $("#transaction_type_transfer_label").click(function(event) {
        if ($(this).hasClass('disabled')) {
            return false;
        }

        //get confirmation if not set by script on first run
        if (!window.firstRun) {
            //ignore click if already selected
            if (transactionData.transactionType == $("#transaction_type_transfer").val()) {
                return false;
            }
            if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
                return false;
            }
        } else {
            window.firstRun = false;
        }
    });

	$("#transaction_type_transfer").change(function(){
		transactionData.transactionType = 'transfer';

		//from must be an account
        $('#account_from_label').html("Account from");

        //to must be an account
        $('#account_to_label').html("Account to");

		transactionData.updateExchangeRate();

	});

	//master account dropdown functionality
	transactionData.elements.fromAccountInput.select2({
		ajax: {
			url: transactionData.account_from.url,
			dataType: 'json',
            delay: 150,
            data: function (params) {
                var queryParameters = {
                  q: params.term,
                  transaction_type: transactionData.transactionType || 'withdrawal',
                  account_type: 'master'
                }

                return queryParameters;
            },
			processResults: function (data) {
				//exclude slave selection from results
				var other = transactionData.elements.slaveAccountInput.get( 0 );
				var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);

				return {
					results: data.filter(function(obj) {return obj.id !== other_id;})
				};
			},
			cache: true
		},
		selectOnClose: true,
		placeholder: "Select account to debit",
        allowClear: true
	});

	//get default value for master account
	if (transactionData.accountMaster) {
		$.ajax({
			type: 'GET',
			url:  '/ajax/get_account_data',
			dataType: 'json',
			data: {
				id: transactionData.accountMaster
			}
		}).then(function (data) {
			// create the option and append to Select2
			var option = new Option(data.name, data.id, true, true);
			transactionData.elements.masterAccountInput.append(option).trigger('change');

			// manually trigger the `select2:select` event
			transactionData.elements.masterAccountInput.trigger({
				type: 'select2:select',
				params: {
					data: data
				}
			});
		});
	}

	transactionData.elements.fromAccountInput.on('select2:select', function (e) {
        transactionData.accountMaster = e.params.data.id;
        $.ajax({
			url: '/ajax/get_account_currency_label',
			data: {
				account_id: e.params.data.id
			}
		})
		.done(function( data ) {
			transactionData.newCurrencyMaster = data;
		});
	});

	transactionData.elements.fromAccountInput.on('select2:unselect', function (e) {
        transactionData.newCurrencyMaster = null;
        transactionData.accountMaster = null;
	});

	//payee dropdown functionality
	var payeeElement = $("#transaction_payee");
	payeeElement.select2({
		tags: true,
		createTag: function (params) {
			return {
			  id: params.term,
			  text: params.term,
			  newOption: true
			}
		},
		insertTag: function (data, tag) {
			// Insert the tag at the end of the results
            data.push(tag);

            //set remaining amount to unknown category
            newPayeeCategory = {'id': null, 'text': null};
		},
		templateResult: function (data) {
			var $result = $("<span></span>");

			$result.text(data.text);

			if (data.newOption) {
			  $result.append(" <em>(new)</em>");
			}

			return $result;
		},
		ajax: {
			url:  '/api/assets/payee',
			dataType: 'json',
            delay: 150,
            data: function (params) {
                var queryParameters = {
                  q: params.term,
                  transaction_type: transactionData.transactionType || 'withdrawal',
                  account: transactionData.accountMaster
                }

                return queryParameters;
            },
			processResults: function (data) {
				return {
					results: data
				};
			},
			cache: true
		},
		selectOnClose: true,
		placeholder: "Select payee",
		allowClear: true
	});

	//get default value, if it is set
	if (transactionData.payeeData) {
		//new name is provided
		if(isNaN(transactionData.payeeData)) {
			var option = new Option(transactionData.payeeData, transactionData.payeeData, true, true);
			payeeElement.append(option).trigger('change');
		} else {
			$.ajax({
				type: 'GET',
				url:  'ajax/get_payee_data',
				dataType: 'json',
				data: {
					id: transactionData.payeeData
				}
			}).then(function (data) {
				// create the option and append to Select2
				var option = new Option(data.name, data.id, true, true);
				payeeElement.append(option).trigger('change');

				// manually trigger the `select2:select` event
				payeeElement.trigger({
					type: 'select2:select',
					params: {
						data: data
					}
				});
			});
		}
	}

	payeeElement.on('select2:select', function (e) {
        window.transactionData.payeeData = e.params.data.id;
		$.ajax({
			url:  'ajax/get_default_category_for_payee',
			data: {payee_id: e.params.data.id}
		})
		.done(function( data ) {
			try {
				var result = JSON.parse(data);
			} catch (err) {
				var result = {};
			}
			window.transactionData.newPayeeCategory = result;
		});
	});

	payeeElement.on('select2:unselect', function (e) {
        window.transactionData.newPayeeCategory = null;
        window.transactionData.payeeData = null;
	});


	//To account dropdown functionality

	transactionData.elements.toAccountInput.select2({
		ajax: {
			url:  transactionData.account_to.url,
			dataType: 'json',
			delay: 150,
            data: function (params) {
                var queryParameters = {
                  q: params.term,
                  transaction_type: transactionData.transactionType || 'transfer',
                  account_type: 'to'
                }

                return queryParameters;
            },
			processResults: function (data) {
				//exclude master selection from result list
				var other = document.getElementById("transaction_account_from");
				var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);

				return {
					results: data.filter(function(obj) {return obj.id !== other_id;})
				};
			},
			cache: true
		},
		selectOnClose: true,
		placeholder: "Select account to credit",
		allowClear: true
	});

	transactionData.elements.toAccountInput.on('select2:select', function (e) {
		$.ajax({
			url:  'ajax/get_account_currency_label',
			data: {account_id: e.params.data.id}
		})
		.done(function( data ) {
			transactionData.newCurrencySlave = data;
		});
	});

	transactionData.elements.toAccountInput.on('select2:unselect', function (e) {
		transactionData.newCurrencySlave = null;
	});

	//get default value, if it is set
	if (transactionData.accountSlave) {
		$.ajax({
			type: 'GET',
			url:  'ajax/get_account_data',
			dataType: 'json',
			data: {
				id: transactionData.accountSlave
			}
		}).then(function (data) {
			// create the option and append to Select2
			var option = new Option(data.name, data.id, true, true);
			transactionData.elements.slaveAccountInput.append(option).trigger('change');

			// manually trigger the `select2:select` event
			transactionData.elements.slaveAccountInput.trigger({
				type: 'select2:select',
				params: {
					data: data
				}
			});
		});
	}

	//transaction item copy function
	$(".new_transaction_item").click(function() {
        create_transaction_item();
        $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);
	});

	//setup transaction item removal button functionality
	$(".remove_transaction_item").click(function() {
		$(this).closest(".transaction_item_row").remove();
        transactionData.updateTotals();

        $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);

    });

    //setup toggle detail functionality
    $(".toggle_transaction_detail").click(function(){
        $(this).closest(".transaction_item_row").find(".transaction_detail_container").toggle();
    })

    //item list collapse and expand functionality
    $("#itemListCollapse").click(function(){
        $(".transaction_item_row").find(".transaction_detail_container").hide();
    });
    $("#itemListShow").click(function(){
        $(".transaction_item_row:not(#transaction_item_prototype)").each(function() {
           if(   $(this).find("div.transaction_detail_container input.transaction_item_comment").val() != ""
              || $(this).find("div.transaction_detail_container select").select2('data').length > 0) {
                $(this).find(".transaction_detail_container").show();
            } else {
                $(this).find(".transaction_detail_container").hide();
            }
        });
    });
    $("#itemListExpand").click(function(){
        $(".transaction_item_row").find(".transaction_detail_container").show();
    });

    //setup remaining amount copy function
    $(".load_remainder").click(function() {
        try {
            var element = $(this).closest(".transaction_item_row").find("input.transaction_item_amount");
            var remainingAmount = transactionData.remainingAmountNotAllocated || transactionData.remainingAmountToPayeeDefault;

            var amount = math.eval(element.val() + "+" +remainingAmount);

            element.val(amount);
            transactionData.updateTotals();

		} catch (err) {

		}
    });


	$(".transaction_item_amount").blur(function() {
		/*
			Handle changes to transaction item amount.

			Parse input. Display error, if NaN. Update totals.
		*/

		try {
			var amount = math.eval(this.value.replace(/\s/g,""));
            //console.log('result: ' +amount);
            if(amount <= 0) throw Error("Positive number expected");
			$(this).closest(".form-group").removeClass("has-error");
			$(this).val	(amount);
		} catch (err) {
			$(this).closest(".form-group").addClass("has-error");
		}

		transactionData.updateTotals();
	});

	$("#transaction_amount_master").blur(function() {
		/*
			Handle changes to transaction total in master field.

			Parse input. Display error, if NaN. Update totals and udate slave.
		*/
		var amount = 0;
		try {
			var amount = math.eval(this.value.replace(/\s/g,"")) || amount;
			//console.log('result: ' +amount);
			if(amount <= 0) throw Error("Positive number expected");
            $(this).val	(amount);
		} catch (err) {

        }

        $(this).valid();
		transactionData.newAmountMaster = amount;
	});

	$("#transaction_amount_slave").blur(function() {
		/*
			Handle changes to transaction to in from field.

			Parse input. Display error, if NaN. Update totals.
		*/
		var amount = 0;
		try {
			var amount = math.eval(this.value.replace(/\s/g,"")) || amount;
			//console.log('result: ' +amount);
			if(amount <= 0) throw Error("Positive number expected");
			$(this).val	(amount);
		} catch (err) {

        }

        $(this).valid();
		transactionData.newAmountSlave = amount;
	});

	//on load initializations
	//show at least one transaction item by default, but remove delete button from it
	for (var key in transactionData.itemData) {
		if (transactionData.itemData.hasOwnProperty(key)) {
			create_transaction_item( transactionData.itemData[key]);
		}
	}

	transactionData.updateTotals();
	if (window.transactionData.itemRowCounter == 0) {
		$(".box-header .new_transaction_item").click();
	}

	//remove delete button from first instanceof
	//$(".remove_transaction_item")[1].remove();
    $(".remove_transaction_item").prop('disabled', document.querySelectorAll(".transaction_item_row:not(#transaction_item_prototype)").length <= 1);

	//select transaction type or withdrawal by default
    $("#transaction_type_" + (transactionData.transactionType || 'withdrawal')).click();

    //set callback type
    var defaultCallback = defaultCallback || 'new';
    $("#callback_" + defaultCallback ).click();

	//datepicker
	$('#transaction_date').daterangepicker({
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            format: 'YYYY-MM-DD'
        }
    });



	//form validation
	$("#formTransaction").validate({
		//debug: true,
		ignore: '.ignore, :hidden',
		rules: {
			date: {
				required: function() {
                        return (   $("#entry_type_schedule").is(':not(:checked)')
                                || $("#entry_type_budget").is(':not(:checked)'));
                    },
				dateISO: true
			},
			payee: {
                required: function() {
                    return (   $("#entry_type_schedule").is(':not(:checked)')
                            && $("#entry_type_budget").is(':not(:checked)'))
                            || $("#entry_type_schedule").is(':checked');
                }
			},
			account_master: {
                required: function() {
                        return (   $("#entry_type_schedule").is(':not(:checked)')
                                && $("#entry_type_budget").is(':not(:checked)'))
                                || $("#entry_type_schedule").is(':checked');
                    }
			},
			account_slave: {
				required: '#transaction_type_transfer:checked'
			},
			amount_master: {
				required: true,
				minStrict: 0,
				number: true
			},
			amount_slave: {
				required: true,
				minStrict: 0,
				number: true
			},
			remaining_payee_default: {
				number: true,
				min: 0,
			},
			remaining_not_allocated: {
				number: true,
				min: 0,
            },
            //schedule
            schedule_start: {
				required: function() {
					return (   $("#entry_type_schedule").is(':checked')
					        || $("#entry_type_budget").is(':checked'));
				},
				dateISO: true
            },
            schedule_end: {
				dateISO: true
            },
            schedule_interval: {
                number: true,
                minStrict: 0
            },
            schedule_count: {
                number: true,
                minStrict: 0
            },
            schedule_frequency: {
                required: function() {
					return (   $("#entry_type_schedule").is(':checked')
					        || $("#entry_type_budget").is(':checked'));
				}
            }
		},
		messages: {
			remaining_payee_default: {
				min: 'Must be at least 0. Review amounts.',
			},
			remaining_not_allocated: {
				min: 'Must be at least 0. Review amounts.',
			}
		},
        highlight: function(element, errorClass, validClass) {
            $(element).parent('div').addClass(errorClass).removeClass(validClass);
        },
        unhighlight: function(element, errorClass, validClass) {
            $(element).parent('div').addClass(validClass).removeClass(errorClass);
        },
		errorClass: 'has-error'
    });

    //hide schedule box if no schedule OR budget is selected
    if (   ! $("#entry_type_schedule").prop( "checked" )
        && ! $("#entry_type_budget").prop( "checked" )) {
        $("#schedule_container").hide();
    } else {
        $("#transaction_reconciled").prop( "disabled", true ).prop("checked", false);
        $("#transaction_date").prop( "disabled", true ).prop( "value", "");

        if ($("#entry_type_budget").prop( "checked" )) {
            $("#transaction_type_transfer_label").addClass( "disabled" );
        }
    }

    //adjust inputs based on schedule AND budget selection
    $("#entry_type_schedule, #entry_type_budget").click(function(e) {
        var isSchedule = $("#entry_type_schedule").prop( "checked" );
        var isBudget = $("#entry_type_budget").prop( "checked" );

        if (transactionData.transactionType == "transfer" && isBudget) {
            if (!confirm("Are you sure? This will change transaction type to Withdrawal, and some data might get lost.")) {
                return false;
            }
        }

        if (isSchedule || isBudget) {
            $("#schedule_container").show();
            $("#transaction_reconciled").prop( "disabled", true ).prop("checked", false);
            $("#transaction_date").prop( "disabled", true ).prop( "value", "");

            if (isBudget) {
                $("#transaction_type_transfer_label").addClass( "disabled" );

                //if transaction type was transfer, and budget is selected, switch to withdrawal
                if (transactionData.transactionType == "transfer") {
                    $('#transaction_type_withdrawal').click();
                }
            } else {
                $("#transaction_type_transfer_label").removeClass( "disabled" );
            }
        } else {
            $("#schedule_container").hide();
            $("#transaction_reconciled").prop( "disabled", false );
            $("#transaction_date").prop( "disabled", false );
            $("#transaction_type_transfer_label").removeClass( "disabled" );
        }
    });

    $("#transaction_reconciled").change(function(){
        if (this.checked) {
            $("#entry_type_schedule").prop( "disabled", true ).prop("checked", false);
            $("#entry_type_budget").prop( "disabled", true ).prop("checked", false);
        } else {
            $("#entry_type_schedule").prop( "disabled", false );
            $("#entry_type_budget").prop( "disabled", false );
        }
    });
});

	//custom functions for validator
	$.validator.addMethod('minStrict', function (value, el, param) {
		if (this.optional(el)) {  // "required" not in force and field is empty
			return true;
		}
		$.validator.messages.minStrict = 'Must be greather than zero';
		return value > param;
	});


	function create_transaction_item (itemData) {
		var currentItem = ++window.transactionData.itemRowCounter;

		var template = $( "#transaction_item_prototype" ).clone(true).removeAttr("id").removeClass("hidden")[0];

		// re-define `template`
		template = $(template).attr("id", "transaction_item_row_" + currentItem);

		$("#transaction_item_container").append(template);

		//update input names and other related references
		$("#transaction_item_row_" + currentItem + " select").attr("name", function() { return $(this).attr("name").replace(/#/, currentItem); });
		$("#transaction_item_row_" + currentItem + " input").attr("name", function() { return $(this).attr("name").replace(/#/, currentItem); });

		//update input values
		if (typeof itemData !== 'undefined') {
			$("#transaction_item_row_" + currentItem + " input.transaction_item_amount").val(itemData.amount);
			$("#transaction_item_row_" + currentItem + " input.transaction_item_comment").val(itemData.comment);
		}

        //create select2 functionality
        var newCategorySelect = $("#transaction_item_row_" + currentItem + " select.category");
		newCategorySelect.select2({

			ajax: {
				url:  'ajax/categories',
				dataType: 'json',
                delay: 150,
                data: function (params) {
                    var queryParameters = {
                      q: params.term,
                      active: 1,
                      payee: transactionData.payeeData
                    }

                    return queryParameters;
                },
				processResults: function (data) {
					return {
						results: data
					};
				},
				cache: true
			},
			selectOnClose: true,
			placeholder: "Select category",
			allowClear: true
        });

        if (   typeof itemData !== 'undefined'
            && itemData.category) {
            var newOption = new Option(transactionData.assets.categories[itemData.category], itemData.category, false, false);
            newCategorySelect.append(newOption).trigger('change');
        }

        var newTagSelect = $("#transaction_item_row_" + currentItem + " select.tag");
		newTagSelect.select2({
			tags: true,
			createTag: function (params) {
				return {
				  id: params.term,
				  text: params.term,
				  newOption: true
				}
			},
			insertTag: function (data, tag) {
				// Insert the tag at the end of the results
				data.push(tag);
			},
			templateResult: function (data) {
				var $result = $("<span></span>");

				$result.text(data.text);

				if (data.newOption) {
				  $result.append(" <em>(new)</em>");
				}

				return $result;
			},
			ajax: {
				url:  'ajax/tags',
				dataType: 'json',
				delay: 150,
				processResults: function (data) {
					return {
						results: data
					};
				},
				cache: true
			},
			//selectOnClose: true,
			placeholder: "Select tag(s)",
			allowClear: true
		});

        if (   typeof itemData !== 'undefined'
            && (itemData.tags || {}).length > 0) {
            var tags = [];
            itemData.tags.forEach(function(item) {
                var text = (!isNaN(parseFloat(item)) ? transactionData.assets.tags[item] : item);
                tags.push(item);
                var newOption = new Option(text, item, false, false);
                newTagSelect.append(newOption)
            });
            newTagSelect.val(tags)
            newTagSelect.trigger('change');
        }
    };

//schedule related functions
function updateSchedule () {
    $("#rruleText").html(window.rule.toText());
    //$("#schedule_rrule").val(
    var r =
        window.rrule.RRule.optionsToString({
            freq: window.rule.options.freq,
            dtstart: window.rule.options.dtstart,
            interval: window.rule.options.interval,
            count: window.rule.options.count,
            until: window.rule.options.until
        })
    ;

    //console.log(r);
    //console.log (window.rrule.RRule.fromString(r));
};

var rule;

$( document ).ready(function() {
    window.rule = new rrule.RRule();

    //datepicker
    /* TODO
	$('#schedule_start').datepicker({
        onSelect: function(date, inst) {
           window.rule.options.dtstart = new Date(Date.UTC(inst.selectedYear, inst.selectedMonth, inst.selectedDay, 0, 0));

           updateSchedule ();
        },
        dateFormat: "yy-mm-dd",
        firstDay: 1,
        numberOfMonths: 1,
        showCurrentAtPos: 1
    });
	$('#schedule_end').datepicker({
        onSelect: function(date, inst) {
           window.rule.options.until = new Date(Date.UTC(inst.selectedYear, inst.selectedMonth, inst.selectedDay, 0, 0));
           updateSchedule ();
        },
        dateFormat: "yy-mm-dd",
        firstDay: 1,
        numberOfMonths: 1,
        showCurrentAtPos: 1
    });
    */

    //frequency
    $("#schedule_frequency").change(function(){
        window.rule.options.freq = rrule.Frequency[$(this).val()];
        updateSchedule ();
    })
    //one time setup
    $("#schedule_frequency").change();

    //interval
    $("#schedule_interval").blur(function(){
        var amount = 1;
		try {
			var amount = math.eval(this.value.replace(/\s/g,""));
			//console.log('result: ' +amount);
			if(amount <= 0) throw Error("Positive number expected");
			$(this).closest(".form-group").removeClass("has-error");
			$(this).val	(amount);
		} catch (err) {
			$(this).closest(".form-group").addClass("has-error");
        }

        window.rule.options.interval = amount;
        $(this).valid();
        updateSchedule ();
    });

   //count
   $("#schedule_count").blur(function(){
        var amount = 1;
        try {
            var amount = math.eval(this.value.replace(/\s/g,""));
            //console.log('result: ' +amount);
            if(amount <= 0) throw Error("Positive number expected");
            $(this).closest(".form-group").removeClass("has-error");
            $(this).val	(amount);
        } catch (err) {
            $(this).closest(".form-group").addClass("has-error");
        }

        window.rule.options.count = amount;
        $(this).valid();
        updateSchedule ();
    });

    //click the selective show button once
    $("#itemListShow").click();

});

function clickCancel(){
    if(confirm('Are you sure you want to discard any changes?')) {
        window.history.back();
    }
    return false;
}

</script>


@endsection