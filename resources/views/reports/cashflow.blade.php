@extends('template.layouts.page')

@section('title_postfix', __('Cash flow'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Cash flow'))

@section('content')
<div class="row">
    <div class="col-12 col-lg-3" id="cashflowLeftControlPanel">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Account') }}
                </div>
            </div>
            <div class="card-body">
                <select class="form-select" id="cashflowAccount"></select>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    {{ __('Actions') }}
                </div>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    {{ __('Reload') }}
                    <button type="button" class="btn btn-sm btn-primary" id="btnReload">
                        <i class="fa fa-fw fa-refresh"></i>
                    </button>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    {{ __('Show on same axis') }}
                    <div>
                        <input type="checkbox" class="btn-check" id="singleAxis" checked autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="singleAxis" title="{{ __('Show on same axis') }}"><i class="fa fa-fw fa-lock"></i></label>
                    </div>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    {{ __('With forecast') }}
                    <div>
                        <input type="checkbox" class="btn-check" id="withForecast" {{ $withForecast ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-sm btn-outline-secondary" for="withForecast" title="{{ __('With forecast') }}"><i class="fa fa-fw fa-calendar"></i></label>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-12 col-lg-9" id="cashflowMainContent">
        <div class="left-control-panel-toggle-shell mb-3">
            <div class="card left-control-panel-toggle-card">
                <div class="card-header d-flex align-items-center gap-2 left-control-panel-toggle-header">
                    <button
                        type="button"
                        id="toggleCashflowLeftControlPanelButton"
                        class="btn btn-sm btn-outline-secondary"
                        title="{{ __('Collapse left control panel') }}"
                        aria-label="{{ __('Collapse left control panel') }}"
                        aria-expanded="true"
                        aria-controls="cashflowLeftControlPanel cashflowMainContent"
                    >
                        <i class="fas fa-angles-left" data-left-control-panel-toggle-icon></i>
                    </button>
                    <div class="card-title mb-0">
                        {{ __('Cash flow') }}
                    </div>
                </div>
                <div class="alert alert-info m-3 mb-0 alert-dismissible fade show hidden" role="alert" id="cashflowRateNoteAlert" data-dismissible-key="dismissCashflowRateNote">
                    <small>
                        <strong>{{ __('reports.cashflow.rateNoteLabel') }}</strong> {{ __('reports.cashflow.rateNoteBody') }}
                    </small>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="card-body">
                    <div id="placeholder" class="d-flex align-items-end gap-2 placeholder-glow" style="height:500px;">
                        <span class="placeholder w-100 h-25"></span>
                        <span class="placeholder w-100 h-50"></span>
                        <span class="placeholder w-100 h-75"></span>
                        <span class="placeholder w-100 h-100"></span>
                        <span class="placeholder w-100 h-75"></span>
                        <span class="placeholder w-100 h-50"></span>
                        <span class="placeholder w-100 h-25"></span>
                    </div>
                    <div id="chartdiv" class="hidden" style="width:100%; height:500px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
