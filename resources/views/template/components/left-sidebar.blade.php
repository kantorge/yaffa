<!-- Left side column. contains the logo and sidebar -->
<aside class="main-sidebar">

    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">

    <!-- search form (Optional) -->
    <form action="#" method="get" class="sidebar-form">
        <div class="input-group">
        <input type="text" name="q" class="form-control" placeholder="Search...">
        <span class="input-group-btn">
            <button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i>
            </button>
            </span>
        </div>
    </form>
    <!-- /.search form -->

    @if(isset($accountsForNavbar) && count($accountsForNavbar) > 0)
    <form action="#" method="get" class="sidebar-form">
        <div class="input-group">
            <select name="jump_to_account" id="jump_to_account" class="form-control" >
                <option value="">Select account to open</option>
                @foreach($accountsForNavbar as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </form>
    @endif

    <!-- Sidebar Menu -->
    <ul class="sidebar-menu" data-widget="tree">
        <li class="treeview">
            <a href="#"><i class="fa fa-dashboard"></i> <span>Dashboards</span>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            </a>
            <ul class="treeview-menu">
                <li><a href="{{ route('account.summary') }}"><i class="fa fa-dashboard"></i> <span>Main page</span></a></li>
                <li><a href="{{ route('investment.summary') }}"><i class="fa fa-line-chart"></i> <span>Investments</span></a></li>
            </ul>
        </li>
        <li class="treeview">
            <a href="#"><i class="fa fa-database"></i> <span>Assets</span>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            </a>
            <ul class="treeview-menu">
                <li><a href="{{ route('account-group.index') }}"><i class="fa fa-reorder"></i> <span>Account groups</span></a></li>
                <li><a href="{{ route('account-entity.index', ['type' => 'account']) }}"><i class="fa fa-university"></i> <span>Accounts</span></a></li>
                <li><a href="{{ route('account-entity.index', ['type' => 'payee']) }}"><i class="fa fa-suitcase"></i> <span>Payees</span></a></li>
                <li><a href="{{ route('investment-group.index') }}"><i class="fa fa-reorder"></i> <span>Investment groups</span></a></li>
                <li><a href="{{ route('investment.index') }}"><i class="fa fa-line-chart"></i> <span>Investments</span></a></li>
                <li><a href="{{ route('tag.index') }}"><i class="fa fa-tags"></i> <span>Tags</span></a></li>
                <li><a href="{{ route('categories.index') }}"><i class="fa fa-folder-open"></i> <span>Categories</span></a></li>
                <li><a href="{{ route('currencies.index') }}"><i class="fa fa-money"></i> <span>Currencies</span></a></li>
            </ul>
        </li>
        <li class="treeview">
            <a href="#"><i class="fa fa-money"></i> <span>Budget</span>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            </a>
            <ul class="treeview-menu">
                <li><a href="{{ route('schedule.index') }}"><i class="fa fa-list"></i> <span>List of schedules and budgets</span></a></li>
            </ul>
        </li>
        <li class="treeview">
            <a href="#"><i class="fa fa-table"></i> <span>Reports</span>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            </a>
            <ul class="treeview-menu">
                <li><a href="{{ route('reports.transactions') }}"><i class="fa fa-search"></i> <span>Find transactions</span></a></li>
                <li><a href="{{ route('reports.cashflow') }}"><i class="fa fa-line-chart"></i> <span>Cash flow</span></a></li>
                <li><a href="{{ route('reports.budgetchart') }}"><i class="fa fa-line-chart"></i> <span>Budget chart</span></a></li>
            </ul>
        </li>
        <li class="treeview">
            <a href="#"><i class="fa fa-bolt"></i> <span>Quick actions</span>
                <span class="pull-right-container">
                    <i class="fa fa-angle-left pull-right"></i>
                </span>
            </a>
            <ul class="treeview-menu">
                <li><a href="{{ route('transactions.createStandard') }}"><i class="fa fa-cart-plus"></i> <span>Add transaction</span></a></li>
                <li><a href="{{ route('transactions.createInvestment') }}"><i class="fa fa-line-chart"></i> <span>Add investment transaction</span></a></li>
            </ul>
        </li>
    </ul>
    <!-- /.sidebar-menu -->
    </section>
<!-- /.sidebar -->
</aside>
