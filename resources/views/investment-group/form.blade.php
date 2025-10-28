@extends('template.layouts.page')

@section('title_postfix',  __('Investment groups'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Investment groups'))

@section('content')
@if(isset($investmentGroup))
<form
    accept-charset="UTF-8"
    action="{{ route('investment-group.update', $investmentGroup) }}"
    autocomplete="off"
    method="POST"
>
@method('PATCH')
@else
<form
    accept-charset="UTF-8"
    action="{{ route('investment-group.store') }}"
    autocomplete="off"
    method="POST"
>
@endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">
                @if(isset($investmentGroup['id']))
                    {{ __('Modify investment group') }}
                @else
                    {{ __('Add investment group') }}
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
                        value="{{old('name', $investmentGroup['name'] ?? '' )}}"
                    >
                </div>
            </div>
        </div>
        <div class="card-footer">
            @csrf

            <input class="btn btn-primary" type="submit" value="{{ __('Save') }}">
            <a href="{{ route('investment-group.index') }}" class="btn btn-secondary cancel confirm-needed">{{ __('Cancel') }}</a>
        </div>
    </div>
</form>
@stop
