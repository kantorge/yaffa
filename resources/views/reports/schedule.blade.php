@extends('template.layouts.page')

@section('title', 'Scheduled transactions')

@section('content_header')
    <h1>Details of scheduled and budgeted transactions</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">
                Scheduled and budgeted transactions
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <div class="row">
                <div class="col-lg-12">
                    <div class="form-group d-inline-block">
                        <label class="control-label">
                            Schedule
                        </label>
                        <div>
                            <div class="btn-group" data-toggle="buttons">
                                <label class="btn btn-primary" title="Yes">
                                    <input type="radio" name="schedule" value="Yes" class="radio-inline">
                                    <span class="fa fa-fw fa-check"></span>
                                </label>
                                <label class="btn btn-primary active" title="Any">
                                    <input type="radio" name="schedule" value="" class="radio-inline" checked="checked">
                                    <span class="fa fa-fw fa-circle-o"></span>
                                </label>
                                <label class="btn btn-primary" title="No">
                                    <input type="radio" name="schedule" value="No" class="radio-inline">
                                    <span class="fa fa-fw fa-close"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group d-inline-block">
                        <label class="control-label">
                            Budget
                        </label>
                        <div>
                            <div class="btn-group" data-toggle="buttons">
                                <label class="btn btn-primary" title="Yes">
                                    <input type="radio" name="budget" value="Yes" class="radio-inline">
                                    <span class="fa fa-fw fa-check"></span>
                                </label>
                                <label class="btn btn-primary active" title="Any">
                                    <input type="radio" name="budget" value="" class="radio-inline" checked="checked">
                                    <span class="fa fa-fw fa-circle-o"></span>
                                </label>
                                <label class="btn btn-primary" title="No">
                                    <input type="radio" name="budget" value="No" class="radio-inline">
                                    <span class="fa fa-fw fa-close"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group d-inline-block">
                        <label class="control-label">
                            Active
                        </label>
                        <div>
                            <div class="btn-group" data-toggle="buttons">
                                <label class="btn btn-primary" title="Yes">
                                    <input type="radio" name="active" value="Yes" class="radio-inline">
                                    <span class="fa fa-fw fa-check"></span>
                                </label>
                                <label class="btn btn-primary active" title="Any">
                                    <input type="radio" name="budget" value="" class="radio-inline" checked="checked">
                                    <span class="fa fa-fw fa-circle-o"></span>
                                </label>
                                <label class="btn btn-primary" title="No">
                                    <input type="radio" name="active" value="No" class="radio-inline">
                                    <span class="fa fa-fw fa-close"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <table class="table table-bordered table-hover no-footer" id="table"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

    @include('template.components.model-delete-form')

    @include('template.components.transaction-skip-form')

@stop