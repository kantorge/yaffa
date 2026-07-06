@extends('template.layouts.page')

@section('title_postfix', __('Advanced reconcile'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Advanced reconcile'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div class="card-title mb-0">{{ __('Account reconciliation dashboard') }}</div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <select class="form-select form-select-sm w-auto" id="advancedReconcileType">
                        <option value="total">{{ __('Total') }}</option>
                        <option value="cash">{{ __('Cash') }}</option>
                        <option value="investment">{{ __('Investment') }}</option>
                    </select>
                    <select class="form-select form-select-sm w-auto" id="advancedReconcileDisplay">
                        <option value="status">{{ __('See status') }}</option>
                        <option value="balance">{{ __('See closing balance') }}</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="advancedReconcileReload">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle" id="advancedReconcileDashboard">
                        <thead></thead>
                        <tbody>
                            <tr>
                                <td><i class="fa fa-fw fa-spinner fa-spin"></i></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
