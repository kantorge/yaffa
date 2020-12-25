@extends('template.page')

@section('title', 'Payees')

@section('content_header')
<h1>Payees</h1>
@stop

@section('content')

    @if(isset($payee))
        <form
            accept-charset="UTF-8"
            action="{{ route('payees.update', $payee->id) }}"
            autocomplete="off"
            method="POST"
        >
        <input name="_method" type="hidden" value="PATCH">
    @else
        <form
            accept-charset="UTF-8"
            action="{{ route('payees.store') }}"
            autocomplete="off"
            method="POST"
        >
    @endif

    <div class="box box-primary">
        <div class="box-header">
            <h3 class="box-title">
                @if(isset($payee->id))
                    Modify payee
                @else
                    Add new payee
                @endif
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body form-horizontal">
            <div class="form-group">
                <label for="name" class="control-label col-sm-3">
                    Name
                </label>
                <div class="col-sm-9">
                    <input
                        class="form-control"
                        id="name"
                        name="name"
                        type="text"
                        value="{{old('name', $payee->name ?? '' )}}"
                    >
                </div>
            </div>

           <div class="form-group">
                <label for="active" class="control-label col-sm-3">
                    Active
                </label>
                <div class="col-sm-9">
                    <input
                        id="active"
                        class="checkbox-inline"
                        name="active"
                        type="checkbox"
                        value="1"
                        @if (old())
                            @if (old('active') == '1')
                                checked="checked"
                            @endif
                        @elseif(isset($payee))
                            @if ($payee['active'] == '1')
                                checked="checked"
                            @endif
                        @else
                            checked="checked"
                        @endif
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="category_id" class="control-label col-sm-3">
                    Default category
                </label>
                <div class="col-sm-9">
                    <select
                        class="form-control"
                        id="category_id"
                        name="config[category_id]"
                    >
                        <option value=''> < No default category></option>
                        @forelse($categories as $id => $name)
                            <option
                                value="{{ $id }}"
                                @if (old())
                                    @if (old('config.category_id') == $id)
                                        selected="selected"
                                    @endif
                                @elseif(isset($payee))
                                    @if ($payee->config->category_id == $id)
                                        selected="selected"
                                    @endif
                                @endif
                            >
                                {{ $name }}
                            </option>
                        @empty

                        @endforelse

                    </select>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer">
            @csrf
            <input
                name="id"
                type="hidden"
                value="{{old('id', $payee['id'] ?? '' )}}"
            >
            <input
                name="config_type"
                type="hidden"
                value="{{old('config_type', 'payee' )}}"
            >

            <input class="btn btn-primary" type="submit" value="Save">
            <a href="{{ route('payees.index') }}" class="btn btn-secondary cancel confirm-needed">Cancel</a>
        </div>
        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

    </form>

@stop