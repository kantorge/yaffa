<div class="sidebar sidebar-light sidebar-lg sidebar-end sidebar-overlaid hide" id="quick-action-bar" data-test="quick-action-bar">
    <div class="sidebar-header bg-transparent">
        <span class="sidebar-title">
            {{ __('Quick actions') }}
        </span>
        <button class="sidebar-close-custom" type="button" data-coreui-close="sidebar" data-test="quick-action-bar-close">
            <i class="fa fa-close"></i>
        </button>
    </div>
    <div class="tab-content">
        <div class="tab-pane active" id="quick-actions" role="tabpanel">
            <div class="list-group list-group-flush">
                <div class="list-group-item border-start-4 border-start-dark bg-light text-center fw-bold text-medium-emphasis text-uppercase small">
                    {{ __('New standard transaction') }}
                </div>
                <div class="list-group-item border-start-4 border-start-danger list-group-item-divider">
                    <a
                            class="nav-link"
                            href="{{ route('transaction.create', [
                                'type' => 'standard',
                                'transaction_type' => 'withdrawal'
                            ]) }}"
                    >
                        <i class="fa fa-2x fa-circle-minus text-danger me-2"></i>
                        {{ __('New withdrawal') }}
                    </a>
                </div>
                <div class="list-group-item border-start-4 border-start-success list-group-item-divider">
                    <a
                            class="nav-link"
                            href="{{ route('transaction.create', [
                                'type' => 'standard',
                                'transaction_type' => 'deposit'
                            ]) }}"
                    >
                        <i class="fa fa-2x fa-circle-plus text-success me-2"></i>
                        {{ __('New deposit') }}
                    </a>
                </div>
                <div class="list-group-item border-start-4 border-start-primary list-group-item-divider">
                    <a
                            class="nav-link"
                            href="{{ route('transaction.create', [
                                'type' => 'standard',
                                'transaction_type' => 'transfer'
                            ]) }}"
                    >
                        <i class="fa fa-2x fa-exchange-alt text-primary me-2"></i>
                        {{ __('New transfer') }}
                    </a>
                </div>

                <div class="list-group-item border-start-4 border-start-dark bg-light text-center fw-bold text-medium-emphasis text-uppercase small">
                    {{ __('New assets') }}
                </div>
                <div class="list-group-item border-start-4 border-start-secondary list-group-item-divider">
                    <a class="nav-link" href="{{ route('investment.create') }}">
                        <i class="fa fa-2x fa-chart-line text-secondary me-2"></i>
                        {{ __('New investment') }}
                    </a>
                </div>
                <div class="list-group-item border-start-4 border-start-primary list-group-item-divider">
                    <a class="nav-link" href="{{ route('account-entity.create', ['type' => 'payee']) }}">
                        <i class="fa fa-2x fa-briefcase text-primary me-2"></i>
                        {{ __('New payee') }}
                    </a>
                </div>
                <div class="list-group-item border-start-4 border-start-secondary list-group-item-divider">
                    <a class="nav-link" href="{{ route('tag.create') }}">
                        <i class="fa fa-2x fa-tags text-secondary me-2"></i>
                        {{ __('New tag') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
