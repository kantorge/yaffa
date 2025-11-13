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
                        <h6>{{ __('Upload investment transactions from various sources') }}</h6>
                        <p class="mb-2">{{ __('Supported sources:') }}</p>
                        <ul class="mb-2">
                            <li><strong>WiseAlpha</strong> - {{ __('Corporate bond transactions from WiseAlpha platform') }}</li>
                            <li><strong>Trading 212</strong> - {{ __('Export from Trading 212 CSV format') }}</li>
                            <li><strong>MoneyHub</strong> - {{ __('Investment data from MoneyHub') }}</li>
                            <li><strong>Custom JSON/YAML</strong> - {{ __('Flexible format for custom integrations') }}</li>
                        </ul>
                        <p class="mb-0">{{ __('The system will automatically detect duplicates and map fields based on the selected source.') }}</p>
                    </div>

                    <!-- Vue Component Container -->
                    <div id="app">
                        <investment-upload-tool></investment-upload-tool>
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
                        <h6>{{ __('File Format Requirements') }}</h6>
                        <ul>
                            <li>{{ __('Maximum file size: 10MB') }}</li>
                            <li>{{ __('Supported formats: CSV, Excel (.xlsx), JSON, YAML') }}</li>
                            <li>{{ __('Files should have a header row for CSV/Excel') }}</li>
                            <li>{{ __('JSON/YAML files should have a "transactions" array at the root level') }}</li>
                        </ul>

                        <h6 class="mt-3">{{ __('Duplicate Detection') }}</h6>
                        <p>{{ __('The system automatically checks for duplicate transactions based on:') }}</p>
                        <ul>
                            <li>{{ __('Transaction date') }}</li>
                            <li>{{ __('Investment (symbol/ISIN)') }}</li>
                            <li>{{ __('Account') }}</li>
                            <li>{{ __('Quantity') }}</li>
                            <li>{{ __('Price (with ±0.01 tolerance)') }}</li>
                        </ul>

                        <h6 class="mt-3">{{ __('Field Mapping') }}</h6>
                        <p>{{ __('For custom sources, you can map your file fields to YAFFA fields:') }}</p>
                        <ul>
                            <li><strong>date</strong> - {{ __('Transaction date (required)') }}</li>
                            <li><strong>_transaction_type_name</strong> - {{ __('Type: Buy, Sell, Dividend, etc.') }}</li>
                            <li><strong>_symbol</strong> - {{ __('Investment symbol, ticker, or ISIN') }}</li>
                            <li><strong>_account_name</strong> - {{ __('Account name') }}</li>
                            <li><strong>config.quantity</strong> - {{ __('Number of shares/units') }}</li>
                            <li><strong>config.price</strong> - {{ __('Price per unit') }}</li>
                            <li><strong>config.commission</strong> - {{ __('Transaction fees') }}</li>
                            <li><strong>config.tax</strong> - {{ __('Tax amount') }}</li>
                            <li><strong>config.dividend</strong> - {{ __('Dividend amount') }}</li>
                            <li><strong>comment</strong> - {{ __('Notes or description') }}</li>
                        </ul>

                        <h6 class="mt-3">{{ __('Example Files') }}</h6>
                        <p>{{ __('Sample configuration files are available:') }}</p>
                        <ul>
                            <li><a href="{{ asset('storage/examples/wisealpha-example.yaml') }}" target="_blank">WiseAlpha YAML Example</a></li>
                            <li><a href="{{ asset('storage/examples/trading212-example.csv') }}" target="_blank">Trading212 CSV Example</a></li>
                            <li><a href="{{ asset('storage/examples/custom-example.json') }}" target="_blank">Custom JSON Example</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
