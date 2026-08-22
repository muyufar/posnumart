<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="#!" class="brand-link">
      <img src="dist/img/logobumnupacnu.jpeg" alt="AdminLTE Logo" class="brand-image img-circle elevation-3">
      <span class="brand-text font-weight-light">NUMART</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->
      <div class="user-panel mt-3 pb-3 mb-3 d-flex">
        <div class="image">
          <img src="dist/img/avatar5.png" class="img-circle elevation-2" alt="User Image">
        </div>
        <div class="info">
          <a href="#" class="d-block"><?= $_SESSION['user_nama']; ?></a>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-desktop"></i> 
              <p>
                 Dashboard
              </p>
            </a>
          </li>

          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-shopping-bag"></i>
              <p>
                Penjualan
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>

          <?php if ( $levelLogin !== "kasir" ) { ?>
          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-shopping-bag"></i>
              <p>
                Pembelian Produk
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>
          <?php } ?>

          <?php if ( $levelLogin !== "kasir" ) { ?>
          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-exchange"></i>
              <p>
                Transfer Stock
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>
          <?php } ?>

          <?php if ( $levelLogin !== "kasir" ) { ?>
          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-university"></i>
              <p>
                Master
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>
          <?php } ?>

          <?php if ( $levelLogin !== "kasir" ) { ?>
          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-line-chart"></i>
              <p>
                Laba Bersih
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>
          <?php } ?>

          <?php if ( $levelLogin !== "kasir" ) { ?>
          <li class="nav-item has-treeview">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-file"></i>
              <p>
                Laporan
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
          </li>
          <?php } ?>

          <?php if ( $levelLogin === "super admin" ) { ?>
          <li class="nav-header">SETTINGS</li>
          <li class="nav-item">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-users"></i> 
              <p>
                 Users
              </p>
            </a>
          </li>
          <?php  
            $toko = query("SELECT * FROM toko WHERE toko_id = 1");
          ?>
          <?php foreach ( $toko as $row ) : ?>
            <?php 
              $toko_id = $row['toko_id']; 
              $toko_nama = $row['toko_nama']; 
            ?>
          <?php endforeach; ?>
          <?php  
            $namaToko = str_replace(" ", "-", $toko_nama)
          ?>
          <?php if ( $sessionCabang == 0 ) { ?>
          <li class="nav-item">
            <a href="#!" class="nav-link">
              <i class="nav-icon fa fa-id-card-o"></i> 
              <p>
                 Toko
              </p>
            </a>
          </li>
          <?php } ?>
        <?php } ?>
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>