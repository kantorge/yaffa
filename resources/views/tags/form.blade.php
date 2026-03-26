@extends('template.layouts.page')

@section('title_postfix',  __('Tags'))

@section('content_container_classes', 'container-lg')

@section('content_header',  __('Tags'))

@section('content')
@isset($tag)
<form
    accept-charset="UTF-8"
    action="{{ route('tags.update', $tag) }}"
    autocomplete="off"
    dusk="form-tag"
    method="POST"
>
@method('PATCH')
@else
<form
    accept-charset="UTF-8"
    action="{{ route('tags.store') }}"
    autocomplete="off"
    dusk="form-tag"
    method="POST"
>
@endisset

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @isset($tag->id)
                    {{ __('Modify tag') }}
                @else
                    {{ __('Add tag') }}
                @endisset
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
                        dusk="form-tag-field-name"
                        id="name"
                        name="name"
                        type="text"
                        value="{{old('name', $tag->name ?? '' )}}"
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
                        @elseif(isset($tag))
                            @if ($tag['active'] == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('tags.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
