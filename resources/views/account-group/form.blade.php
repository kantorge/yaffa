@extends('template.layouts.page')

@section('title_postfix',  __('Account groups'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Account groups'))

@section('content')
@if(isset($accountGroup))
<form
    accept-charset="UTF-8"
    action="{{ route('account-group.update', $accountGroup->id) }}"
    autocomplete="off"
    method="POST"
>
<input name="_method" type="hidden" value="PATCH">
@else
<form
    accept-charset="UTF-8"
    action="{{ route('account-group.store') }}"
    autocomplete="off"
    method="POST"
>
@endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @if(isset($accountGroup->id))
                    {{ __('Modify account group') }}
                @else
                    {{ __('Add account group') }}
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
                        value="{{old('name', $accountGroup->name ?? '' )}}"
                    >
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf
            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('account-group.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
