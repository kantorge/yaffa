@extends('template.page')

@section('title', 'Investment price')

@section('content_header')
    <h1>Investment price - {{$investment->name}}</h1>
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

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($investmentPrice->id))
                    Modify investment price
                @else
                    Add new investment price
                @endif
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body form-horizontal">
            <div class="form-group">
                <label for="date" class="control-label col-sm-3">
                    Date
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
                <label for="price" class="control-label col-sm-3">
                    Price
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

            <input class="btn btn-primary" type="submit" value="Save">
            <a href="{{ route('investment-price.list', ['investment' => $investment->id]) }}" class="btn btn-secondary cancel confirm-needed">Cancel</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
