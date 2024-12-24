@extends('template.layouts.page')

@section('title_postfix', __('Transactions by criteria'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Transactions by criteria'))

@section('content')
    <div id="app">
        <find-transactions></find-transactions>
    </div>
@stop
