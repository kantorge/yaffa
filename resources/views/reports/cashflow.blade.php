@extends('template.page')

@section('title', 'Cash flow')

@section('content_header')
    <h1>Cash flow</h1>
@stop

@section('content')

<div class="row">
    <div class="col-sm-12">
        <div class="box">
            <div class="box-body">
                <div id="chartdiv" style="width:100%;height:500px;"></div>
            </div>
        </div>
    </div>
</div>

@stop
