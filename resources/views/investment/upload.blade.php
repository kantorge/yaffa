@extends('template.layouts.page')

@section('title_postfix', __('Upload Investment Transactions'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('Upload Investment Transactions'))

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa fa-upload"></i>
                        {{ __('Bulk Import Transactions') }}
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>{{ __('Upload transactions from various sources') }}</h6>
                        <p class="mb-0">{{ __('Select a specialized importer below, or use the Trading212 uploader for general CSV/JSON/YAML imports.') }}</p>
                    </div>

                    <!-- Import Type Cards -->
                    <div class="row g-3 mb-4">
                        <!-- Trading 212 / Generic Uploader -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-primary">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-chart-line text-primary"></i>
                                        Trading212
                                    </h5>
                                    <p class="card-text text-muted small">
                                        Upload transactions from Trading212 CSV exports. Also supports custom JSON/YAML with flexible field mapping.
                                    </p>
                                    <div class="mt-3">
                                        <div id="app">
                                            <investment-upload-tool></investment-upload-tool>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- WiseAlpha -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-success">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-file-invoice text-success"></i>
                                        WiseAlpha
                                    </h5>
                                    <p class="card-text text-muted small">
                                        Corporate bond transactions from WiseAlpha platform. Upload multiple CSV statement files at once.
                                    </p>
                                    <a href="{{ route('investment.upload_statements') }}" class="btn btn-success btn-sm mt-auto">
                                        <i class="fa fa-upload"></i> Open WiseAlpha Uploader
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- MoneyHub -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-info">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-piggy-bank text-info"></i>
                                        MoneyHub
                                    </h5>
                                    <p class="card-text text-muted small">
                                        Investment data from MoneyHub aggregation service. Automatically maps accounts and transactions.
                                    </p>
                                    <a href="{{ route('import.moneyhub') }}" class="btn btn-info btn-sm mt-auto">
                                        <i class="fa fa-upload"></i> Open MoneyHub Uploader
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Fuel Ventures -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-warning">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-rocket text-warning"></i>
                                        Fuel Ventures
                                    </h5>
                                    <p class="card-text text-muted small">
                                        VC/startup investment tracking from Fuel Ventures CSV exports.
                                    </p>
                                    <a href="{{ route('investment.upload_csv') }}" class="btn btn-warning btn-sm mt-auto">
                                        <i class="fa fa-upload"></i> Open Fuel Ventures Uploader
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Payslip -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-secondary">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-receipt text-secondary"></i>
                                        Payslips
                                    </h5>
                                    <p class="card-text text-muted small">
                                        Employment income from processed payslip JSON files. Creates categorized deposit transactions.
                                    </p>
                                    <a href="{{ route('payslip.upload') }}" class="btn btn-secondary btn-sm mt-auto">
                                        <i class="fa fa-upload"></i> Open Payslip Uploader
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Import History -->
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-dark">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="fa fa-history text-dark"></i>
                                        Import History
                                    </h5>
                                    <p class="card-text text-muted small">
                                        View past imports, track processing status, and download error logs.
                                    </p>
                                    <a href="{{ route('imports.index') }}" class="btn btn-dark btn-sm mt-auto">
                                        <i class="fa fa-list"></i> View Import History
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="card mt-3">
                <div class="card-header">
                    <div
                        class="card-title collapse-control"
                        data-coreui-toggle="collapse"
                        data-coreui-target="#helpSection"
                    >
                        <i class="fa fa-angle-down"></i>
                        {{ __('Help & Documentation') }}
                    </div>
                </div>
                <div class="collapse" id="helpSection">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>{{ __('File Format Requirements (Trading212)') }}</h6>
                                <ul>
                                    <li>{{ __('Maximum file size: 10MB') }}</li>
                                    <li>{{ __('Supported formats: CSV, Excel (.xlsx), JSON, YAML') }}</li>
                                    <li>{{ __('Files should have a header row for CSV/Excel') }}</li>
                                    <li>{{ __('JSON/YAML files should have a "transactions" array at the root level') }}</li>
                                </ul>

                                <h6 class="mt-3">{{ __('Duplicate Detection') }}</h6>
                                <p class="small">{{ __('The system automatically checks for duplicate transactions based on:') }}</p>
                                <ul class="small">
                                    <li>{{ __('Transaction date') }}</li>
                                    <li>{{ __('Investment (symbol/ISIN)') }}</li>
                                    <li>{{ __('Account') }}</li>
                                    <li>{{ __('Quantity & Price') }}</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>{{ __('Field Mapping (Custom Sources)') }}</h6>
                                <p class="small">{{ __('For custom sources, map your file fields to YAFFA fields:') }}</p>
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>{{ __('YAFFA Field') }}</th>
                                            <th>{{ __('Description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <tr><td><code>date</code></td><td>Transaction date (required)</td></tr>
                                        <tr><td><code>_transaction_type_name</code></td><td>Buy, Sell, Dividend, etc.</td></tr>
                                        <tr><td><code>_symbol</code></td><td>Symbol, ticker, or ISIN</td></tr>
                                        <tr><td><code>_account_name</code></td><td>Account name</td></tr>
                                        <tr><td><code>config.quantity</code></td><td>Number of shares/units</td></tr>
                                        <tr><td><code>config.price</code></td><td>Price per unit</td></tr>
                                        <tr><td><code>config.commission</code></td><td>Transaction fees</td></tr>
                                        <tr><td><code>config.tax</code></td><td>Tax amount</td></tr>
                                        <tr><td><code>comment</code></td><td>Notes or description</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
