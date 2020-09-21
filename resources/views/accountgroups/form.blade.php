@extends('adminlte::page')

@section('title', 'Account groups')

@section('content_header')
<h1>Account groups</h1>
@stop

@section('content')

    @if(isset($accountGroup))
        {{ Form::model($accountGroup, ['route' => ['accountgroups.update', $accountGroup->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'accountgroups.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($accountGroup))
                    Modify account group
                @else
                    Add account group
                @endif
            </h3>
        </div>
        <!-- /.card-header -->
        <div class="card-body">
            <div class="form-group">
                {{ Form::label('name', \App\AccountGroup::label('name'), ['class' => 'control-label col-xs-3']) }}
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