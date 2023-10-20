@extends('template.layouts.page')

@section('title_postfix', __('Investments'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investments'))

@section('content')
@if(isset($investment))
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.update', ['type' => 'investment', 'account_entity' => $investment->id]) }}"
    autocomplete="off"
    method="POST"
>
<input name="_method" type="hidden" value="PATCH">
@else
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.store', ['type' => 'investment']) }}"
    autocomplete="off"
    method="POST"
>
@endif

    <div class="card mb-3">
        <div class="card-header">
            <div class="card-title">
                @if(isset($investment->id))
                    {{ __('Modify investment') }}
                @else
                    {{ __('Add new investment') }}
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
                        value="{{old('name', $investment->name ?? '' )}}"
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

            <div class="row mb-3">
                <label for="symbol" class="col-form-label col-sm-3">
                    {{ __('Symbol') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="symbol"
                        name="config[symbol]"
                        type="text"
                        value="{{old('config.symbol', $investment->config->symbol ?? '' )}}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="isin" class="col-form-label col-sm-3">
                    {{ __('ISIN number') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="isin"
                        name="config[isin]"
                        type="text"
                        value="{{old('config.isin', $investment->config->isin ?? '' )}}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="investment_group_id" class="col-form-label col-sm-3">
                    {{ __('Investment group') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-select"
                        id="investment_group_id"
                        name="config[investment_group_id]"
                    >
                        @forelse($allInvestmentGropus as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.investment_group_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['config']['investment_group_id'] == $id)
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

            <div class="row mb-3">
                <label for="currency_id" class="col-form-label col-sm-3">
                    {{ __('Currency') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-select"
                        id="currency_id"
                        name="config[currency_id]"
                    >
                        @forelse($allCurrencies as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.currency_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['config']['currency_id'] == $id)
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

            <div class="row mb-3">
                <label for="investment_price_provider" class="col-form-label col-sm-3">
                    {{ __('Price provider') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-select"
                        id="investment_price_provider"
                        name="config[investment_price_provider]"
                    >
                        <option value=''>{{ __(' < No price provider > ') }}</option>
                        @forelse($allInvestmentPriceProviders as $id => $properties)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.investment_price_provider') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($investment))
                                    @if ($investment['config']['investment_price_provider'] == $id)
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

            <div class="row mb-3">
                <label for="auto_update" class="col-form-label col-sm-3">
                    {{ __('Automatic update') }}
                </label>
                <div class="col-sm-9">
                    <input
                        id="auto_update"
                        class="form-check-input"
                        name="config[auto_update]"
                        type="checkbox"
                        value="1"
                        @if (old())
                            @if (old('config.auto_update') == '1')
                                checked="checked"
                            @endif
                        @elseif(isset($investment))
                            @if ($investment->config->auto_update == '1')
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
            <input name="config_type" type="hidden" value="investment">

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a
                    href="{{ route('account-entity.index', ['type' => 'investment']) }}"
                    class="btn btn-secondary cancel confirm-needed"
            >
                {{ __('Cancel') }}
            </a>
        </div>
    </div>
</form>
@stop
