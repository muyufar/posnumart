<aside class="main-sidebar sidebar-dark-primary elevation-4 modern-sidebar">
  <!-- Brand Logo -->
  <a href="bo" class="brand-link modern-brand">
    <img src="dist/img/logobumnupacnu.jpeg" alt="AdminLTE Logo" class="brand-image img-circle elevation-3">
    <span class="brand-text font-weight-light">NUMART</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar modern-sidebar-content">
    <!-- Sidebar user panel (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex modern-user-panel">
      <div class="image">
        <img src="dist/img/avatar5.png" class="img-circle elevation-2 modern-avatar" alt="User Image">
      </div>
      <div class="info">
        <a href="#" class="d-block modern-user-name"><?= $_SESSION['user_nama']; ?></a>
      </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <?php if ($levelLogin !== "kurir") { ?>
          <li class="nav-item">
            <a href="bo" class="nav-link">
              <i class="nav-icon fa fa-desktop"></i>
              <p>
                Dashboard
              </p>
            </a>
          </li>

          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-shopping-cart"></i>
              <p>
                Penjualan
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="beli-langsung?customer=<?= base64_encode(0); ?>" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kasir</p>
                </a>
              </li>
              <!--<li class="nav-item has-treeview">-->
              <!--  <a href="#" class="nav-link">-->
              <!--    <i class="far fa-circle nav-icon"></i>-->
              <!--    <p>-->
              <!--      Kasir-->
              <!--      <i class="right fas fa-angle-left"></i>-->
              <!--    </p>-->
              <!--  </a>-->
              <!--  <ul class="nav nav-treeview">-->
              <!--    <li class="nav-item">-->
              <!--      <a href="beli-langsung?customer=<?= base64_encode(0); ?>" class="nav-link">-->
              <!--        <i class="far fa-dot-circle nav-icon"></i>-->
              <!--        <p>Customer Umum</p>-->
              <!--      </a>-->
              <!--    </li>-->
              <!--    <li class="nav-item">-->
              <!--      <a href="beli-langsung?customer=<?= base64_encode(1); ?>" class="nav-link">-->
              <!--        <i class="far fa-dot-circle nav-icon"></i>-->
              <!--        <p>Member Retail</p>-->
              <!--      </a>-->
              <!--    </li>-->
              <!--    <li class="nav-item">-->
              <!--      <a href="beli-langsung?customer=<?= base64_encode(2); ?>" class="nav-link">-->
              <!--        <i class="far fa-dot-circle nav-icon"></i>-->
              <!--        <p>Grosir</p>-->
              <!--      </a>-->
              <!--    </li>-->
              <!--  </ul>-->
              <!--</li>-->
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Customer
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="customer" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Data Customer</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="customer-management" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Dashboard Management</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="customer-analisa" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Analisa Belanja</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="customer-keuntungan" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Margin Pelanggan</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="customer-area-tracking" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Area Tracking</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="customer-wa-blast" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>WA Blast</p>
                    </a>
                  </li>
                  <?php if ($levelLogin === "super admin" || $levelLogin === "admin") : ?>
                  <li class="nav-item">
                    <a href="customer-target-settings" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Pengaturan Target</p>
                    </a>
                  </li>
                  <?php endif; ?>
                </ul>
              </li>
              <li class="nav-item">
                <a href="penjualan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Invoice Penjualan</p>
                </a>
              </li>
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Piutang
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="piutang" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Belum Lunas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="piutang-menunggak" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Piutang Menunggak</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="piutang-lunas" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Lunas</p>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        <?php } ?>

        <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-shopping-bag"></i>
              <p>
                Pembelian
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="transaksi-pembelian" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Transaksi</p>
                </a>
              </li>
               <li class="nav-item">
                <a href="forecasting-pengadaan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Forecasting Pengadaan (AI)</p>
                </a>
              </li>
              <?php if ((int) $sessionCabang < 1 && $levelLogin !== 'kasir' && $levelLogin !== 'kurir') : ?>
              <li class="nav-item">
                <a href="pengadaan-gudang" class="nav-link" id="nav-pengadaan-gudang">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Pusat Pengadaan Gudang
                    <span class="badge badge-danger right" id="badge-pengadaan-gudang" style="display:none;">0</span>
                  </p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="supplier" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Supplier</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="pembelian" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Invoice Pembelian</p>
                </a>
              </li>
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Hutang
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="hutang" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Belum Lunas</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="hutang-menunggak" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Hutang Menunggak</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="hutang-lunas" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Lunas</p>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        <?php } ?>





        <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-exchange"></i>
              <p>
                Transfer Stock
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="transfer-stock-cabang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Transaksi</p>
                </a>
              </li>
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Data Transfer Stock
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="transfer-stock-cabang-masuk" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Masuk</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="transfer-stock-cabang-keluar" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Keluar</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="monitor-duplikat-transfer-masuk" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Monitor duplikat masuk</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="penyesuaian-stock" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Penyesuaian stock</p>
                    </a>
                  </li>
                </ul>
              </li>
            </ul>
          </li>
        <?php } ?>

        <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-university"></i>
              <p>
                Master
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="kategori" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kategori</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="satuan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Satuan</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="barang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Barang</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="aktifkan_barang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Aktifkan Barang</p>
                </a>
              </li>
              <?php if ($levelLogin == "admin" || $levelLogin == "super admin") : ?>
              <li class="nav-item">
                <a href="barang-sinkronisasi" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Sinkronisasi Barang</p>
                </a>
              </li>
              <?php endif; ?>
            </ul>
          </li>
        <?php } ?>

        <?php if ($levelLogin !== "kurir") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-calculator"></i>
              <p>
                Stock Opname
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="stock-opname-per-produk" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Per Produk</p>
                </a>
              </li>

              <?php if ($levelLogin !== "kasir") { ?>
                <li class="nav-item">
                  <a href="stock-opname-keseluruhan" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Keseluruhan</p>
                  </a>
                </li>
              <?php } ?>

              <li class="nav-item">
                <a href="stock-opname-data-produk" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Print Data Produk</p>
                </a>
              </li>

              <li class="nav-item">
                <a href="stock-opname-laporan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan &amp; Buku Stok</p>
                </a>
              </li>
            </ul>
          </li>
        <?php } ?>

        <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-usd"></i>
              <p>
                Portal Keuangan
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="laba-kategori" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kategori Akun COA</p>
                </a>
              </li>
              <?php if ($levelLogin == "super admin") : ?>
              <li class="nav-item">
                <a href="laba-kategori-sinkronisasi" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Sinkronisasi Akun</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="laba-bersih-data" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Data Operasional</p>
                </a>
              </li>
              <?php if ($levelLogin === "super admin" || $levelLogin === "admin") : ?>
              <li class="nav-item">
                <a href="laba-bersih-edit-akun" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Edit Akun Transaksi</p>
                </a>
              </li>
              <?php endif; ?>
              <?php if ($levelLogin === "super admin") : ?>
              <li class="nav-item">
                <a href="transaksi-mapping" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Sinkronisasi Akun (AI)</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="laba-bersih-laporan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan Cash Basis</p>
                </a>
              </li>
                <li class="nav-item">
                <a href="laba-bersih-laporan-accural" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan Accrual Basis</p>
                </a>
              </li>
            </ul>
          </li>
        <?php } ?>

        <?php if ($levelLogin === "kurir") { ?>
          <li class="nav-item">
            <a href="kurir-data" class="nav-link">
              <i class="nav-icon fa fa-table"></i>
              <p>
                Data Kurir
              </p>
            </a>
          </li>
        <?php } ?>

        <li class="nav-item has-treeview">
          <a href="#" class="nav-link">
            <i class="nav-icon fa fa-book"></i>
            <p>
              Laporan
              <i class="fas fa-angle-left right"></i>
              <span class="badge badge-info right"></span>
            </p>
          </a>

          <ul class="nav nav-treeview">
            <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
              <li class="nav-item">
                <a href="laporan-kasir" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kasir</p>
                </a>
              </li>
            <?php } ?>

            <?php if ($levelLogin === "kasir") { ?>
              <li class="nav-item">
                <a href="laporan-kasir-pribadi" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kasir Pribadi</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="laporan-pergantian-shift" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p><?= $sessionCabang >= 1 ? 'Pergantian Shift' : 'Penjualan Harian' ?></p>
                </a>
              </li>
            <?php } ?>

            <?php if ($levelLogin === "kurir") { ?>
              <li class="nav-item">
                <a href="laporan-kurir-pribadi" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kurir Pribadi</p>
                </a>
              </li>
            <?php } ?>

            <?php if ($levelLogin !== "kurir") { ?>
              <li class="nav-item">
                <a href="laporan-kurir" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Kurir</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="laporan-customer" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Customer</p>
                </a>
              </li>
            <?php } ?>

            <?php if ($levelLogin !== "kasir" && $levelLogin !== "kurir") { ?>
              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Penjualan
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="periode" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Per Periode</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="laporan-produk" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Per Produk</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="produk-analisa" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Analisa Produk (Promo)</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="edit-transaksi" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Retur</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="laporan-pergantian-shift" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p><?= $sessionCabang >= 1 ? 'Pergantian Shift' : 'Penjualan Harian' ?></p>
                    </a>
                  </li>
                </ul>
              </li>

              <li class="nav-item">
                <a href="laporan-supplier" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Supplier</p>
                </a>
              </li>

              <li class="nav-item has-treeview">
                <a href="#" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Pembelian
                    <i class="right fas fa-angle-left"></i>
                  </p>
                </a>
                <ul class="nav nav-treeview">
                  <li class="nav-item">
                    <a href="periode-pembelian" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Per Periode</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="laporan-produk-pembelian" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Per Produk</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="edit-transaksi-pembelian" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Retur</p>
                    </a>
                  </li>
                </ul>
              </li>

              <li class="nav-item">
                <a href="terlaris" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Terlaris</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="stok" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Stok</p>
                </a>
              </li>
          </ul>
        <?php } ?>
        </li>

        <?php if ($levelLogin === "super admin") { ?>
          <li class="nav-item has-treeview">
            <a href="#" class="nav-link">
              <i class="nav-icon fa fa-database"></i>
              <p>
                Backup & Restore
                <i class="fas fa-angle-left right"></i>
                <span class="badge badge-info right"></span>
              </p>
            </a>
            <ul class="nav nav-treeview">
              <li class="nav-item">
                <a href="backup" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Backup</p>
                </a>
              </li>
              <?php if ($sessionCabang < 1) { ?>
                <li class="nav-item">
                  <a href="restore" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Restore</p>
                  </a>
                </li>
              <?php } ?>
            </ul>
          </li>
        <?php } ?>

        <!--<?php if ($levelLogin === "super admin" || $sessionCabang == 1) { ?>-->
        <!--  <li class="nav-header">INVESTOR</li>-->
        <!--  <li class="nav-item">-->
        <!--    <a href="investor-dashboard" class="nav-link">-->
        <!--      <i class="nav-icon fa fa-line-chart"></i>-->
        <!--      <p>-->
        <!--        Dashboard Investor-->
        <!--      </p>-->
        <!--    </a>-->
        <!--  </li>-->
        <!--<?php } ?>-->

        <?php if ($levelLogin === "super admin") { ?>
          <li class="nav-header">SETTINGS</li>
          <li class="nav-item">
            <a href="user-type" class="nav-link">
              <i class="nav-icon fa fa-users"></i>
              <p>
                Users
              </p>
            </a>
          </li>
          <!-- <li class="nav-item">
            <a href="shopee-sync" class="nav-link">
              <i class="nav-icon fa fa-refresh"></i>
              <p>
                Sync Shopee
              </p>
            </a>
          </li>
          <li class="nav-item">
            <a href="shopee-mapping" class="nav-link">
              <i class="nav-icon fa fa-link"></i>
              <p>
                Mapping Produk
              </p>
            </a>
          </li> -->
          <?php if ($sessionCabang == 0) { ?>
            <li class="nav-item">
              <a href="toko" class="nav-link">
                <i class="nav-icon fa fa-id-card-o"></i>
                <p>
                  Toko
                </p>
              </a>
            </li>
          <?php } ?>
        <?php } ?>
        <!-- <?php if ($levelLogin === "super admin") { ?>
          <li class="nav-header">INTEGRASI</li>
          <li class="nav-item">
            <a href="shopee-settings" class="nav-link">
              <i class="nav-icon fa fa-plug"></i>
              <p>
                Integrasi Shopee
              </p>
            </a>
          </li>
        <?php } ?> -->
        <!-- <li class="nav-item">
          <a href="shopee" class="nav-link">
            <i class="nav-icon fa fa-plug"></i>
            <p>
              Integrasi Shopee
            </p>
          </a>
        </li> -->
      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
<?php if ((int) $sessionCabang < 1 && $levelLogin !== 'kasir' && $levelLogin !== 'kurir') : ?>
<script>
(function () {
  function refreshPengadaanBadge() {
    var $badge = $('#badge-pengadaan-gudang');
    if (!$badge.length) return;
    $.getJSON('api/pengadaan-gudang-notif.php').done(function (res) {
      if (!res || !res.ok) return;
      var total = (res.pending || 0) + (res.diproses || 0);
      if (total > 0) {
        $badge.text(total).show();
        $('#nav-pengadaan-gudang').addClass('text-warning');
      } else {
        $badge.hide();
        $('#nav-pengadaan-gudang').removeClass('text-warning');
      }
    });
  }
  $(function () {
    refreshPengadaanBadge();
    setInterval(refreshPengadaanBadge, 60000);
  });
})();
</script>
<?php endif; ?>