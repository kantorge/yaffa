<ul class="sidebar-nav" data-coreui="navigation" data-simplebar>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="nav-icon cil-speedometer"></i>
            {{ __('Dashboard') }}
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link" href="{{ route('investment.summary') }}">
            <i class="nav-icon cil-chart-line"></i>
            {{ __('Investment summary') }}
        </a>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon cil-library"></i>
            {{ __('Assets') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('currencies.index') }}"><i class="nav-icon cil-money"></i> {{ __('Currencies') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-group.index') }}"><i class="nav-icon cil-menu"></i> {{ __('Account groups') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-entity.index', ['type' => 'account']) }}"><i class="nav-icon cil-bank"></i> {{ __('Accounts') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('account-entity.index', ['type' => 'payee']) }}"><i class="nav-icon cil-briefcase"></i> {{ __('Payees') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment-group.index') }}"><i class="nav-icon cil-menu"></i> {{ __('Investment groups') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment.index') }}"><i class="nav-icon cil-badge"></i> {{ __('Investments') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('categories.index') }}"><i class="nav-icon cil-folder-open"></i> {{ __('Categories') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('tag.index') }}"><i class="nav-icon cil-tags"></i> {{ __('Tags') }}</a></li>
        </ul>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon cil-spreadsheet"></i>
            {{ __('Reports') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.transactions') }}"><i class="nav-icon cil-search"></i> {{ __('Find transactions') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.cashflow') }}"><i class="nav-icon cil-chart"></i> {{ __('Cash flow') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('reports.budgetchart') }}"><i class="nav-icon cil-chart"></i> {{ __('Budget chart') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('report.schedules') }}"><i class="nav-icon cil-list"></i> {{ __('List of schedules and budgets') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('investment.timeline') }}"><i class="nav-icon cil-chart"></i> {{ __('Investment timeline') }}</a></li>
        </ul>
    </li>

    <li class="nav-group" aria-expanded="false">
        <a class="nav-link nav-group-toggle" href="#">
            <i class="nav-icon fa fa-bolt"></i>
            {{ __('Quick actions') }}
        </a>
        <ul class="nav-group-items">
            <li class="nav-item"><a class="nav-link" href="{{ route('transactions.createStandard') }}"><i class="nav-icon cil-cart"></i> {{ __('New transaction') }}</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('transactions.createInvestment') }}"><i class="nav-icon cil-chart-line"></i> {{ __('New investment transaction') }}</a></li>
            {{-- <li class="nav-item"><a class="nav-link" href="{{ route('import.csv') }}"><i class="nav-icon cil-cloud-upload"></i> {{ __('Import transactions') }}</a></li> --}}
        </ul>
    </li>
</ul>
