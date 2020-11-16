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
			        <span class="pull-right">
				        TODO
			        </span>
                </div>
                <!-- /.box-header -->
                <div class="card-body">
                    <div id="accordion">
                        @forelse($summary as $key => $item)
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" class="collapsed" aria-expanded="false">
                                            {{ $key }}
                                        </a>
                                    </h4>
                                </div>
                                <div id="collapseOne" class="panel-collapse in collapse">
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            @foreach($item as $account)
                                                <li  class="list-group-item">
                                                    <a href="/accounts/account_details?account_id={{ $account->id }}" class="product-title">
                                                        {{ $account->name }}
                                                        <span class="pull-right <?=($account['sum'] < 0 ? "text-danger" : "")?>">
                                                            <?=$account['sum']?>
                    <?php //if ($base_currency['id'] != $account['currencies_id']) {?>
                                                            / <?php //NiceNumber($account['balance'] * $currency_rates[$account['currencies_id']], 0, 0, $base_currency['suffix'])?>
                    <?php //}?>
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
			<div class="box">
				<div class="box-header with-border">
					<h3 class="box-title">View filters</h3>
					<div class="box-tools pull-right">
						<!-- Collapse Button -->
						<button type="button" class="btn btn-box-tool" data-widget="collapse">
							<i class="fa fa-minus"></i>
						</button>
					</div>
					<!-- /.box-tools -->
				</div>
				<!-- /.box-header -->
				<div class="box-body">
					<p>
						<a href="<?='/accounts/index/inactive/0'?>">Show active accounts only</a>
					</p>
					<p>
						<a href="<?='accounts/index/inactive/1'?>">Show all accounts</a>
					</p>
				</div>
				<!-- /.box-body -->
			</div>
			<!-- /.box -->

			<div class="box" id="upcomingTransactionsBox">
				<div class="box-header with-border">
					<h3 class="box-title">Upcoming transactions</h3>
					<div class="box-tools pull-right">
						<!-- Collapse Button -->
						<button type="button" class="btn btn-box-tool" data-widget="collapse">
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
			<!-- /.box -->
		</div>
		<!-- /.col -->
      </div>
	  <!-- /.row -->

@stop