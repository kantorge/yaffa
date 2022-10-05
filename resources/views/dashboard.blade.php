@extends('template.layouts.page')

@section('title', 'YAFFA - Dashboard')

@section('content_header')
    <h1>
        Welcome to your YAFFA dashboard!
    </h1>
@stop

@section('content')
    <div id="app">
        <dashboard></dashboard>
    </div>
@stop
