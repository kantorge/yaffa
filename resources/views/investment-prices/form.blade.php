@extends('template.layouts.page')

@section('title', __('Investment price'))

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
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('investment-price.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($investmentPrice->id))
                    {{ __('Modify investment price') }}
                @else
                    {{ __('Add new investment price') }}
                @endif
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <div class="form-group">
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
                    >
                </div>
            </div>

            <div class="form-group">
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
                value="{{old('id', $investmentPrice->id ?? '' )}}"
            >
            <input
                name="investment_id"
                type="hidden"
                value="{{ old('investment_id', $investment->id) }}"
            >

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('investment-price.list', ['investment' => $investment->id]) }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
