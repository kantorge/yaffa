@extends('template.layouts.page')

@section('title', 'Account  summary')

@section('content_header')
    <h1>
        Account summary
        -
        @if ($withClosed)
            All accounts
        @else
            Active accounts
        @endif
    </h1>
@stop

@section('content')

    <div class="row">
        <div class="col-md-5">
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
                <div class="box-footer">
                    <div class="pull-right box-tools">
                        <a href="{{ route('account.summary') }}" title="Show active accounts only" class="btn btn-sm btn-info"><span class="fa fa-folder-open-o"></span></a>
                        <a href="{{ route('account.summary', ['withClosed' => 'withClosed']) }}" title="Show all accounts" class="btn btn-sm btn-info"><span class="fa fa-folder-o"></a>
                    </div>
                </div>
            </div>
            <!-- /.box -->
        </div>
        <!-- /.col -->

		<div class="col-md-7">
            <div id="PayeeCategoryRecommendationContainer">
                <payee-category-recommendation-box></payee-category-recommendation-box>
            </div>
            <div id="ScheduleCalendar">
                <schedule-calendar></schedule-calendar>
            </div>
		</div>
		<!-- /.col -->
    </div>
    <!-- /.row -->

@stop
