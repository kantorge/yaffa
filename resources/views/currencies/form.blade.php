@extends('template.layouts.page')

@section('title', __('Currencies'))

@section('content_header', __('Currencies'))

@section('content')

    @if(isset($currency))
        <form
            accept-charset="UTF-8"
            action="{{ route('currencies.update', $currency->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('currencies.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($currency->id))
                    {{ __('Modify currency') }}
                @else
                    {{ __('Add currency') }}
                @endif
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body form-horizontal">
            <div class="form-group">
                <label for="name" class="control-label col-sm-3">
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

            <div class="form-group">
                <label for="iso_code" class="control-label col-sm-3">
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
            <div class="form-group">
                <label for="num_digits" class="control-label col-sm-3">
                    {{ __('Number of decimal digits to display') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="num_digits"
                        name="num_digits"
                        type="text"
                        value="{{old('num_digits', $currency->num_digits ?? '' )}}"
                    >
                </div>
            </div>
            <div class="form-group">
                <label for="suffix" class="control-label col-sm-3">
                    {{ __('Suffix') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="suffix"
                        name="suffix"
                        type="text"
                        value="{{old('suffix', $currency->suffix ?? '' )}}"
                    >
                </div>
            </div>
            <div class="form-group">
                <label for="base" class="control-label col-sm-3">
                    {{ __('Base currency') }}
                </label>
                <div class="col-sm-9">
                    <input
                        id="base"
                        class="checkbox-inline"
                        name="base"
                        type="checkbox"
                        value="1"
                        @if (old())
                            @if (old('base') == '1')
                                checked="checked"
                            @endif
                        @elseif(isset($currency))
                            @if ($currency->base == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>
            <div class="form-group">
                <label for="auto_update" class="control-label col-sm-3">
                    {{ __('Automatic update') }}
                </label>
                <div class="col-sm-9">
                    <input
                        id="auto_update"
                        class="checkbox-inline"
                        name="auto_update"
                        type="checkbox"
                        value="1"
                        @if (old())
                            @if (old('auto_update') == '1')
                                checked="checked"
                            @endif
                        @elseif(isset($currency))
                            @if ($currency->auto_update == '1')
                                checked="checked"
                            @endif
                        @endif
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf
            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('currencies.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
