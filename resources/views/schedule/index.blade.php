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
                <div class="col-lg-2">
                    <div class="form-group">
                        <label class="control-label">
                            Schedule
                        </label>
                        <div>
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-primary">
                                    <input type="radio" name="schedule" value="Yes" class="radio-inline">
                                    Yes
                                </label>
                                <label class="btn btn-primary active">
                                    <input type="radio" name="schedule" value="" class="radio-inline" checked="checked">
                                    Any
                                </label>
                                <label class="btn btn-primary">
                                    <input type="radio" name="schedule" value="No" class="radio-inline">
                                    No
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label class="control-label">
                            Budget
                        </label>
                        <div>
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-primary">
                                    <input type="radio" name="budget" value="Yes" class="radio-inline">
                                    Yes
                                </label>
                                <label class="btn btn-primary active">
                                    <input type="radio" name="budget" value="" class="radio-inline" checked="checked">
                                    Any
                                </label>
                                <label class="btn btn-primary">
                                    <input type="radio" name="budget" value="No" class="radio-inline">
                                    No
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="form-group">
                        <label class="control-label">
                            Active
                        </label>
                        <div>
                            <div class="btn-group btn-group-justified" data-toggle="buttons">
                                <label class="btn btn-primary">
                                    <input type="radio" name="active" value="Yes" class="radio-inline">
                                    Yes
                                </label>
                                <label class="btn btn-primary active">
                                    <input type="radio" name="active" value="" class="radio-inline" checked="checked">
                                    Any
                                </label>
                                <label class="btn btn-primary">
                                    <input type="radio" name="active" value="No" class="radio-inline">
                                    No
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

@stop
