@extends('template.layouts.page')

@section('title_postfix',  __('Received email'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Email received by YAFFA'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardOverview"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Overview') }}
                    </div>
                </div>
                <div class="collapse card-body show" aria-expanded="true" id="cardOverview">
                    <dl class="row mb-0">
                        <dt class="col-8">{{ __('Received at') }}</dt>
                        <dd class="col-4"
                            title="{{ $receivedMail->created_at }}">{{ $receivedMail->created_at->diffForHumans() }}</dd>
                        <dt class="col-8">{{ __('Processed') }}</dt>
                        <dd class="col-4">
                            @if ($receivedMail->processed)
                                <i
                                        class="fa fa-check-square text-success"
                                        dusk="icon-received-mail-processed-yes"
                                        title="{{ __('Yes') }}"
                                ></i>
                            @else
                                <i
                                        class="fa fa-square text-danger"
                                        dusk="icon-received-mail-processed-no"
                                        title="{{ __('No') }}"
                                ></i>
                            @endif
                        </dd>
                        <dt class="col-8">{{ __('Handled') }}</dt>
                        <dd class="col-4">
                            @if ($receivedMail->handled)
                                <i
                                        class="fa fa-check-square text-success"
                                        dusk="icon-received-mail-handled-yes"
                                        title="{{ __('Yes') }}"
                                ></i>
                            @else
                                <i
                                        class="fa fa-square text-danger"
                                        dusk="icon-received-mail-handled-no"
                                        title="{{ __('No') }}"
                                ></i>
                            @endif
                        </dd>
                        <dt class="col-8">{{ __('Linked transaction') }}</dt>
                        <dd class="col-4">
                            @if ($receivedMail->transaction_id)
                                <a
                                        dusk="link-received-mail-transaction"
                                        href="{{ route('transaction.open', [
                                            'transaction' => $receivedMail->transaction_id,
                                            'action' => 'show'
                                        ]) }}"
                                        title="{{ __('View transaction') }}"
                                >
                                    {{ $receivedMail->transaction_id }}
                                </a>
                            @else
                                <i
                                        class="fa fa-square text-danger"
                                        dusk="icon-received-mail-transaction-no"
                                        title="{{ __('No') }}"
                                ></i>
                        @endif
                    </dl>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardActions"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Actions') }}
                    </div>
                </div>
                <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardActions">
                    @if ($receivedMail->processed && !$receivedMail->handled)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Finalize transaction') }}
                        <button
                                class="btn btn-xs btn-primary finalizeIcon"
                                dusk="button-received-mail-finalize"
                                type="button"
                                title="{{ __('Finalize transaction') }}"
                        >
                            <i class="fa fa-fw fa-edit"></i>
                        </button>
                    </li>
                    @endif
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Delete email') }}
                        <button
                                class="btn btn-xs btn-danger deleteIcon"
                                dusk="button-received-mail-delete"
                                type="button"
                                title="{{ __('Delete') }}"
                        >
                            <i class="fa fa-fw fa-trash"></i>
                        </button>

                        @include('template.components.model-delete-form')
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-12 col-lg-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <button
                                    class="nav-link active"
                                    dusk="button-received-mail-tab-html"
                                    id="nav-email-tab-html"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#email-tab-html"
                                    type="button"
                                    role="tab"
                                    aria-controls="email-tab-html"
                                    aria-selected="true"
                            >
                                {{ __('HTML') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                    class="nav-link"
                                    dusk="button-received-mail-tab-text"
                                    id="nav-email-tab-text"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#email-tab-text"
                                    type="button"
                                    role="tab"
                                    aria-controls="email-tab-text"
                                    aria-selected="false"
                            >
                                {{ __('Text') }}
                            </button>
                        </li>
                        <li
                                class="nav-item"
                                @if(!$receivedMail->processed)
                                    title="{{ __('Email has not been processed yet') }}"
                                @endif
                        >
                            <button
                                    @class([
                                        "nav-link",
                                        "disabled" => !$receivedMail->processed
                                    ])
                                    id="nav-email-tab-data"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#email-tab-data"
                                    dusk="button-received-mail-tab-data"
                                    type="button"
                                    role="tab"
                                    aria-controls="email-tab-data"
                                    aria-selected="false"
                                    @if(!$receivedMail->processed)
                                        aria-disabled="true"
                                    @endif
                            >
                                {{ __('Extracted data') }}
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="nav-tabContent">
                        <div
                                class="tab-pane fade show active"
                                dusk="received-mail-tab-html"
                                id="email-tab-html"
                                role="tabpanel"
                                aria-labelledby="nav-email-tab-html"
                                tabindex="0"
                        >
                            {!! $receivedMail->html !!}
                        </div>
                        <div
                                class="tab-pane fade"
                                dusk="received-mail-tab-text"
                                id="email-tab-text"
                                role="tabpanel"
                                aria-labelledby="nav-email-tab-text"
                                tabindex="0"
                        >
                            <pre>{{ e($receivedMail->text) }}</pre>
                        </div>
                        @if($receivedMail->processed)
                            <div
                                    class="tab-pane fade"
                                    dusk="received-mail-tab-data"
                                    id="email-tab-data"
                                    role="tabpanel"
                                    aria-labelledby="nav-email-tab-data"
                                    tabindex="0"
                            >
                                <pre>
                                    Transaction type: {{ $receivedMail->transaction_data['transaction_type']['name'] }}
                                    Date: {{ $receivedMail->transaction_data['date'] }}
                                    Account: {{ $receivedMail->transaction_data['raw']['account'] }}
                                    Payee: {{ $receivedMail->transaction_data['raw']['payee'] }}
                                    Amount: {{ $receivedMail->transaction_data['raw']['amount'] }}
                                </pre>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
