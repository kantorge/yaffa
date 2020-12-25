<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Create the tabs -->
    <ul class="nav nav-tabs nav-justified control-sidebar-tabs">
      <li class="active"><a href="#control-sidebar-home-tab" data-toggle="tab" aria-expanded="true">Quick actions</a></li>
    </ul>
    <!-- Tab panes -->
    <div class="tab-content">
      <!-- Home tab content -->
      <div class="tab-pane active" id="control-sidebar-home-tab">
        <ul class="control-sidebar-menu">
          <li>
            <a href="{{ route('transactions.createStandard') }}">
              <i class="menu-icon fa fa-cart-plus bg-green"></i>

              <div class="menu-info">
                <h4 class="control-sidebar-subheading">New transaction</h4>
              </div>
            </a>
          </li>
          <li>
            <a href="{{ route('transactions.createInvestment') }}">
              <i class="menu-icon fa fa-line-chart bg-green"></i>

              <div class="menu-info">
                <h4 class="control-sidebar-subheading">New investment transaction</h4>
              </div>
            </a>
          </li>
        </ul>
        <!-- /.control-sidebar-menu -->
      </div>
      <!-- /.tab-pane -->
    </div>
  </aside>
  <!-- /.control-sidebar -->
  <!-- Add the sidebar's background. This div must be placed
  immediately after the control sidebar -->
  <div class="control-sidebar-bg"></div>