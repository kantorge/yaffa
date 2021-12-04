@extends('template.layouts.page')

@section('title', 'Investments')

@section('content_header')
    <h1>Investments</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">List of investments</h3>
            <div class="pull-right box-tools">
                <a href="{{ route('investment.create') }}" class="btn btn-success" title="New investment"><i class="fa fa-plus"></i></a>
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

    <form id="form-delete" action="" method="POST" style="display: none;">
        <input type="hidden" name="_method" value="DELETE">
        @csrf
    </form>

@stop
