@extends('adminlte::page')

@section('title', 'Accounts')

@section('content_header')
    <h1>Accounts</h1>
@stop

@section('content')

    @if(isset($account))
        {{ Form::model($account, ['route' => ['accounts.update', $account->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'accounts.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($account))
                    Modify account
                @else
                    Add new account
                @endif
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <div class="form-group">
                {{ Form::label('name', 'Name', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('name', old('name'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('active', 'Active', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::checkbox('active', '1', 1) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('name', 'Opening balance', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('config[opening_balance]', old('config[opening_balance]'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('task_id', 'Account group', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('config[account_group_id]', $allAccountGropus, old('config[account_group_id]'), ['class' => 'form-control']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('task_id', 'Currency', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('config[currency_id]', $allCurrencies, old('config[currency_id]'), ['class' => 'form-control']) }}
                </div>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            {{ Form::hidden('id', old('id')) }}
            {{ Form::hidden('config_type', old('config_type', 'account')) }}
            {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
        </div>
        <!-- /.card-footer -->
    </div>
    <!-- /.card -->

    {{ Form::close() }}

@stop