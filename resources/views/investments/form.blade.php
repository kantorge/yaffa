@extends('adminlte::page')

@section('title', 'Investments')

@section('content_header')
<h1>Investments</h1>
@stop

@section('content')

    @if(isset($investment))
        {{ Form::model($investment, ['route' => ['investments.update', $investment->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'investments.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($investment))
                    Modify investment
                @else
                    Add new investment
                @endif
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <div class="form-group">
                {{ Form::label('name', \App\Investment::label('name'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('name', old('name'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('active', \App\Investment::label('active'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::checkbox('active', '1', 1) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('name', \App\Investment::label('symbol'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('symbol', old('symbol'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('name', \App\Investment::label('comment'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('comment', old('comment'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('task_id', \App\Investment::label('investment_group'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('investment_groups_id', $allInvestmentGropus, old('investment_groups_id'), ['class' => 'form-control']) }}
                </div>
            </div>

            <div class="form-group">
                {{ Form::label('task_id', \App\Investment::label('currency'), ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('currencies_id', $allCurrencies, old('currencies_id'), ['class' => 'form-control']) }}
                </div>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            {{ Form::hidden('id', old('id')) }}
            {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
        </div>
        <!-- /.card-footer -->
    </div>
    <!-- /.card -->

    {{ Form::close() }}

@stop