@extends('template.layouts.page')

@section('title', 'Merge payees')

@section('content_header')
<h1>Merge payees</h1>
@stop

@section('content')

    <form
        accept-charset="UTF-8"
        action="{{ route('payees.merge.submit') }}"
        autocomplete="off"
        id="merge-payees-form"
        method="POST"
    >
        <div class="box box-primary">
            <div class="box-header">
                <h3 class="box-title">
                    Select payees to merge
                </h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payee_source">
                                Payee to be merged
                            </label>
                            <select
                                class="form-control"
                                id="payee_source"
                                name="payee_source"
                            ></select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payee_target">
                                Where to merge payee
                            </label>
                            <select
                                class="form-control"
                                id="payee_target"
                                name="payee_target"
                            ></select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group form-horizontal">
                            <label>
                                After merging
                            </label>
                            <div class="radio">
                                <label class="radio-inline">
                                    <input type="radio" name="action" value="delete" checked="checked">
                                    Delete payee
                                </label>
                                <label class="radio-inline">
                                    <input type="radio" name="action" value="close" checked="">
                                    Set payee to inactive
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group has-error">
                            <span class="help-block">This action cannot be undone. Proceed with caution.</span>
                            @csrf
                            <button
                                class="btn btn-sm btn-default"
                                type="button"
                                id="cancel"
                            >
                                Cancel
                            </button>
                            <input class="btn btn-primary" type="submit" value="Merge payees">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

@stop
