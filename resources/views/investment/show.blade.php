@extends('template.layouts.page')

@section('title', __('Investment details'))

@section('content_header')
    {{ __('Investment details') }} - {{ $investment->name }}
@stop

@section('content')
    <div class="row">
        <div class="col-md-7">
            <div class="row">
                <div class="col-md-5">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                {{ __('Investment details') }}
                            </h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <dl class="dl-horizontal">
                                <dt>{{ __('Name') }}</dt>
                                <dd>{{ $investment->name }}</dd>
                                <dt>{{ __('Symbol') }}</dt>
                                <dd>{{ $investment->symbol }}</dd>
                                <dt>{{ __('ISIN number') }}</dt>
                                <dd>{!! ($investment->isin ? $investment->isin : '<span class="text-muted">Not set</span>') !!}</dd>
                                <dt>{{ __('Active') }}</dt>
                                <dd>
                                    @if($investment->active)
                                        <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                    @else
                                        <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                                    @endif
                                </dd>
                                <dt>{{ __('Group') }}</dt>
                                <dd>{{ $investment->investment_group->name }}</dd>
                                <dt>{{ __('Currency') }}</dt>
                                <dd>{{ $investment->currency->name }}</dd>
                                @if($investment->comment)
                                    <dt>{{ __('Comment') }}</dt>
                                    <dd>{{ $investment->comment }}</dd>
                                @endif
                                @if($investment->investment_price_provider)
                                    <dt>{{ __('Price provider') }}</dt>
                                    <dd>{{ $investment->investment_price_provider_name }}</dd>
                                    <dt>{{ __('Automatic update') }}</dt>
                                    <dd>
                                        @if($investment->auto_update)
                                            <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                        @else
                                            <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
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
                            <h3 class="box-title">{{ __('Current assets') }}</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <dl class="dl-horizontal">
                                <dt>{{ __('Currently owned quantity') }}</dt>
                                <dd>{{ $investment->getCurrentQuantity() }}</dd>
                                <dt>{{ __('Latest price') }}</dt>
                                <dd>{{ $investment->getLatestPrice() }}</dd>
                                <dt>{{ __('Latest owned value') }}</dt>
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
                            <h3 class="box-title">{{ __('Results') }}</h3>
                            <div class="box-tools pull-right">
                                <button class="btn btn-xs btn-primary" id="clear_dates">{{ __('Clear selection') }}</button>
                            </div>
                            <!-- /.box-tools -->
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body form-horizontal">
                            <div class="form-group">
                                <label for="date_from" class="col-sm-2 control-label">{{ __('Date from') }}</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" id="date_from">
                                </div>
                                <label for="date_to" class="col-sm-2 control-label">{{ __('Date to') }}</label>
                                <div class="col-sm-4">
                                    <input type="text" class="form-control" id="date_to">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-6">
                                    <dl class="dl-horizontal">
                                        <dt>{{ __('Buying cost') }}</dt>
                                        <dd id="summaryBuying"></dd>
                                        <dt>{{ __('Added quantity') }}</dt>
                                        <dd id="summaryAdded"></dd>
                                        <dt>{{ __('Removed quantity') }}</dt>
                                        <dd id="summaryRemoved"></dd>
                                        <dt>{{ __('Selling revenue') }}</dt>
                                        <dd id="summarySelling"></dd>
                                        <dt>{{ __('Dividend') }}</dt>
                                        <dd id="summaryDividend"></dd>
                                        <dt>{{ __('Commissions') }}</dt>
                                        <dd id="summaryCommission"></dd>
                                        <dt>{{ __('Taxes') }}</dt>
                                        <dd id="summaryTaxes"></dd>
                                        <dt>{{ __('Quantity') }}</dt>
                                        <dd id="summaryQuantity"></dd>
                                        <dt>{{ __('Value') }}</dt>
                                        <dd id="summaryValue"></dd>
                                    </dl>
                                </div>
                                <div class="col-sm-6">
                                    <dl class="dl-horizontal">
                                        <dt>{{ __('Result') }}</dt>
                                        <dd id="summaryResult"></dd>
                                        <dt>{{ __('ROI') }}</dt>
                                        <dd id="summaryROI"></dd>
                                        <dt>{{ __('Annualized ROI') }}</dt>
                                        <dd id="summaryAROI">{{ __('TBD') }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">{{ __('Transaction history') }}</h3>
                    <div class="pull-right box-tools">
                        <a href="{{route('transactions.createInvestment')}}" class="btn btn-success" title="{{ __('New investment transaction') }}"><i class="fa fa-plus"></i></a>
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
                    <h3 class="box-title">{{ __('Price history') }}</h3>
                    <div class="box-tools pull-right">
                        <span class="label label-danger hidden" id="priceChartNoData">{{ __('No data available') }}</span>
                        @if($investment->investment_price_provider)
                            <a href="{{ route('investment-price.retreive', ['investment' =>  $investment->id ]) }}" class="btn btn-xs btn-success" title="{{ __('Load new price data') }}">
                                <span class="fa fa-cloud-download"></span>
                            </a>
                        @endif
                        <a href="{{ route('investment-price.list', ['investment' =>  $investment->id ]) }}" class="btn btn-xs btn-primary">
                            <span class="fa fa-search" title="{{ __('List prices') }}"></span>
                        </a>
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
                    <h3 class="box-title">{{ __('Quantity history') }}</h3>
                    <div class="box-tools pull-right">
                        <span class="label label-danger hidden" id="quantityChartNoData">{{ __('No data available') }}</span>
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

    @include('template.components.model-delete-form')
@stop
