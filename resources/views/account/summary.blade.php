@extends('template.layouts.page')

@section('title', 'Account  summary')

@section('content_header')
    <h1>Account summary</h1>
@stop

@section('content')

    <div class="row">
        <div class="col-md-8">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">Total value</h3>
			        <div class="pull-right">
                        @NiceNumber($total) {{ $baseCurrency['suffix'] }}
			        </div>
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <div class="box-group" id="accordion">
                        @forelse($summary as $key => $item)
                            <div class="panel box {{ ($item['sum'] < 0 ? 'box-danger' : 'box-primary') }}">
                                <div class="box-header with-border">
                                    <h4 class="box-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapse_{{ $key }}" class="collapsed" aria-expanded="false">
                                            {{ $item['group'] }}
                                        </a>
                                    </h4>
                                    <div class="pull-right {{ ($item['sum'] < 0 ? 'text-danger' : '') }}">
                                        @NiceNumber($item['sum']) {{ $baseCurrency['suffix'] }}
                                    </div>
                                </div>
                                <div id="collapse_{{ $key }}" class="panel-collapse collapse" aria-expanded="false" style="height: 0px;">
                                    <ul class="list-group list-group-flush">
                                        @foreach($item['accounts'] as $account)
                                            <li  class="list-group-item">
                                                <a href="{{ route('account.history', ['account' => $account->id]) }}" class="product-title">
                                                    {{ $account->name }}
                                                    <span class="pull-right <?=($account['sum'] < 0 ? "text-danger" : "")?>">
                                                        @if(isset($account['sum_foreign']))
                                                            @NiceNumber($account['sum_foreign'])
                                                            {{ $account['currency']['suffix'] }}
                                                            /
                                                        @endif

                                                        @NiceNumber($account['sum']) {{ $baseCurrency['suffix'] }}
                                                    </span>
                                                </a>
                                            </li >
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @empty
                            No data
                        @endforelse

                    </div>
                </div>
                <!-- /.box-body -->
            </div>
            <!-- /.box -->
        </div>
        <!-- /.col -->

		<div class="col-md-4">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">View filters</h3>

                    <div class="box-tools">
                        <button type="button" class="btn btn-box-tool" data-box-widget="collapse">
                                <i class="fa fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body" style="display: block;">
                    <p>
                        <a href="{{ route('account.summary') }}">Show active accounts only</a>
                    </p>
                    <p>
                        <a href="{{ route('account.summary', ['withClosed' => 'withClosed']) }}">Show all accounts</a>
                    </p>
                </div>
                <!-- /.box-body -->
            </div>

            <div id="PayeeCategoryRecommendationContainer">
                <payee-category-recommendation-box></payee-category-recommendation-box>
            </div>

		</div>
		<!-- /.col -->
    </div>
    <!-- /.row -->

@stop
