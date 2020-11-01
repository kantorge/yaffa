@extends('adminlte::page')

@section('title', 'Currencies')

@section('content_header')
<h1>Currencies</h1>
@stop

@section('content')

    @if(isset($currency))
        {{ Form::model($currency, ['route' => ['currencies.update', $currency->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'currencies.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">Add new currency</h3>
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
                {{ Form::label('iso_code', 'ISO Code', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('iso_code', old('iso_code'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>
            <div class="form-group">
                {{ Form::label('num_digits', 'Number of digits', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('num_digits', old('num_digits'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>
            <div class="form-group">
                {{ Form::label('suffix', 'Suffix', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::text('suffix', old('suffix'), ['class' => 'form-control', 'autocomplete' => 'off']) }}
                </div>
            </div>
            <div class="form-group">
                {{ Form::label('base', 'Base currency', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::checkbox('base', '1') }}
                </div>
            </div>
            <div class="form-group">
                {{ Form::label('auto_update', 'Automatic update', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::checkbox('auto_update', '1') }}
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