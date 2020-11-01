@extends('adminlte::page')

@section('title', 'Payees')

@section('content_header')
<h1>Payees</h1>
@stop

@section('content')

    @if(isset($payee))
        {{ Form::model($payee, ['route' => ['payees.update', $payee->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'payees.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($payee))
                    Modify payee
                @else
                    Add new payee
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
                {{ Form::label('payee_id', 'Category', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('config[categories_id]', $categories, old('config[categories_id]'), ['class' => 'form-control', 'placeholder' => 'Default category']) }}
                </div>
            </div>
        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            {{ Form::hidden('id', old('id')) }}
            {{ Form::hidden('config_type', old('config_type', 'payee')) }}
            {{ Form::submit('Save', ['class' => 'btn btn-primary']) }}
        </div>
        <!-- /.card-footer -->
    </div>
    <!-- /.card -->

    {{ Form::close() }}

@stop