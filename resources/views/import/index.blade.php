@extends('template.layouts.page')

@section('title', __('Import transactions'))

@section('content_header', __('Import transactions'))

@section('content')
    <div id="app">
        <import-page></import-page>
        <transaction-create-standard-modal></transaction-create-standard-modal>
        <transaction-quickview-modal></transaction-quickview-modal>
    </div>

@stop
