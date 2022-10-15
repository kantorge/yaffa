@extends('template.layouts.page')

@section('title', __('Investments'))

@section('content_header', __('Investments'))

@section('content')

    @if(isset($investment))
        <form
            accept-charset="UTF-8"
            action="{{ route('investment.update', $investment->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('investment.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($investment->id))
                    {{ __('Modify investment') }}
                @else
                    {{ __('Add new investment') }}
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
                        value="{{old('name', $investment->name ?? '' )}}"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="active" class="control-label col-sm-3">
                    {{ __('Active') }}
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
                        @elseif(isset($investment))
                            @if ($investment->active == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="symbol" class="control-label col-sm-3">
                    {{ __('Symbol') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="symbol"
                        name="symbol"
                        type="text"
                        value="{{old('symbol', $investment->symbol ?? '' )}}"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="isin" class="control-label col-sm-3">
                    {{ __('ISIN number') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="isin"
                        name="isin"
                        type="text"
                        value="{{old('isin', $investment->isin ?? '' )}}"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="comment" class="control-label col-sm-3">
                    {{ __('Comment') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="comment"
                        name="comment"
                        type="text"
                        value="{{old('comment', $investment->comment ?? '' )}}"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="investment_group_id" class="control-label col-sm-3">
                    {{ __('Investment group') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="investment_group_id"
                        name="investment_group_id"
                    >
                        @forelse($allInvestmentGropus as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('investment_group_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['investment_group_id'] == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @empty

                        @endforelse

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="currency_id" class="control-label col-sm-3">
                    {{ __('Currency') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="currency_id"
                        name="currency_id"
                    >
                        @forelse($allCurrencies as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('currency_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['currency_id'] == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @empty

                        @endforelse

                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="investment_price_provider" class="control-label col-sm-3">
                    {{ __('Price provider') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="investment_price_provider"
                        name="investment_price_provider"
                    >
                        <option value=''>{{ __(' < No price provider > ') }}</option>
                        @forelse($allInvestmentPriceProviders as $id => $properties)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('investment_price_provider') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['investment_price_provider'] == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $properties['name'] }}
                            </option>
                        @empty

                        @endforelse

                    </select>
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
                        @elseif(isset($investment))
                            @if ($investment->auto_update == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('investment.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop
