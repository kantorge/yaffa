@extends('adminlte::page')

@section('classes_body', "layout-footer-fixed")

@section('title', 'Transaction')

@section('content')

    <!-- form start -->
    @if(isset($transaction->id))
        <form
            accept-charset="UTF-8"
            action="{{ route('transactions.update', ['transaction' => $transaction->id]) }}"
            autocomplete="off"
            id="formTransaction"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('transactions.storeStandard') }}"
            autocomplete="off"
            id="formTransaction"
            method="POST"
        >
    @endif

    <div class="card card-primary">
        <div class="card-header">
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
                                    <label class="control-label col-sm-3">
                                        Type
                                    </label>
                                    <div class="col-sm-9">
                                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                            <label class="btn btn-primary" id="transaction_type_withdrawal_label">
                                                <input
                                                    id="transaction_type_withdrawal"
                                                    name="transaction_type"
                                                    type="radio"
                                                    value="withdrawal"
                                                    {{ (isset($transaction['transactionType'])
                                                        ? ($transaction['transactionType']['name'] == 'withdrawal' ? 'checked="checked"': '')
                                                        : '') }}
                                                >
                                                Withdrawal
                                            </label>
                                            <label class="btn btn-primary" id="transaction_type_deposit_label">
                                                <input
                                                    id="transaction_type_deposit"
                                                    name="transaction_type"
                                                    type="radio"
                                                    value="deposit"
                                                    {{ (isset($transaction['transactionType'])
                                                        ? ($transaction['transactionType']['name'] == 'deposit' ? 'checked="checked"': '')
                                                        : '') }}
                                                >
                                                Deposit
                                            </label>
                                            <label class="btn btn-primary" id="transaction_type_transfer_label">
                                                <input
                                                    id="transaction_type_transfer"
                                                    name="transaction_type"
                                                    type="radio"
                                                    value="transfer"
                                                    {{ (isset($transaction['transactionType'])
                                                        ? ($transaction['transactionType']['name'] == 'transfer' ? 'checked="checked"': '')
                                                        : '') }}
                                                >
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
                                        <input
                                            class="form-control"
                                            id="transaction_date"
                                            maxlength="10"
                                            name="date"
                                            type="text"
                                            value="{{old('date', $transaction['date'])}}"
                                        >
                                    </div>
                                    <div class="col-sm-3">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label" id="account_from_label">
                                        Account from
                                    </label>
                                    <div class="col-sm-9">
                                        <select id="account_from" class="form-control" name="config[account_from_id]">
                                            @if(isset($transaction['config']['account_from_id']))
                                                <option value="{{ $transaction['config']['accountFrom']['id'] }}" selected="selected">{{ $transaction['config']['accountFrom']['name'] }}</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <label class="control-label col-sm-3" id="account_to_label">
                                        Payee
                                    </label>
                                    <div class="col-sm-9">
                                        <select id="account_to" class="form-control" name="config[account_to_id]">
                                            @if(isset($transaction['config']['account_to_id']))
                                                <option value="{{ $transaction['config']['accountTo']['id'] }}" selected="selected">{{ $transaction['config']['accountTo']['name'] }}</option>
                                            @endif
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
                                            value="{{old('comment', $transaction['comment'])}}"
                                        >
                                    </div>
                                </div>

                                <div class="form-group row" id="entry_type_container">
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_schedule"
                                            class="checkbox-inline"
                                            name="schedule"
                                            type="checkbox"
                                            value="1"
                                            {{ ((old('schedule', $transaction['schedule'])) ? 'checked' : '') }}
                                        >
                                        <label for="entry_type_schedule" class="control-label">
                                            Scheduled
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_budget"
                                            class="checkbox-inline"
                                            name="budget"
                                            type="checkbox"
                                            value="1"
                                            {{ ((old('budget', $transaction['budget'])) ? 'checked' : '') }}
                                        >
                                        <label for="entry_type_budget" class="control-label">
                                            Budget
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="transaction_reconciled"
                                            class="checkbox-inline"
                                            name="reconciled"
                                            type="checkbox"
                                            value="1"
                                            {{ ((old('reconciled', $transaction['reconciled'])) ? 'checked' : '') }}
                                        >
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
                        <div class="card-body" id="transaction_item_container">
                            <div class="list-group">

                                @forelse($transaction->transactionItems as $key => $item)
                                    @include('transactions.item', ['counter' => $key + 1, 'item' => $item])
                                @empty
                                    @include('transactions.item', ['counter' => 1])
                                @endforelse

                            </div>
                        </div>
                        <!-- /.card-body -->

                        <div class="card-footer">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="form-group col-sm-4" id="amount_from_group">
                                            <label for="transaction_amount_from" class="control-label">
                                                Amount from <span class='transaction_currency_from'></span>
                                            </label>
                                            <input
                                                class="form-control valid"
                                                id="transaction_amount_from"
                                                maxlength="50"
                                                name="config[amount_from]"
                                                type="text"
                                                value="{{old('config[amount_from]', $transaction['config']['amount_from'] ?? '')}}"
                                            >
                                        </div>
                                        <div class="col-sm-4" id="transfer_exchange_rate_group">
                                            <span>Exchange rate</span>
                                            <span id="transfer_exchange_rate"></span>
                                        </div>
                                        <div class="form-group col-sm-4 pull-right" id="amount_to_group">
                                            <label for="transaction_amount_slave" class="control-label">
                                                Amount to <span class='transaction_currency_to'></span>
                                            </label>
                                            <input
                                                class="form-control valid"
                                                id="transaction_amount_to"
                                                maxlength="50"
                                                name="config[amount_to]"
                                                type="text"
                                                value="{{old('config[amount_to]', $transaction['config']['amount_to'] ?? '')}}"
                                            >
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
                                                        <input
                                                            name="remaining_payee_default"
                                                            id="remaining_payee_default_input"
                                                            type="hidden"
                                                            value=""
                                                        >
                                                    </td>
                                                </tr>
                                                <tr id="remaining_not_allocated_container">
                                                    <th>Remaining amount not allocated:</th>
                                                    <td class="text-right">
                                                        <span id="remaining_not_allocated">0</span>
                                                        <span class='transaction_currency_from_nowrap'></span>
                                                        <input
                                                            name="remaining_not_allocated"
                                                            id="remaining_not_allocated_input"
                                                            type="hidden"
                                                            value=""
                                                        >
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

                    @include('transactions.schedule')

                </div>
                <!--/.col (right) -->

            </div>
            <!-- /.row -->

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
                                <label class="btn btn-outline-secondary" id="callback_clone_label">
                                    <input name="callback" type="radio" value="clone" id="callback_clone">
                                    Clone this transaction
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
        <!-- /.card-body -->
        <div class="card-footer">
            @csrf
            <input
                name="id"
                type="hidden"
                value="{{old('id', $transaction['id'])}}"
            >
            <input
                name="config_type"
                type="hidden"
                value="transaction_detail_standard"
            >
        </div>
        <!-- /.card-footer -->
    </div>
    <!-- /.card -->

    </form>

    <!-- transaction item prototype start -->
    @include('transactions.item', ['counter' => '#', 'item' => []])
    <!-- transaction item prototype end -->

@endsection