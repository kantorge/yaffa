@extends('template.layouts.page')

@section('title', 'Categories')

@section('content_header')
<h1>Categories</h1>
@stop

@section('content')

    @if(isset($category))
        <form
            accept-charset="UTF-8"
            action="{{ route('categories.update', $category->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('categories.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($category->id))
                    Modify category
                @else
                    Add new category
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
                        value="{{old('name', $category->name ?? '' )}}"
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
                        @elseif(isset($category))
                            @if ($category['active'] == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="parent_id" class="control-label col-sm-3">
                    Parent
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="parent_id"
                        name="parent_id"
                        placeholder="Parent category"
                    >
                        <option value=''> < No parent category ></option>
                        @foreach($parents as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('parent_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($category))
                                    @if ($category->parent_id == $id))
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @endforeach

                    </select>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="Save">
            <a href="{{ route('categories.index') }}" class="btn btn-secondary cancel confirm-needed">Cancel</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
