@extends('adminlte::page')

@section('title', 'Investment groups')

@section('content_header')
<h1>Investment groups</h1>
@stop

@section('content')

    @if(isset($investmentGroup))
        {{ Form::model($investmentGroup, ['route' => ['investmentgroups.update', $investmentGroup->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'investmentgroups.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($investmentGroup))
                    Modify investment group
                @else
                    Add investment group
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