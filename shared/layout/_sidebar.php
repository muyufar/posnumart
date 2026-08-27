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
              <?php if ($levelLogin !== 'kurir') { ?>
              <li class="nav-item">
                <a href="marketplace-pesanan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Belanja Online</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="marketplace-min-order" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Minimal Pesanan Online</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="marketplace-diskon" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Diskon Online</p>
                </a>
              </li>
              <?php } ?>
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
                    <a href="customer-wa-cron-monitor" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Monitor Cron WA</p>
                    </a>
                  </li>
                  <li class="nav-item">
                    <a href="wa-device-connect" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>WA Device</p>
                    </a>
                  </li>
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
              <li class="nav-item">
                <a href="laporan-penjualan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan Penjualan</p>
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
                  <?php if ($levelLogin === 'super admin' || $levelLogin === 'admin') : ?>
                  <li class="nav-item">
                    <a href="rekonsiliasi-piutang" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Rekonsiliasi Piutang</p>
                    </a>
                  </li>
                  <?php endif; ?>
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
                  <p class="pgd-nav-row">
                    <span class="nav-label-text">Pusat Pengadaan Gudang</span>
                    <span class="badge badge-pengadaan-nav" id="badge-pengadaan-gudang" style="display:none;" title="Permintaan aktif">0</span>
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="pengadaan-po-riwayat" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Riwayat PO &amp; Transfer</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="pengadaan-po-tidak-datang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Barang PO Tidak Datang</p>
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
              <li class="nav-item">
                <a href="laporan-pembelian" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan Pembelian</p>
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
                  <?php if ($levelLogin === 'super admin' || $levelLogin === 'admin') : ?>
                  <li class="nav-item">
                    <a href="rekonsiliasi-hutang" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Rekonsiliasi Hutang</p>
                    </a>
                  </li>
                  <?php endif; ?>
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
                    <a href="cetak-berita-acara-kirim-barang" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Cetak Berita Acara Kirim Barang</p>
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
              <?php if ((int) $sessionCabang === 0) : ?>
              <li class="nav-item">
                <a href="satuan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Satuan</p>
                </a>
              </li>
              <?php endif; ?>
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
              <?php if ((int) $sessionCabang === 0) : ?>
              <li class="nav-item">
                <a href="barang-ubah-barcode" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Ubah Barcode</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="perbaiki-hpp-barang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Perbaiki HPP</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="perbaiki-hpp-ganti-satuan" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>HPP Ganti Satuan</p>
                </a>
              </li>
              <?php if ((int) $sessionCabang === 0 || $levelLogin === 'super admin') : ?>
              <li class="nav-item">
                <a href="hpp-perbaikan-gudang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>
                    Perbaikan HPP Gudang
                    <?php
                      if (!function_exists('hpp_perbaikan_count_baru')) {
                        require_once numart_path('aksi/hpp-perbaikan-lib.php');
                      }
                      $hppReqBaru = 0;
                      try {
                        $hppReqBaru = hpp_perbaikan_count_baru($conn);
                      } catch (Throwable $e) {
                        $hppReqBaru = 0;
                      }
                      if ($hppReqBaru > 0) :
                    ?>
                      <span class="badge badge-danger right"><?= (int) $hppReqBaru; ?></span>
                    <?php endif; ?>
                  </p>
                </a>
              </li>
              <?php endif; ?>
              <?php if ((int) $sessionCabang > 0) : ?>
              <li class="nav-item">
                <a href="hpp-perbaikan-toko" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Minta Perbaikan HPP</p>
                </a>
              </li>
              <?php endif; ?>
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
              <?php if ($levelLogin === 'super admin' || $levelLogin === 'admin') : ?>
              <li class="nav-item">
                <a href="coa-link-nugrosir" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Link COA ke Nugrosir</p>
                </a>
              </li>
              <?php endif; ?>
              <li class="nav-item">
                <a href="laba-bersih-data" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Data Operasional</p>
                </a>
              </li>
              <?php if ($levelLogin === 'super admin' || $levelLogin === 'admin') : ?>
              <li class="nav-item">
                <a href="rekonsiliasi-hutang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Rekonsiliasi Hutang</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="rekonsiliasi-piutang" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Rekonsiliasi Piutang</p>
                </a>
              </li>
              <?php endif; ?>
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
                  <p>Laba Rugi Accrual</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="laba-bersih-laporan-neraca" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Laporan Neraca</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="laba-bersih-laporan-neraca-konsolidasi" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Neraca Konsolidasi</p>
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
                    <a href="laporan-penjualan" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Laporan Lengkap</p>
                    </a>
                  </li>
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
                    <a href="laporan-penjualan-kategori" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Per Kategori</p>
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
                    <a href="laporan-pembelian" class="nav-link">
                      <i class="far fa-dot-circle nav-icon"></i>
                      <p>Laporan Lengkap</p>
                    </a>
                  </li>
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
              <?php if (function_exists('numart_is_local_dev_host') && numart_is_local_dev_host()) { ?>
                <li class="nav-item">
                  <a href="sync-database-live" class="nav-link">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Sync DB Live Server</p>
                  </a>
                </li>
              <?php } ?>
              <li class="nav-item">
                <a href="export-baqnu" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Export BAQNU</p>
                </a>
              </li>
              <li class="nav-item">
                <a href="arsip-baqnu" class="nav-link">
                  <i class="far fa-circle nav-icon"></i>
                  <p>Arsip BAQNU</p>
                </a>
              </li>
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
<style>
/* Override AdminLTE .right absolute — badge notifikasi pengadaan */
#nav-pengadaan-gudang .pgd-nav-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  width: 100%;
  padding-right: 0.25rem;
}
#nav-pengadaan-gudang .nav-label-text {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
#nav-pengadaan-gudang #badge-pengadaan-gudang.badge-pengadaan-nav {
  position: static !important;
  right: auto !important;
  top: auto !important;
  float: none !important;
  flex-shrink: 0;
  font-size: 0.625rem;
  font-weight: 700;
  min-width: 1.125rem;
  height: 1.125rem;
  line-height: 1.125rem;
  padding: 0 0.35rem;
  border-radius: 999px;
  display: none;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 2px rgba(0,0,0,.2);
}
#nav-pengadaan-gudang #badge-pengadaan-gudang.badge-pengadaan-nav.is-visible {
  display: inline-flex !important;
}
#nav-pengadaan-gudang #badge-pengadaan-gudang.badge-light {
  background: #fff;
  color: #0d9488;
}
</style>
<script>
(function () {
  function refreshPengadaanBadge() {
    var $badge = $('#badge-pengadaan-gudang');
    var $nav = $('#nav-pengadaan-gudang');
    if (!$badge.length) return;
    $.getJSON('api/pengadaan-gudang-notif.php').done(function (res) {
      if (!res || !res.ok) return;
      var total = (res.pending || 0) + (res.diproses || 0);
      var kritis = res.kritis || 0;
      if (total > 0) {
        $badge.text(total > 99 ? '99+' : total).addClass('is-visible').show();
        $nav.addClass('has-badge');
        $badge.removeClass('badge-danger badge-warning badge-info badge-light is-kritis');
        if (kritis > 0) {
          $badge.addClass('badge-danger is-kritis').attr('title', kritis + ' permintaan KRITIS');
        } else {
          $badge.addClass('badge-light').attr('title', total + ' permintaan menunggu/diproses');
        }
      } else {
        $badge.removeClass('is-visible').hide();
        $nav.removeClass('has-badge');
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