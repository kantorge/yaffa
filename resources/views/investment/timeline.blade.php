@extends('template.layouts.page')

@section('title', 'Investment timeline')

@section('content_header')
    <h1>Investment timeline</h1>
@stop

@section('content')

<div class="box">
    <div class="box-body">
        <div class="row" id="filters">
            <div class="col-lg-12">
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
                                <input type="radio" name="active" value="All" class="radio-inline" checked="checked">
                                <span class="fa fa-fw fa-circle-o"></span>
                            </label>
                            <label class="btn btn-primary" title="No">
                                <input type="radio" name="active" value="No" class="radio-inline">
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group d-inline-block">
                    <label class="control-label">
                        Open
                    </label>
                    <div>
                        <div class="btn-group" data-toggle="buttons">
                            <label class="btn btn-primary" title="Yes">
                                <input type="radio" name="open" value="Yes" class="radio-inline">
                                <span class="fa fa-fw fa-check"></span>
                            </label>
                            <label class="btn btn-primary active" title="Any">
                                <input type="radio" name="open" value="All" class="radio-inline" checked="checked">
                                <span class="fa fa-fw fa-circle-o"></span>
                            </label>
                            <label class="btn btn-primary" title="No">
                                <input type="radio" name="open" value="No" class="radio-inline">
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="chart" style="width: 100%; height: 700px"></div>
    </div>
</div>

@stop
