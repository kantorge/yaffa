@extends('adminlte::page')

@section('classes_body', "layout-footer-fixed")

@section('title', 'Transaction')

@section('content')

    <!-- form start -->
    @if(isset($transaction->id))
        <form
            accept-charset="UTF-8"
            action="{{ route('transactions.updateInvestment', ['transaction' => $transaction->id]) }}"
            autocomplete="off"
            id="formTransaction"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('transactions.storeInvestment') }}"
            autocomplete="off"
            id="formTransaction"
            method="POST"
        >
    @endif

	<div class="row">
        <!-- main column -->
        <div class="col-md-12">
        	<!-- general form elements -->
        	<div class="card card-primary">
            	<div class="card-header with-border">
              		<h3 class="card-title">
                        @if(isset($transaction->id))
                            Modify transaction
                        @else
                            Add transaction
                        @endif
			  		</h3>
            	</div>
            	<!-- /.card-header -->

              	<div class="card-body">
					<div class="row">
						<div class="col-md-6">
							<div class="row">
								<div class="col-md-6">
                					<div class="form-group valid">
                                        <label for="transaction_type" class="control-label">Transaction type</label>
                                        <!--TODO: make the list dynamic-->
                                        <select name="transaction_type" id="transaction_type" class="form-control" aria-invalid="false">
                                            <option value="Buy">Buy</option>
                                            <option value="Sell">Sell</option>
                                            <option value="Add shares">Add shares</option>
                                            <option value="7">Remove shares</option>
                                            <option value="8">Dividend</option>
                                            <option value="9">S-Term Cap Gains Dist</option>
                                            <option value="10">L-Term Cap Gains Dist</option>
                                        </select>
									</div>
								</div>
								<div class="col-md-6">
                					<div class="form-group">
                                        <label for="transaction_account" class="control-label">Account</label>
                                        <select name="account" id="transaction_account" class="form-control">
                                        </select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-3">
		                			<div class="form-group">
                                    <label for="transaction_date" class="control-label">Date</label>
                                    <input type="text" name="date" value="" id="transaction_date" maxlength="10" class="form-control">
									</div>
                                </div>
                                <div class="col-md-3">
		                			<div class="form-group">
                                        <input type="checkbox" name="entry_type_schedule" value="schedule" id="entry_type_schedule" class="checkbox-inline">
                                        <label for="entry_type_schedule" class="control-label">Scheduled</label>
                                    </div>
                                </div>
								<div class="col-md-6">
									<div class="form-group">
                                        <label for="transaction_investment" class="control-label">Investment</label>
                                        <select name="investment" id="transaction_investment" class="form-control">
                                        </select>
									</div>
								</div>
                            </div>
                            <div class="row">
								<div class="col-md-12">
							        <div class="form-group">
                                        <label for="transaction_comment" class="control-label">Comment</label>
                                        <input type="text" name="comment" value="" id="transaction_comment" maxlength="255" class="form-control">
							        </div>
                                </div>
                            </div>
                        </div>
						<div class="col-md-6">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
                                        <label for="transaction_quantity" class="control-label">Quantity</label>
                                        <input type="text" name="quantity" value="" data-control="Quantity" id="transaction_quantity" maxlength="10" class="form-control input-with-math">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
                                        <label for="transaction_price" class="control-label">Price</label>
                                        <input type="text" name="price" value="" data-control="Price" id="transaction_price" maxlength="10" class="form-control input-with-math">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
                                        <label for="transaction_commission" class="control-label">Commission</label>
                                        <input type="text" name="commission" value="" data-control="Commission" id="transaction_commission" maxlength="10" class="form-control input-with-math">
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
                                        <label for="transaction_dividend" class="control-label">Amount</label>
                                        <input type="text" name="dividend" value="" data-control="Dividend" id="transaction_dividend" maxlength="10" class="form-control input-with-math" disabled="">
									</div>
								</div>
							</div>
							<div class="form-group">
                                <label for="transaction_total" class="control-label">Total <span class="transaction_currency"></span></label>
                                <input type="text" name="total" value="" id="transaction_total" maxlength="10" class="form-control" disabled="disabled">
							</div>
						</div>
					</div>

        		</div>
            	<!-- /.card-body -->

                <footer class="main-footer layout-footer-fixed">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-sm-2">
                                <label for="callback" class="control-label">After saving</label>
                            </div>
                            <div class="col-sm-8">
                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                    <label class="btn btn-outline-secondary" id="callback_new_label">
                                        <input name="callback" type="radio" value="new" id="callback_new">
                                        Add an other transaction
                                    </label>
                                    <label class="btn btn-outline-secondary" id="callback_returnToAccount_label">
                                        <input name="callback" type="radio" value="returnToAccount" id="callback_return_to_account">
                                        Return to selected account
                                    </label>
                                    <label class="btn btn-outline-secondary" id="callback_returnToDashboard_label">
                                        <input name="callback" type="radio" value="returnToDashboard" id="callback_return_to_dashboard">
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
                </footer>
          </div>
          <!-- /.box -->

          @include('transactions.schedule')

        </div>
        <!--/.col (left) -->
    </div>
    <!-- /.row -->

    @csrf
    <input
        name="id"
        type="hidden"
        value="{{old('id', $transaction['id'])}}"
    >
    <input
        name="config_type"
        type="hidden"
        value="transaction_detail_investment"
    >
</form>

@endsection