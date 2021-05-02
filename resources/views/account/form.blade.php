@extends('template.page')

@section('title', 'Accounts')

@section('content_header')
    <h1>Accounts</h1>
@stop

@section('content')

    @if(isset($account))
        <form
            accept-charset="UTF-8"
            action="{{ route('account.update', $account->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('account.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($account->id))
                    Modify account
                @else
                    Add new account
                @endif
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body form-horizontal">
            <div class="form-group">
                <label for="name" class="control-label col-sm-3">
                    Name
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="name"
                        name="name"
                        type="text"
                        value="{{old('name', $account->name ?? '' )}}"
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
                        name="active"
                        type="checkbox"
                        value="1"
                        @if (old())
                            @if (old('active') == '1')
                                checked="checked"
                            @endif
                        @elseif(isset($account))
                            @if ($account->active == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="opening_balance" class="control-label col-sm-3">
                    Opening balance
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="opening_balance"
                        name="config[opening_balance]"
                        type="text"
                        value="{{ old('config.opening_balance', $account['config']['opening_balance'] ?? '' ) }}"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="account_group_id" class="control-label col-sm-3">
                    Account group
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="account_group_id"
                        name="config[account_group_id]"
                    >
                        @forelse($allAccountGroups as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.account_group_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($account))
                                    @if ($account['config']['account_group_id'] == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @empty

                        @endforelse

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="currency_id" class="control-label col-sm-3">
                    Currency
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="currency_id"
                        name="config[currency_id]"
                    >
                        @forelse($allCurrencies as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.currency_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($account))
                                    @if ($account['config']['currency_id'] == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @empty

                        @endforelse

                    </select>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf
            <input
                name="id"
                type="hidden"
                value="{{old('id', $account->id ?? '' )}}"
            >
            <input
                name="config_type"
                type="hidden"
                value="{{old('config_type', 'account' )}}"
            >

            <input class="btn btn-primary" type="submit" value="Save">
            <a href="{{ route('account.index') }}" class="btn btn-secondary cancel confirm-needed">Cancel</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
