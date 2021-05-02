@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

@section('title', 'Investment details')

@section('content_header')
    <h1>Investment details - {{ $investment->name }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-7">
            <div class="row">
                <div class="col-md-5">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">Investment details</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <dl class="dl-horizontal">
                                <dt>Name</dt>
                                <dd>{{ $investment->name }}</dd>
                                <dt>Symbol</dt>
                                <dd>{{ $investment->symbol }}</dd>
                                <dt>Active</dt>
                                <dd>
                                    @if($investment->active)
                                        <i class="fa fa-check-square text-success" title="Yes"></i>
                                    @else
                                        <i class="fa fa-square text-danger" title="No"></i>
                                    @endif
                                </dd>
                                <dt>Group</dt>
                                <dd>{{ $investment->investment_group->name }}</dd>
                                <dt>Currency</dt>
                                <dd>{{ $investment->currency->name }}</dd>
                                @if($investment->comment)
                                    <dt>Comment</dt>
                                    <dd>{{ $investment->comment }}</dd>
                                @endif
                                @if($investment->investment_price_provider_id)
                                    <dt>Price provider</dt>
                                    <dd>{{ $investment->investment_price_provider->name }}</dd>
                                    <dt>Auto update</dt>
                                    <dd>
                                        @if($investment->auto_update)
                                            <i class="fa fa-check-square text-success" title="Yes"></i>
                                        @else
                                            <i class="fa fa-square text-danger" title="No"></i>
                                        @endif
                                    </dd>
                                @endif
                            </dl>
                        </div>
                        <!-- /.box-body -->
                    </div>
                    <!-- /.box -->
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">Current assets</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <dl class="dl-horizontal">
                                <dt>Currently owned quantity</dt>
                                <dd>{{ $investment->getCurrentQuantity() }}</dd>
                                <dt>Latest price</dt>
                                <dd>{{ $investment->getLatestPrice() }}</dd>
                                <dt>Latest owned value</dt>
                                <dd>{{ $investment->getCurrentQuantity() * $investment->getLatestPrice() }}</dd>
                            </dl>
                        </div>
                        <!-- /.box-body -->
                    </div>
                    <!-- /.box -->
                </div>
                <div class="col-md-7">
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title">Results</h3>
                            <div class="box-tools pull-right">
                                <button class="btn btn-xs btn-primary" id="clear_dates">Clear selection</button>
                            </div>
                            <!-- /.box-tools -->
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body form-horizontal">
                            <div class="form-group">
                                <label for="date_from" class="col-sm-2 control-label">Date from</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control hasDatepicker" id="date_from">
                                </div>
                                <label for="date_to" class="col-sm-2 control-label">Date to</label>
                                <div class="col-sm-4">
                                    <div class="input-group margin">
                                        <input type="text" class="form-control hasDatepicker" id="date_to">
                                        <span class="input-group-btn">
                                            <button class="btn btn-flat btn-info" id="date_to_today" title="Set to today"><i class="fa fa-calendar"></i></button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <dl class="dl-horizontal">
                                        <dt>Buying cost</dt>
                                        <dd id="summaryBuying"></dd>
                                        <dt>Added quantity</dt>
                                        <dd id="summaryAdded"></dd>
                                        <dt>Removed quantity</dt>
                                        <dd id="summaryRemoved"></dd>
                                        <dt>Selling revenue</dt>
                                        <dd id="summarySelling"></dd>
                                        <dt>Dividend</dt>
                                        <dd id="summaryDividend"></dd>
                                        <dt>Commissions</dt>
                                        <dd id="summaryCommission"></dd>
                                        <dt>Taxes</dt>
                                        <dd id="summaryTaxes"></dd>
                                        <dt>Quantity</dt>
                                        <dd id="summaryQuantity"></dd>
                                        <dt>Value</dt>
                                        <dd id="summaryValue"></dd>
                                    </dl>
                                </div>
                                <div class="col-sm-6">
                                    <dl class="dl-horizontal">
                                        <dt>Result</dt>
                                        <dd id="summaryResult"></dd>
                                        <dt>ROI</dt>
                                        <dd id="summaryROI"></dd>
                                        <dt>Annualized ROI</dt>
                                        <dd id="summaryAROI">TBD</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Transaction history</h3>
                    <div class="pull-right box-tools">
                        <a href="{{route('transactions.createInvestment')}}" class="btn btn-success" title="New currency rate"><i class="fa fa-plus"></i></a>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <table class="table table-bordered table-hover dataTable" role="grid" id="table"></table>
                </div>
                <!-- /.box-body -->
            </div>
            <!-- /.box -->
        </div>
        <div class="col-md-5">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Price history</h3>
                    <div class="box-tools pull-right hidden" id="priceChartNoData">
                        <button class="btn btn-xs btn-danger">No data available</button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <div id="chartPrice" style="width: 100%; height: 300px"></div>
                </div>
            </div>
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Quantity history</h3>
                    <div class="box-tools pull-right hidden" id="quantityChartNoData">
                        <button class="btn btn-xs btn-danger">No data available</button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <div id="chartQuantity" style="width: 100%; height: 300px"></div>
                </div>
            </div>
        </div>
    </div>
@stop
