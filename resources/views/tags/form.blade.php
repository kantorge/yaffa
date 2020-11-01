@extends('adminlte::page')

@section('title', 'Tags')

@section('content_header')
<h1>Tags</h1>
@stop

@section('content')

    @if(isset($tag))
        {{ Form::model($tag, ['route' => ['tags.update', $tag->id], 'method' => 'patch']) }}
    @else
        {{ Form::open(['route' => 'tags.store']) }}
    @endif

    <div class="card card-primary">
        <div class="card-header">
            <h3 class="card-title">
                @if(isset($tag))
                    Modify tag
                @else
                    Add tag
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