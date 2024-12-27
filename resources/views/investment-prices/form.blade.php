@extends('template.layouts.page')

@section('title', __('Investment price'))

@section('content_container_classes', 'container-lg')

@section('content_header')
    {{ __('Investment price') }} - {{$investment->name}}
@stop

@section('content')
@if(isset($investmentPrice))
    <form
        accept-charset="UTF-8"
        action="{{ route('investment-price.update', $investmentPrice->id) }}"
        autocomplete="off"
        method="POST"
        dusk="form-investment-price"
    >
        @method('PATCH')
@else
    <form
        accept-charset="UTF-8"
        action="{{ route('investment-price.store') }}"
        autocomplete="off"
        method="POST"
        dusk="form-investment-price"
    >
@endif

    <div class="card mb-3">
        <div class="card-header">
            <div class="card-title">
                @if(isset($investmentPrice->id))
                    {{ __('Modify investment price') }}
                @else
                    {{ __('Add new investment price') }}
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <label for="date" class="col-form-label col-sm-3">
                    {{ __('Date') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="date"
                        name="date"
                        type="text"
                        value="{{old('date', $investmentPrice->date ?? '' )}}"
                        dusk="input-date"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="price" class="col-form-label col-sm-3">
                    {{ __('Price') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="price"
                        name="price"
                        type="text"
                        value="{{ old('price', $investmentPrice['price'] ?? '' ) }}"
                        dusk="input-price"
                    >
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf
            <input
                name="id"
                type="hidden"
                value="{{old('id', $investmentPrice->id ?? '' )}}"
            >
            <input
                name="investment_id"
                type="hidden"
                value="{{ old('investment_id', $investment->id) }}"
            >

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}" dusk="button-submit">
            <a href="{{ route('investment-price.list', ['investment' => $investment->id]) }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
