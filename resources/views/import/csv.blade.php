@extends('template.layouts.page')

@section('title', __('Import CSV'))

@section('content_header', __('Import CSV'))

@section('content')
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="account">{{ __('Select account') }}</label>
                            <select name="account" id="account" class="form-control"></select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="csv_file">{{ __('File') }}</label>
                            <input type="file" class="form-control-file" id="csv_file" name="file" disabled>
                        </div>
                        <div class="col-md-2 form-group">
                            <label for="reset">&nbsp;</label>
                            <button type="button" class="btn btn-primary" id="reset">Reset form</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{{ __('Identified transactions') }}</div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group d-inline-block">
                                <label class="control-label">
                                    {{ __('Has similar transaction') }}
                                </label>
                                <div class="btn-group" role="group" aria-label="Toggle button group for similar transactions">
                                    <input type="radio" class="btn-check" name="has_similar" id="has_similar_yes" value="Yes">
                                    <label class="btn btn-outline-primary" for="has_similar_yes" title="{{ __('Yes') }}">
                                        <span class="fa fa-fw fa-check"></span>
                                    </label>

                                    <input type="radio" class="btn-check" name="has_similar" id="has_similar_any" value="" checked>
                                    <label class="btn btn-outline-primary" for="has_similar_any" title="{{ __('Any') }}">
                                        <span class="fa fa-fw fa-circle"></span>
                                    </label>

                                    <input type="radio" class="btn-check" name="has_similar" id="has_similar_no" value="No">
                                    <label class="btn btn-outline-primary" for="has_similar_no" title="{{ __('No') }}">
                                        <span class="fa fa-fw fa-close"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group d-inline-block">
                                <label class="control-label">
                                    {{ __('Already handled') }}
                                </label>
                                <div class="btn-group" role="group" aria-label="Toggle button group for already handled_transactions">
                                    <input type="radio" class="btn-check" name="handled" id="handled_yes" value="Yes">
                                    <label class="btn btn-outline-primary" for="handled_yes" title="{{ __('Yes') }}">
                                        <span class="fa fa-fw fa-check"></span>
                                    </label>

                                    <input type="radio" class="btn-check" name="handled" id="handled_any" value="" checked>
                                    <label class="btn btn-outline-primary" for="handled_any" title="{{ __('Any') }}">
                                        <span class="fa fa-fw fa-circle"></span>
                                    </label>

                                    <input type="radio" class="btn-check" name="handled" id="handled_no" value="No">
                                    <label class="btn btn-outline-primary" for="handled_no" title="{{ __('No') }}">
                                        <span class="fa fa-fw fa-close"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <table class="table table-bordered table-hover no-footer" id="dataTable">
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title collapse-control">
                        <span class="collapsed" data-coreui-toggle="collapse" href="#collapse-csv-unmatched-container" aria-expanded="true" aria-controls="collapse-csv-unmatched-container">
                            <i class="fa fa-angle-down"></i>
                            {{ __('Unmatched rows') }}
                        </span>
                    </div>
                </div>

                <div class="card-body collapse table-responsive no-padding" id="collapse-csv-unmatched-container">
                    <table id="unmatched_table" class="table table-striped table-hover">
                        <thead id="unmatched_table_head">
                        </thead>
                        <tbody id="unmatched_table_body">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="app">
        <transaction-show-modal
            :initial-controls="{ show: false, edit: false, delete: false, skip: false, enter: false, delete: false }"
        ></transaction-show-modal>

        <transaction-create-standard-modal></transaction-create-standard-modal>
    </div>

@stop
