@extends('template.layouts.page')

@section('title', 'Budget chart')

@section('content_header')
    <h1>Budget chart</h1>
@stop

@section('content')

<div class="row">
    <div class="col-lg-8">
        <div class="box">
            <div class="box-header">
                <div class="pull-right box-tools">
                    <a
                        class="btn {{($byYears ? 'btn-primary' : 'btn-info') }}"
                        href="{{ route('reports.budgetchart', ['byYears' => ($byYears ? '' : 'byYears')]) }}"
                        title="{{($byYears ? 'Switch to monthly view' : 'Switch to yearly view') }}">
                        <i class="fa fa-calendar"></i>
                    </a>
                </div>
                <!-- /.box-tools -->
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div id="chartdiv" style="width:100%;height:500px;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="box">
            <div class="box-header">
                <h3>Select categories to display</h3>
                <p>Selecting parent category loads all related subcategories too.</p>
            </div>
            <div class="box-body">
                <select
                    class="form-control"
                    id="category_id"
                    multiple
                    size="15"
                >
                    @forelse($categories as $id => $name)
                        <option value="{{ $id }}">
                            {{ $name }}
                        </option>
                    @empty

                    @endforelse
                </select>
            </div>
            <div class="box-footer">
                <button name="reload" type="button" id="reload" class="btn btn-primary pull-right">Change</button>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h3 class="box-title">
                    Scheduled and budgeted transactions for selected categories
                </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <table class="table table-bordered table-hover no-footer" id="table"></table>
            </div>
            <!-- /.box-body -->
        </div>
        <!-- /.box -->
    </div>
</div>

@stop
