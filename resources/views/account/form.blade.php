@extends('template.layouts.page')

@section('title_postfix',  __('Accounts'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Accounts'))

@section('content')
@if(isset($account))
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.update', ['type' => 'account', 'account_entity' => $account->id]) }}"
    autocomplete="off"
    method="POST"
>
<input name="_method" type="hidden" value="PATCH">
@else
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.store', ['type' => 'account']) }}"
    autocomplete="off"
    method="POST"
>
@endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @if(isset($account->id))
                    {{ __('Modify account') }}
                @else
                    {{ __('Add new account') }}
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
                        value="{{old('name', $account->name ?? '' )}}"
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
                        @elseif(isset($account))
                            @if ($account->active == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="opening_balance" class="col-form-label col-sm-3">
                    {{ __('Opening balance') }}
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="opening_balance"
                        name="config[opening_balance]"
                        type="text"
                        value="{{ old('config.opening_balance', $account['config']['opening_balance'] ?? '' ) }}"
                    >
                </div>
            </div>

            <div class="row mb-3">
                <label for="account_group_id" class="col-form-label col-sm-3">
                    {{ __('Account group') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-select"
                        id="account_group_id"
                        name="config[account_group_id]"
                    >
                        @forelse($allAccountGroups as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.account_group_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($account))
                                    @if ($account['config']['account_group_id'] == $id)
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
                                @elseif(isset($account))
                                    @if ($account['config']['currency_id'] == $id)
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
                <label for="alias" class="col-form-label col-sm-3">
                    {{ __('Import alias') }}
                </label>
                <div class="col-sm-9">
                            <textarea
                                    class="form-control"
                                    id="alias"
                                    name="alias"
                            >{{old('alias', $account->alias ?? '' )}}</textarea>
                </div>
            </div>

            <div class="row mb-3">
                <label for="default_date_range" class="col-form-label col-sm-3">
                    {{ __('Default date range for account details') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-select"
                        id="default_date_range"
                        name="config[default_date_range]"
                    >
                        <option value="">{{ __('Inherit user setting') }}</option>
                        <option value="none">{{ __("Don't load data by default") }}</option>
                        @foreach (config('yaffa.account_date_presets') as $group)
                            <optgroup label="{{ __($group['label']) }}">
                                @foreach ($group['options'] as $option)
                                    <option
                                        value="{{ $option['value'] }}"
                                        @if (old())
                                            @if (old('config.default_date_range') == $option['value'])
                                                selected="selected"
                                            @endif
                                        @elseif(isset($account))
                                            @if (($account['config']['default_date_range'] ?? null) == $option['value'])
                                                selected="selected"
                                            @endif
                                        @endif
                                    >
                                        {{ __($option['label']) }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf
            <input name="config_type" type="hidden" value="account">

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('account-entity.index', ['type' => 'account']) }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
