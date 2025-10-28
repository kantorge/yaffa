@extends('template.layouts.page')

@section('title_postfix',  __('Payees'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Payees'))

@section('content')
<h2>
    @if(isset($payee->id))
        {{ __('Modify payee') }}
    @else
        {{ __('Add new payee') }}
    @endif
</h2>

@if(isset($payee))
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.update', ['type' => 'payee', 'account_entity' => $payee->id]) }}"
    autocomplete="off"
    method="POST"
>
@method('PATCH')
@else
<form
    accept-charset="UTF-8"
    action="{{ route('account-entity.store', ['type' => 'payee']) }}"
    autocomplete="off"
    method="POST"
>
@endif

<div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        {{ __('Payee details') }}
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
                                value="{{old('name', $payee->name ?? '' )}}"
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
                                @elseif(isset($payee))
                                    @if ($payee['active'] == '1')
                                        checked="checked"
                                    @endif
                                @else
                                    checked="checked"
                                @endif
                            >
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="category_id" class="col-form-label col-sm-3">
                            {{ __('Default category') }}
                        </label>
                        <div class="col-sm-9">
                            <select
                                class="form-select"
                                id="category_id"
                                name="config[category_id]"
                            >
                                <option value=''>{{ __(' < No default category > ') }}</option>
                                @forelse($categories as $id => $name)
                                    <option
                                        value="{{ $id }}"
                                        @if (old())
                                            @if (old('config.category_id') == $id)
                                                selected="selected"
                                            @endif
                                        @elseif(isset($payee))
                                            @if ($payee->config->category_id == $id)
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
                            >{{old('alias', $payee->alias ?? '' )}}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    @csrf
                    <input name="config_type" type="hidden" value="payee">

                    <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
                    <a href="{{ route('account-entity.index', ['type' => 'payee']) }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        {{ __('Category preferences') }}
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="preferred" class="form-label">{{ __('Preferred categories for payee') }}</label>
                        <select class="form-select" id="preferred" name="config[preferred][]" data-other-select="#not_preferred"></select>
                    </div>
                    <div class="mb-3">
                        <label for="not_preferred" class="form-label">{{ __('Excluded categories for payee') }}</label>
                        <select class="form-select" id="not_preferred" name="config[not_preferred][]" data-other-select="#preferred"></select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@stop
