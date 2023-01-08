<ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="nav-icon fa fa-dashboard"></i>
            {{ __('Dashboard') }}
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="{{ route('investment.summary') }}">
            <i class="nav-icon fa-solid fa-chart-line"></i>
            {{ __('Investment summary') }}
        </a>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon fa-solid fa-database"></i>
            {{ __('Assets') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('currencies.index') }}"><i class="nav-icon fa-solid fa-money-bill"></i> {{ __('Currencies') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-group.index') }}"><i class="nav-icon fa-solid fa-layer-group"></i> {{ __('Account groups') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-entity.index', ['type' => 'account']) }}"><i class="nav-icon fa-solid fa-building-columns"></i> {{ __('Accounts') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-entity.index', ['type' => 'payee']) }}"><i class="nav-icon fa-solid fa-briefcase"></i> {{ __('Payees') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment-group.index') }}"><i class="nav-icon fa-solid fa-layer-group"></i> {{ __('Investment groups') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment.index') }}"><i class="nav-icon fa-solid fa-file-contract"></i> {{ __('Investments') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('categories.index') }}"><i class="nav-icon fa-solid fa-folder-open"></i> {{ __('Categories') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('tag.index') }}"><i class="nav-icon fa-solid fa-tags"></i> {{ __('Tags') }}</a></li>
        </ul>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon fa-solid fa-table-list"></i>
            {{ __('Reports') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.transactions') }}"><i class="nav-icon fa-solid fa-magnifying-glass"></i> {{ __('Find transactions') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.cashflow') }}"><i class="nav-icon fa-solid fa-chart-line"></i> {{ __('Cash flow') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.budgetchart') }}"><i class="nav-icon fa-solid fa-chart-line"></i> {{ __('Budget chart') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('report.schedules') }}"><i class="nav-icon fa-solid fa-list"></i> {{ __('List of schedules and budgets') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment.timeline') }}"><i class="nav-icon fa-solid fa-chart-line"></i> {{ __('Investment timeline') }}</a></li>
        </ul>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon fa fa-bolt"></i>
            {{ __('Quick actions') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('transactions.createStandard') }}"><i class="nav-icon fa-solid fa-cart-plus"></i> {{ __('New transaction') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('transactions.createInvestment') }}"><i class="nav-icon fa-solid fa-chart-line"></i> {{ __('New investment transaction') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('import.csv') }}"><i class="nav-icon fa-solid fa-upload"></i> {{ __('Import transactions') }}</a></li>
        </ul>
    </li>
</ul>
