@extends('template.layouts.page')

@section('title_postfix',  __('Link Accounts'))

@section('content_container_classes', 'container-lg')

@section('content_header', __('Link GoCardless Accounts with Yaffa accounts'))

@section('content')
    <div id="app">
        <link-accounts></link-accounts>
    </div>
@stop
