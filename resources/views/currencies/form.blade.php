@extends('template.layouts.page')

@section('title_postfix',  __('Currencies'))

@section('content_container_classes', 'container-sm')

@section('content_header', __('Currencies'))

@section('content')

@isset($currency)
<form
    accept-charset="UTF-8"
    action="{{ route('currencies.update', $currency->id) }}"
    autocomplete="off"
    method="POST"
>
    @method('PATCH')
@else
<form
    accept-charset="UTF-8"
    action="{{ route('currencies.store') }}"
    autocomplete="off"
    method="POST"
>
@endisset

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @isset($currency->id)
                    {{ __('Modify currency') }}
                @else
                    {{ __('Add currency') }}
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
                        id="name"
                        name="name"
                        type="text"
                        value="{{old('name', $currency->name ?? '' )}}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="iso_code" class="col-form-label col-sm-3">
                    {{ __('ISO Code') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="iso_code"
                        name="iso_code"
                        type="text"
                        value="{{old('iso_code', $currency->iso_code ?? '' )}}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="auto_update" class="col-form-label col-sm-3">
                    {{ __('Automatic update') }}
                </label>
                <div class="col-sm-9">
                    <input
                        id="auto_update"
                        class="form-check-input"
                        name="auto_update"
                        type="checkbox"
                        value="1"
                        @if ((old() && old('auto_update') == '1') || (isset($currency) && $currency->auto_update == '1'))
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="generic_decimal_precision" class="col-form-label col-sm-3">
                    {{ __('Generic decimal precision') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="generic_decimal_precision"
                        name="generic_decimal_precision"
                        type="number"
                        min="0"
                        value="{{ old('generic_decimal_precision', $currency->generic_decimal_precision ?? '') }}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="detailed_decimal_precision" class="col-form-label col-sm-3">
                    {{ __('Detailed decimal precision') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="detailed_decimal_precision"
                        name="detailed_decimal_precision"
                        type="number"
                        min="0"
                        value="{{ old('detailed_decimal_precision', $currency->detailed_decimal_precision ?? '') }}"
                    >
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf
            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('currencies.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
