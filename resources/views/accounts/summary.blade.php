@extends('template.page')

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@stop

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
                                                <a href="{{ route('accounts.history', ['account' => $account->id]) }}" class="product-title">
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
                        <a href="{{ route('accounts.summary') }}">Show active accounts only</a>
                    </p>
                    <p>
                        <a href="{{ route('accounts.summary', ['withClosed' => 'withClosed']) }}">Show all accounts</a>
                    </p>
                </div>
                <!-- /.box-body -->
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Upcoming transactions</h3>

                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-box-widget="collapse">
                            <i class="fa fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.box-tools -->
                </div>
                <!-- /.box-header -->
                <div class="box-body">
                    <ul class="todo-list" id="upcomingTransactionsContainer">
                        <li class="hidden" id="upcomingTransactionsPrototype">
                            <small class="label" style="margin-left: 0px;"></small>
                            <span class="text"></span>
                            <div class="tools">
                                <a class="upcomingTransactionsActionsAdd" href="#" style="padding: 0 5px;"><i class="fa fa-pencil" title="Edit and insert instance"></i></a>
                                <a class="upcomingTransactionsActionsSkip" href="#"><i class="fa fa-forward" title="Skip current schedule"></i></a>
                            </div>
                        </li>
                    </ul>
                </div>
                <!-- /.box-body -->
                <div class="overlay">
                    <i class="fa fa-refresh fa-spin"></i>
                </div>
            </div>

		</div>
		<!-- /.col -->
      </div>
	  <!-- /.row -->

@stop