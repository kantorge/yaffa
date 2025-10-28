@extends('template.layouts.page')

@section('title_postfix',  __('Categories'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Categories'))

@section('content')
@if(isset($category))
<form
    accept-charset="UTF-8"
    action="{{ route('categories.update', $category->id) }}"
    autocomplete="off"
    method="POST"
>
@method('PATCH')
@else
<form
    accept-charset="UTF-8"
    action="{{ route('categories.store') }}"
    autocomplete="off"
    method="POST"
>
@endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @if(isset($category->id))
                    {{ __('Modify category') }}
                @else
                    {{ __('Add new category') }}
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label for="name" class="col-form-label col-sm-3">
                    {{ __('Name') }}
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

            <div class="row mb-3">
                <label for="active" class="col-form-label col-sm-3">
                    {{ __('Active') }}
                </label>
                <div class="col-sm-9">
                    <input
                        id="active"
                        class="form-check-input"
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

            <div class="row mb-3">
                <label for="parent_id" class="col-form-label col-sm-3">
                    {{ __('Parent category') }}
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

            <div class="row mb-3">
                <label for="default_aggregation" class="col-form-label col-sm-3">
                    {{ __('Default aggregation') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="default_aggregation"
                        name="default_aggregation"
                    >
                        <option value="month" @if (old('default_aggregation') == 'month') selected="selected" @elseif(isset($category) && $category->default_aggregation == 'month') selected="selected" @endif>{{ __('Month') }}</option>
                        <option value="quarter" @if (old('default_aggregation') == 'quarter') selected="selected" @elseif(isset($category) && $category->default_aggregation == 'quarter') selected="selected" @endif>{{ __('Quarter') }}</option>
                        <option value="year" @if (old('default_aggregation') == 'year') selected="selected" @elseif(isset($category) && $category->default_aggregation == 'year') selected="selected" @endif>{{ __('Year') }}</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('categories.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
