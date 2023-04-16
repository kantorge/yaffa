<div class="sidebar sidebar-light sidebar-end sidebar-overlaid hide" id="aside">
    <div class="sidebar-header bg-transparent p-0">
        <button class="sidebar-close" type="button" data-coreui-close="sidebar">
            <i class="fa fa-xmark"></i>
        </button>
    </div>

    <div class="tab-content">
        <div class="tab-pane p-3 active" id="settings" role="tabpanel">
            <h6>{{ __('Quick actions') }}</h6>
            <ul class="control-sidebar-menu">
                <li>
                    <a href="{{ route('transaction.create', ['type' => 'standard']) }}">
                        <i class="menu-icon fa fa-cart-plus bg-green"></i>

                        <div class="menu-info">
                            <h4 class="control-sidebar-subheading">{{ __('New transaction') }}</h4>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="{{ route('transaction.create', ['type' => 'investment']) }}">
                        <i class="menu-icon fa fa-line-chart bg-green"></i>

                        <div class="menu-info">
                            <h4 class="control-sidebar-subheading">{{ __('New investment transaction') }}</h4>
                        </div>
                    </a>
                </li>
                <li>
                    <a href="{{ route('import.csv') }}">
                        <i class="menu-icon fa fa-upload bg-green"></i>

                        <div class="menu-info">
                            <h4 class="control-sidebar-subheading">{{ __('Import transactions') }}</h4>
                        </div>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
