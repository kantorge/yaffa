@extends('template.layouts.page')

@section('title', __('YAFFA - Dashboard'))

@section('content_header', __('Welcome to your YAFFA dashboard!'))

@section('content')
    <div id="app">
        <dashboard></dashboard>
    </div>
@stop
