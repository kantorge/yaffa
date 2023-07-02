@extends('template.layouts.page')

@section('title_postfix', __('Investment details'))

@section('content_container_classes', 'container-fluid')

@section('content_header')
    {{ __('Investment details') }} - {{ $investment->name }}
@stop

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-5">
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Investment details') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-4">{{ __('Name') }}</dt>
                                <dd class="col-8">{{ $investment->name }}</dd>
                                <dt class="col-4">{{ __('Symbol') }}</dt>
                                <dd class="col-8">{{ $investment->symbol }}</dd>
                                <dt class="col-4">{{ __('ISIN number') }}</dt>
                                <dd class="col-8 @if(!$investment->isin)text-muted @endif">
                                    {{ ($investment->isin ? $investment->isin : __('Not set')) }}
                                </dd>
                                <dt class="col-4">{{ __('Active') }}</dt>
                                <dd class="col-8">
                                    @if($investment->active)
                                        <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                    @else
                                        <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                                    @endif
                                </dd>
                                <dt class="col-4">{{ __('Group') }}</dt>
                                <dd class="col-8">{{ $investment->investmentGroup->name }}</dd>
                                <dt class="col-4">{{ __('Currency') }}</dt>
                                <dd class="col-8">{{ $investment->currency->name }}</dd>
                                @if($investment->comment)
                                    <dt class="col-4">{{ __('Comment') }}</dt>
                                    <dd class="col-8">{{ $investment->comment }}</dd>
                                @endif
                                @if($investment->investment_price_provider)
                                    <dt class="col-4">{{ __('Price provider') }}</dt>
                                    <dd class="col-8">{{ $investment->investment_price_provider_name }}</dd>
                                    <dt class="col-4">{{ __('Automatic update') }}</dt>
                                    <dd class="col-8">
                                        @if($investment->auto_update)
                                            <i class="fa fa-check-square text-success" title="{{ __('Yes') }}"></i>
                                        @else
                                            <i class="fa fa-square text-danger" title="{{ __('No') }}"></i>
                                        @endif
                                    </dd>
                                @endif
                            </dl>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Current assets') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-8">{{ __('Owned quantity') }}</dt>
                                <dd class="col-4">{{ $investment->getCurrentQuantity() }}</dd>
                                <dt class="col-8">{{ __('Latest price') }}</dt>
                                <dd class="col-4">{{ $investment->getLatestPrice() }}</dd>
                                <dt class="col-8">{{ __('Latest owned value') }}</dt>
                                <dd class="col-4">{{ $investment->getCurrentQuantity() * $investment->getLatestPrice() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between">
                            <div class="card-title">
                                {{ __('Results') }}
                            </div>
                            <div>
                                <button class="btn btn-sm btn-primary" id="clear_dates">{{ __('Clear selection') }}</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <label for="date_from" class="col-6 col-sm-2 col-form-label">{{ __('Date from') }}</label>
                                <div class="col-6 col-sm-4">
                                    <input type="text" class="form-control" id="date_from">
                                </div>
                                <label for="date_to" class="col-6 col-sm-2 col-form-label">{{ __('Date to') }}</label>
                                <div class="col-6 col-sm-4">
                                    <input type="text" class="form-control" id="date_to">
                                </div>
                            </div>
                            <div class="row mb-0">
                                <div class="col-sm-6">
                                    <dl class="row mb-0">
                                        <dt class="col-6">{{ __('Buying cost') }}</dt>
                                        <dd class="col-6" id="summaryBuying"></dd>
                                        <dt class="col-6">{{ __('Added quantity') }}</dt>
                                        <dd class="col-6" id="summaryAdded"></dd>
                                        <dt class="col-6">{{ __('Removed quantity') }}</dt>
                                        <dd class="col-6" id="summaryRemoved"></dd>
                                        <dt class="col-6">{{ __('Selling revenue') }}</dt>
                                        <dd class="col-6" id="summarySelling"></dd>
                                        <dt class="col-6">{{ __('Dividend') }}</dt>
                                        <dd class="col-6" id="summaryDividend"></dd>
                                        <dt class="col-6">{{ __('Commissions') }}</dt>
                                        <dd class="col-6" id="summaryCommission"></dd>
                                        <dt class="col-6">{{ __('Taxes') }}</dt>
                                        <dd class="col-6" id="summaryTaxes"></dd>
                                        <dt class="col-6">{{ __('Quantity') }}</dt>
                                        <dd class="col-6" id="summaryQuantity"></dd>
                                        <dt class="col-6">{{ __('Value') }}</dt>
                                        <dd class="col-6" id="summaryValue"></dd>
                                    </dl>
                                </div>
                                <div class="col-sm-6">
                                    <dl class="row mb-0">
                                        <dt class="col-6">{{ __('Result') }}</dt>
                                        <dd class="col-6" id="summaryResult"></dd>
                                        <dt class="col-6">{{ __('ROI') }}</dt>
                                        <dd class="col-6" id="summaryROI"></dd>
                                        <dt class="col-6">{{ __('Annualized ROI') }}</dt>
                                        <dd class="col-6" id="summaryAROI">{{ __('TBD') }}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Transaction history') }}
                    </div>
                    <div>
                        <a
                                href="{{ route('transaction.create', [
                                        'type' => 'investment',
                                        'callback' => 'back'
                                    ]) }}"
                                class="btn btn-success btn-sm"
                                title="{{ __('New investment transaction') }}"
                        >
                            <i class="fa fa-plus"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table class="table table-bordered table-hover" role="grid" id="table"></table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Price history') }}
                    </div>
                    <div>
                        <span class="label label-danger hidden" id="priceChartNoData">{{ __('No data available') }}</span>
                        @if($investment->investment_price_provider)
                            <a href="{{ route('investment-price.retreive', ['investment' =>  $investment->id ]) }}" class="btn btn-sm btn-success" title="{{ __('Load new price data') }}">
                                <span class="fa fa-cloud-download"></span>
                            </a>
                        @endif
                        <a href="{{ route('investment-price.list', ['investment' =>  $investment->id ]) }}" class="btn btn-sm btn-primary">
                            <span class="fa fa-search" title="{{ __('List prices') }}"></span>
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chartPrice" style="width: 100%; height: 300px"></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Quantity history') }}
                    </div>
                    <div>
                        <span class="label label-danger hidden" id="quantityChartNoData">{{ __('No data available') }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chartQuantity" style="width: 100%; height: 300px"></div>
                </div>
            </div>
        </div>
    </div>

    @include('template.components.model-delete-form')
@stop
