@extends('template.page')

@section('title', 'Investment summary')

@section('content_header')
    <h1>Investment summary</h1>
@stop

@section('content')

    <div class="box">
        <div class="box-header">
            <h3 class="box-title">Summary of investments</h3>
            <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <table class="table table-bordered table-hover dataTable" role="grid" id="investmentSummary"></table>
        </div>
        <!-- /.box-body -->
    </div>
    <!-- /.box -->

    <div class="modal fade" id="modal-prices">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    <h4 class="modal-title">Investment prices</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success alert-dismissible hidden" id="alertSuccessTemplate">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h4><i class="icon fa fa-check"></i> Data saved</h4>
                    </div>
                    <div class="alert alert-danger alert-dismissible hidden" id="alertErrorTemplate">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        <h4><i class="icon fa fa-check"></i> Error</h4>
                    </div>
                    <table id="priceTable" class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>
                                    Date
                                </th>
                                <th>
                                    Price
                                </th>
                                <th>
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody id="priceTableBody">
                        </tbody>
                    </table>
                        <form id="formPrice" autocomplete="off">
                        <div class="row">
                            <div class="col-xs-6 form-group">
                                <label for="date" class="form-control-label">Date</label>
                                <input type="text" name="date" class="form-control" id="date">
                            </div>
                            <div class="col-xs-6 form-group">
                                <label for="price" class="form-control-label">Price</label>
                                <input type="text" name="price" class="form-control" id="price">
                            </div>
                        </div>
                        <input type="hidden" name="investment_id" id="investment_id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left closeModal" data-dismiss="modal">Close</button>
                    <button type="submit" form="formPrice" class="btn btn-primary">Save changes</button>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>

@stop
