<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">

    <section class="sidebar">

        <!-- search form -->
        <form action="{{ route('search') }}" method="get" class="sidebar-form">
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="{{ __('Search...') }}" autocomplete="off">
                <span class="input-group-btn">
                    <button type="submit" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i></button>
                </span>
            </div>
        </form>
        <!-- /.search form -->

        @if(isset($accountsForNavbar) && count($accountsForNavbar) > 0)
        <form action="#" method="get" class="sidebar-form">
            <div class="input-group">
                <select name="jump_to_account" id="jump_to_account" class="form-control" >
                    <option value="">{{ __('Select account to open') }}</option>
                    @foreach($accountsForNavbar as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        @endif

        <!-- Sidebar Menu -->
        <ul class="sidebar-menu" data-widget="tree">
            <li><a href="{{ route('home') }}"><i class="fa fa-dashboard"></i> <span>{{ __('Dashboard') }}</span></a></li>
            <li><a href="{{ route('investment.summary') }}"><i class="fa fa-line-chart"></i> <span>{{ __('Investment summary') }}</span></a></li>
            <li class="treeview">
                <a href="#"><i class="fa fa-database"></i> <span>{{ __('Assets') }}</span>
                    <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('account-group.index') }}"><i class="fa fa-reorder"></i> <span>{{ __('Account groups') }}</span></a></li>
                    <li><a href="{{ route('account-entity.index', ['type' => 'account']) }}"><i class="fa fa-university"></i> <span>{{ __('Accounts') }}</span></a></li>
                    <li><a href="{{ route('account-entity.index', ['type' => 'payee']) }}"><i class="fa fa-suitcase"></i> <span>{{ __('Payees') }}</span></a></li>
                    <li><a href="{{ route('investment-group.index') }}"><i class="fa fa-reorder"></i> <span>{{ __('Investment groups') }}</span></a></li>
                    <li><a href="{{ route('investment.index') }}"><i class="fa fa-line-chart"></i> <span>{{ __('Investments') }}</span></a></li>
                    <li><a href="{{ route('tag.index') }}"><i class="fa fa-tags"></i> <span>{{ __('Tags') }}</span></a></li>
                    <li><a href="{{ route('categories.index') }}"><i class="fa fa-folder-open"></i> <span>{{ __('Categories') }}</span></a></li>
                    <li><a href="{{ route('currencies.index') }}"><i class="fa fa-money"></i> <span>{{ __('Currencies') }}</span></a></li>
                </ul>
            </li>
            <li class="treeview">
                <a href="#"><i class="fa fa-table"></i> <span>{{ __('Reports') }}</span>
                    <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('reports.transactions') }}"><i class="fa fa-search"></i> <span>{{ __('Find transactions') }}</span></a></li>
                    <li><a href="{{ route('reports.cashflow') }}"><i class="fa fa-line-chart"></i> <span>{{ __('Cash flow') }}</span></a></li>
                    <li><a href="{{ route('reports.budgetchart') }}"><i class="fa fa-line-chart"></i> <span>{{ __('Budget chart') }}</span></a></li>
                    <li><a href="{{ route('report.schedules') }}"><i class="fa fa-list"></i> <span>{{ __('List of schedules and budgets') }}</span></a></li>
                    <li><a href="{{ route('investment.timeline') }}"><i class="fa fa-line-chart"></i> <span>{{ __('Investment timeline') }}</span></a></li>
                </ul>
            </li>
            <li class="treeview">
                <a href="#"><i class="fa fa-bolt"></i> <span>{{ __('Quick actions') }}</span>
                    <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                </a>
                <ul class="treeview-menu">
                    <li><a href="{{ route('transactions.createStandard') }}"><i class="fa fa-cart-plus"></i> <span>{{ __('New transaction') }}</span></a></li>
                    <li><a href="{{ route('transactions.createInvestment') }}"><i class="fa fa-line-chart"></i> <span>{{ __('New investment transaction') }}</span></a></li>
                    <li><a href="{{ route('import.csv') }}"><i class="fa fa-upload"></i> <span>{{ __('Import transactions') }}</span></a></li>
                </ul>
            </li>
        </ul>

    </section>
<!-- /.sidebar -->
</aside>
