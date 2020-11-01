@extends('adminlte::page')

@section('title', 'Categories')

@section('content_header')
<h1>Categories</h1>
@stop

@section('content')

    @if(isset($category))
        {{ Form::model($category, ['route' => ['categories.update', $category->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'categories.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($account))
                    Modify category
                @else
                    Add new category
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
                {{ Form::label('parent_id', 'Parent', ['class' => 'control-label col-xs-3']) }}
                <div class="col-xs-9">
                    {{ Form::select('parent_id', $parents, old('parent_id'), ['class' => 'form-control', 'placeholder' => 'Parent']) }}
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