@extends('template.layouts.page')

@section('title_postfix',  __('Add GoCardless Integration'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Add GoCardless Integration'))

@section('content')
    <div id="app">
        <create-requisition></create-requisition>
    </div>
@stop
