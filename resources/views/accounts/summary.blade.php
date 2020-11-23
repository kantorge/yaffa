@extends('adminlte::page')

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
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Total value</h3>
			        <div class="card-tools">
				        {{ $total }}
			        </div>
                </div>
                <!-- /.box-header -->
                <div class="card-body">
                    <div id="accordion">
                        @forelse($summary as $key => $item)
                            <div class="card card-outline {{ ($item['sum'] < 0 ? 'card-danger' : 'card-primary') }}">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapse{{ $key }}" class="collapsed" aria-expanded="false">
                                            {{ $item['group'] }}
                                        </a>
                                    </h4>
                                    <div class="card-tools {{ ($item['sum'] < 0 ? 'text-danger' : '') }}">
                                        {{ $item['sum'] }}
                                    </div>
                                </div>
                                <div id="collapse{{ $key }}" class="panel-collapse in collapse">
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            @foreach($item['accounts'] as $account)
                                                <li  class="list-group-item">
                                                    <a href="{{ route('accounts.history', ['account' => $account->id]) }}" class="product-title">
                                                        {{ $account->name }}
                                                        <span class="float-right <?=($account['sum'] < 0 ? "text-danger" : "")?>">
                                                            <?=$account['sum']?>
                                                            @if(isset($account['sum_foreign']))
                                                                / {{ $account['sum_foreign'] }}
                                                            @endif
                                                        </span>
                                                    </a>
                                                </li >
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @empty
                            No data
                        @endforelse

                    </div>
                </div>
                <!-- /.card-body -->
            </div>
            <!-- /.card -->
        </div>
        <!-- /.col -->

		<div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">View filters</h3>

                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body" style="display: block;">
                    <p>
                        <a href="{{ route('accounts.summary') }}">Show active accounts only</a>
                    </p>
                    <p>
                        <a href="{{ route('accounts.summary', ['withClosed' => 'withClosed']) }}">Show all accounts</a>
                    </p>
                </div>
                <!-- /.card-body -->
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Upcoming transactions</h3>

                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                        </button>
                    </div>
                    <!-- /.card-tools -->
                </div>
                <!-- /.card-header -->
                <div class="card-body" style="display: block;">
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
                <!-- /.card-body -->
                <div class="overlay">
                    <i class="fa fa-refresh fa-spin"></i>
                </div>
            </div>

		</div>
		<!-- /.col -->
      </div>
	  <!-- /.row -->

@stop