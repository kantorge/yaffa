@extends('template.layouts.page')

@section('title', 'Import CSV')

@section('content_header')
    <h1>Import CSV</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-default">
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label for="account">Select account</label>
                            <select name="account" id="account" class="form-control"></select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="csv_file">File</label>
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

    <div class="row">
        <div class="col-sm-12">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Identified transactions</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group d-inline-block">
                                <label class="control-label">
                                    Has similar transaction
                                </label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        <label class="btn btn-primary" title="Yes">
                                            <input type="radio" name="has_similar" value="Yes" class="radio-inline">
                                            <span class="fa fa-fw fa-check"></span>
                                        </label>
                                        <label class="btn btn-primary active" title="Any">
                                            <input type="radio" name="has_similar" value="" class="radio-inline"
                                                checked="checked">
                                            <span class="fa fa-fw fa-circle-o"></span>
                                        </label>
                                        <label class="btn btn-primary" title="No">
                                            <input type="radio" name="has_similar" value="No" class="radio-inline">
                                            <span class="fa fa-fw fa-close"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group d-inline-block">
                                <label class="control-label">
                                    Already handled
                                </label>
                                <div>
                                    <div class="btn-group" data-toggle="buttons">
                                        <label class="btn btn-primary" title="Yes">
                                            <input type="radio" name="handled" value="Yes" class="radio-inline">
                                            <span class="fa fa-fw fa-check"></span>
                                        </label>
                                        <label class="btn btn-primary" title="Any">
                                            <input type="radio" name="handled" value="" class="radio-inline">
                                            <span class="fa fa-fw fa-circle-o"></span>
                                        </label>
                                        <label class="btn btn-primary active" title="No">
                                            <input type="radio" name="handled" value="No" class="radio-inline"
                                                checked="checked">
                                            <span class="fa fa-fw fa-close"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <table class="table table-bordered table-hover dataTable no-footer" id="dataTable" style="width: 100%;">
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-default collapsed-box">
                <div class="box-header with-border">
                    <h3 class="box-title">Unmatched rows</h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i>
                        </button>
                    </div>

                </div>

                <div class="box-body table-responsive no-padding" style="display: none;">
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

        <transaction-create-modal></transaction-create-modal>
    </div>

@stop
