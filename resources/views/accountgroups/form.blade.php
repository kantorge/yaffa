@extends('template.page')

@section('title', 'Account groups')

@section('content_header')
    <h1>Account groups</h1>
@stop

@section('content')

    @if(isset($accountGroup))
        <form
            accept-charset="UTF-8"
            action="{{ route('accountgroups.update', $accountGroup->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('accountgroups.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($accountGroup->id))
                    Modify account group
                @else
                    Add account group
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
                        value="{{old('name', $accountGroup->name ?? '' )}}"
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf
            <input
                name="id"
                type="hidden"
                value="{{old('id', $account['id'] ?? '' )}}"
            >

            <input class="btn btn-primary" type="submit" value="Save">
            <a href="{{ route('accountgroups.index') }}" class="btn btn-secondary cancel confirm-needed">Cancel</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop