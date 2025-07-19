@extends('template.layouts.page')

@section('title', __('Import QIF files'))

@section('content_header', __('Import QIF files'))

@section('content')
<div id="qif-import-app">
    <qif-import></qif-import>
</div>
@stop