<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';

if ($levelLogin != "admin" && $levelLogin != "super admin") {
  echo "
    <script>
      document.location.href = 'bo';
    </script>
  ";
}

// cek apakah tombol submit sudah ditekan atau belum
if (isset($_POST["submit"])) {
  // var_dump($_POST);

  // cek apakah data berhasil di tambahkan atau tidak
  if (editLabaBersih($_POST) > 0) {
    echo "
      <script>
        alert('Data Berhasil diupdate');
        document.location.href = 'laba-bersih-data';
      </script>
    ";
  } elseif (editLabaBersih($_POST) == null) {
    echo "
      <script>
        alert('Anda Belum Melakukan Perubahan Data');
      </script>
    ";
  } else {
    echo "
      <script>
        alert('data gagal ditambahkan');
      </script>
    ";
  }
}

$labaBersih = query("SELECT * FROM laba_bersih WHERE lb_cabang = $sessionCabang")[0];
$listCabang = query("SELECT * FROM toko ");
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-9">
          <h1>Data Operasional Toko dari Pendapatan & Pengeluaran (<?= $levelLogin ?>)</h1>
        </div>
        <div class="col-sm-3">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Data Operasional</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header d-flex align-content-center">
          <h3 class="card-title">Data Operasional</h3>
          <button
            id="btn-add-modal"
            class="btn btn-success btn-sm ml-auto d-flex justify-content-around  align-content-center"
            data-toggle="modal"
            data-target="#modal-add"
            style="gap: 0.2rem;">
            <i class="bi bi-plus"></i>
            <span>
              Tambah
            </span>
          </button>
        </div>
        <div class="card-body">
          <div class="row">
            <!-- Baris 1: Periode Bulan, Jenis, Kategori, Cabang -->
            <div class="col-2">
              <div class="form-group">
                <label>Periode Bulan</label>
                <select class="form-control" id="bulan-filter">
                  <?php 
                  $bulanNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                  $currentMonth = date('n');
                  $currentYear = date('Y');
                  // Generate 12 bulan terakhir
                  for ($i = 0; $i < 12; $i++) {
                    $month = $currentMonth - $i;
                    $year = $currentYear;
                    if ($month <= 0) {
                      $month += 12;
                      $year--;
                    }
                    $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
                    $selected = ($i == 0) ? 'selected' : '';
                    echo "<option value=\"{$year}-{$monthPadded}\" {$selected}>{$bulanNames[$month]} {$year}</option>";
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-2">
              <div class="form-group">
                <label>Periode Custom</label>
                <div class="input-group input-group-sm">
                  <input type="date" class="form-control form-control-sm" id="date-start" style="font-size: 11px;">
                  <input type="date" class="form-control form-control-sm" id="date-end" style="font-size: 11px;">
                </div>
              </div>
            </div>
            <div class="col-2">
              <div class="form-group">
                <label for="jenis">Jenis</label>
                <select class="form-control" id="jenis">
                  <option value="">Semua</option>
                  <option value="0">Pendapatan</option>
                  <option value="1">Pengeluaran</option>
                </select>
              </div>
            </div>
            <div class="col-2">
              <div class="form-group">
                <label>Kategori</label>
                <select class="form-control kategori select2bs4" id="filter-kategori" style="width: 100%;">
                  <option value="">loading</option>
                </select>
              </div>
            </div>
            <div class="col-2">
              <div class="form-group">
                <label>Cabang</label>
                <select class="form-control" id="cabang" <?= $levelLogin == "super admin" ? "" : "disabled" ?>>
                  <option value="">Semua</option>
                  <?php foreach ($listCabang as $cab) : ?>
                    <option value="<?= $cab['toko_cabang'] ?>" <?= $cab['toko_cabang'] == $_SESSION['user_cabang'] ? 'selected' : '' ?>><?= $cab['toko_nama'] ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-2">
              <div class="form-group">
                <label>Tampil</label>
                <select class="form-control" id="per-page">
                  <option value="10">10</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="1000">Semua</option>
                </select>
              </div>
            </div>
            
            <!-- Baris 2: Pencarian Keterangan, PJ, Tombol Filter -->
            <div class="col-4">
              <div class="form-group mb-0">
                <label>Cari Keterangan</label>
                <input type="text" class="form-control" placeholder="Ketik untuk mencari keterangan..." id="keterangan">
              </div>
            </div>
            <div class="col-3">
              <div class="form-group mb-0">
                <label>Cari PJ (Penanggung Jawab)</label>
                <input type="text" class="form-control" placeholder="Ketik nama PJ..." id="pj-filter">
              </div>
            </div>
            <div class="col-3"></div>
            <div class="col-2 d-flex align-items-end pb-3">
              <button type="button" onclick="getData()" class="btn btn-primary btn-block">
                <i class="bi bi-filter"></i> Filter
              </button>
            </div>
            <div class="col-12 table-responsive mt-3">
              <table class="table table-striped ">
                <caption class="text-center">
                  Tabel Data Operasional periode <span id="period"></span>
                </caption>
                <thead class="thead-default">
                  <tr>
                    <th class="text-center" style="width: 40px;">No</th>
                    <th class="text-center" style="width: 160px;">
                      Dibuat
                      <button class="btn btn-sm btn-link p-0 ml-1 sort-btn" data-column="created_at" style="font-size: 12px; color: #6c757d; text-decoration: none;">
                        <i class="bi bi-arrow-down-up"></i>
                      </button>
                    </th>
                    <th class="text-center" style="width: 160px;">
                      Tanggal
                      <button class="btn btn-sm btn-link p-0 ml-1 sort-btn" data-column="date" style="font-size: 12px; color: #6c757d; text-decoration: none;">
                        <i class="bi bi-arrow-down-up"></i>
                      </button>
                    </th>
                    <th>Jenis</th>
                    <th>Kategori</th>
                    <th>Keterangan</th>
                    <th class="text-right">Cabang</th>
                    <th class="text-right">Nilai</th>
                    <th class="text-right">PJ</th>
                    <th class="text-center" style="width: 80px;">Lampiran</th>
                    <?php if ($levelLogin == 'super admin') : ?>
                      <th class="text-center" style="width: fit-content;">Aksi</th>
                    <?php endif; ?>
                  </tr>
                </thead>
                <tbody class="" id="table-data"></tbody>
              </table>
              <div id="pagination"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<div class="modal fade" id="modal-add" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-title">Tambah Transaksi</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="form-add" enctype="multipart/form-data">
          <input type="hidden" id="form-type" value="create">
          <input type="hidden" id="add-id" value="">

          <!-- Baris 1: Tanggal, Jenis Transaksi, Cabang -->
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="add-tanggal">Tanggal <span class="text-danger">*</span></label>
                <input type="date" name="date" id="add-tanggal" class="form-control" required>
                <div class="invalid-feedback">
                  Tanggal harus diisi
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="add-jenis-transaksi">Jenis Transaksi <span class="text-danger">*</span></label>
                <select class="form-control" id="add-jenis-transaksi" required>
                  <option value="">Pilih Jenis</option>
                  <option value="pemasukan">Pemasukan</option>
                  <option value="pengeluaran">Pengeluaran</option>
                  <option value="hutang">Hutang</option>
                  <option value="piutang">Piutang</option>
                  <option value="tanam_modal">Tanam Modal</option>
                  <option value="tarik_modal">Tarik Modal</option>
                  <option value="transfer_uang">Transfer Uang</option>
                  <option value="pemasukan_piutang">Pemasukan Piutang</option>
                  <option value="transfer_hutang">Transfer Hutang</option>
                </select>
                <div class="invalid-feedback">
                  Jenis Transaksi harus diisi
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="form-group">
                <label for="add-cabang">Cabang</label>
                <select name="cabang" class="form-control form-control" id="add-cabang" <?= $levelLogin != "super admin" ? 'disabled' : '' ?>>
                  <?php foreach ($listCabang as $c) : ?>
                    <option value="<?= $c['toko_cabang']; ?>"
                      <?= $_SESSION['user_cabang'] == $c['toko_cabang'] ? ' selected' : '' ?>>
                      <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- Baris 2: Akun Debit dan Akun Kredit -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="add-akun-debit" id="label-akun-debit">Akun Debit <span class="text-danger">*</span></label>
                <select class="form-control form-control kategori select2bs4" id="add-akun-debit" style="width: 100%;" required>
                  <option value="">Pilih Akun Debit</option>
                </select>
                <div class="invalid-feedback">
                  Akun Debit harus diisi
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="add-akun-kredit" id="label-akun-kredit">Akun Kredit <span class="text-danger">*</span></label>
                <select class="form-control form-control kategori select2bs4" id="add-akun-kredit" style="width: 100%;" required>
                  <option value="">Pilih Akun Kredit</option>
                </select>
                <div class="invalid-feedback">
                  Akun Kredit harus diisi
                </div>
              </div>
            </div>
          </div>

          <!-- Baris 3: Nominal, Bunga, Pajak, Total -->
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="add-nominal">Nominal <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="jumlah" class="form-control" id="add-nominal" placeholder="Masukkan nominal" required>
                <div class="invalid-feedback">
                  Nominal harus diisi
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="add-bunga">Bunga (%)</label>
                <input type="number" step="0.01" min="0" name="bunga" class="form-control" id="add-bunga" placeholder="0" value="0">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="add-pajak">Pajak (%)</label>
                <input type="number" step="0.01" min="0" name="pajak" class="form-control" id="add-pajak" placeholder="0" value="0">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="add-total">Total</label>
                <input type="text" class="form-control" id="add-total" readonly style="background-color: #e9ecef;">
                <small class="form-text text-muted">Total: Nominal + Bunga + Pajak</small>
              </div>
            </div>
          </div>

          <!-- Baris 4: Keterangan -->
          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label for="add-keterangan">Keterangan <span class="text-danger">*</span></label>
                <textarea name="keterangan" id="add-keterangan" class="form-control" rows="2" placeholder="Isikan keterangan transaksi" required></textarea>
                <div class="invalid-feedback">
                  Keterangan harus diisi
                </div>
              </div>
            </div>
          </div>

          <!-- Baris 5: Penanggung Jawab, Tag, File Lampiran -->
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="add-pj">Penanggung Jawab <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" id="add-pj" placeholder="Nama lengkap dengan gelar" required>
                <small class="form-text text-muted">Nama lengkap dengan gelar</small>
                <div class="invalid-feedback">
                  Penanggung Jawab harus diisi
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="add-tag">Tag</label>
                <input type="text" name="tag" class="form-control" id="add-tag" placeholder="Masukkan tag (pisahkan dengan koma)">
                <small class="form-text text-muted">Contoh: urgent, bulanan, proyek-a</small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="add-file-lampiran">File Lampiran</label>
                <input type="file" name="file_lampiran" class="form-control-file" id="add-file-lampiran" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                <small class="form-text text-muted">Format: PDF, JPG, JPEG, PNG, DOC, DOCX</small>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="btn-close" data-dismiss="modal">Tututp</button>
        <button type="button" class="btn btn-primary" id="btn-add">Simpan</button>
      </div>
    </div>
  </div>
</div>


<script src="./dist/js/utils.js"></script>
<script>
  const base_url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '')
  let page = 1
  const levelAdmin = '<?php echo $_SESSION['user_level']; ?>';
  
  // Sorting state
  let sortBy = 'created_at';
  let sortOrder = 'DESC';

  const deleteLaba = (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "You won't be able to revert this!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, delete it!"
    }).then((result) => {
      console.log("🚀 ~ deleteLaba ~ result:", result)
      if (result.value) {
        $.ajax({
          url: `${base_url}/api/laba.php?id=${id}`,
          type: 'DELETE',
          success: function(response) {
            if (response.success) {
              Swal.fire({
                title: "Deleted!",
                text: response.message || "Data berhasil dihapus",
                icon: "success"
              });
              getData();
            } else {
              Swal.fire({
                title: "Error!",
                text: response.message || "Gagal menghapus data",
                icon: "error"
              });
            }
          },
          error: function(xhr, status, error) {
            console.log(error);
            let errorMsg = "Terjadi kesalahan";
            try {
              const response = JSON.parse(xhr.responseText);
              errorMsg = response.message || errorMsg;
            } catch (e) {
              errorMsg = xhr.responseText || errorMsg;
            }
            Swal.fire({
              title: "Error!",
              text: errorMsg,
              icon: "error"
            });
          }
        });
      }
    });
  }

  const editLaba = (item) => {
    console.log('Edit item:', item); // Debug log

    // API date format: '17/11/2024, 06:22'
    const apiDate = item?.date || ''; // '17/11/2024, 06:22'

    if (apiDate) {
      // Split date and time parts
      const [datePart] = apiDate.split(','); // Extract '17/11/2024'

      // Split day, month, year
      const [day, month, year] = datePart.split('/'); // ['17', '11', '2024']

      // Reformat to 'YYYY-MM-DD' for input type="date"
      const formattedDate = `${year}-${month}-${day}`;

      // Set the value of the input
      $('#add-tanggal').val(formattedDate);
    } else {
      // Use the current date if API date is not available
      const currentDate = new Date();
      const today = currentDate.toISOString().split('T')[0]; // Format as 'YYYY-MM-DD'
      $('#add-tanggal').val(today);
    }

    $('#form-type').val('edit')
    $('#modal-title').text('Edit Transaksi')
    $('#add-id').val(item?.id)

    // Get cabang value first
    const cabangValue = item?.cabang?.cabang || item?.cabang || ''
    $('#add-cabang').val(cabangValue)

    // Get akun debit and kredit IDs
    const akunDebitId = item?.akun_debit || item?.akun_debit_detail?.id || item?.kategori?.id || ''
    const akunKreditId = item?.akun_kredit || item?.akun_kredit_detail?.id || ''

    // Get other values
    // Konversi tipe dari database ke jenis_transaksi
    // tipe = 0 (in/pemasukan) → jenis_transaksi = 'pemasukan'
    // tipe = 1 (out/pengeluaran) → jenis_transaksi = 'pengeluaran'
    let jenisTransaksi = item?.jenis_transaksi || ''
    if (!jenisTransaksi && item?.tipe !== undefined) {
      if (item.tipe == 0 || item.tipe === '0') {
        jenisTransaksi = 'pemasukan'
      } else if (item.tipe == 1 || item.tipe === '1') {
        jenisTransaksi = 'pengeluaran'
      }
    }
    
    // Nominal diambil dari kolom jumlah di database
    const nominal = item?.jumlah || item?.nominal || ''
    const bunga = item?.bunga || '0'
    const pajak = item?.pajak || '0'
    const total = item?.total || item?.jumlah || ''
    const keterangan = item?.keterangan || ''
    const pj = item?.name || ''
    const tag = item?.tag || ''

    // Set values that don't depend on kategori loading
    $('#add-jenis-transaksi').val(jenisTransaksi)
    $('#add-nominal').val(nominal)
    $('#add-bunga').val(bunga)
    $('#add-pajak').val(pajak)
    // Don't set total here, let calculateTotal() compute it based on nominal, bunga, pajak
    $('#add-keterangan').val(keterangan)
    $('#add-pj').val(pj)
    $('#add-tag').val(tag)

    // Update labels berdasarkan jenis transaksi
    updateLabelsByJenisTransaksi()

    // Load kategori/akun based on cabang, then set the selected values
    getKategori(cabangValue).done((res) => {
      // Wait a bit for Select2 to be fully initialized
      setTimeout(() => {
        // Set akun debit and kredit after kategori is loaded
        if (akunDebitId) {
          $('#add-akun-debit').val(akunDebitId).trigger('change')
        }
        if (akunKreditId) {
          $('#add-akun-kredit').val(akunKreditId).trigger('change')
        }

        // Calculate total after all values are set
        calculateTotal()
      }, 100)
    }).fail(() => {
      // If getKategori fails, still try to set values
      if (akunDebitId) {
        $('#add-akun-debit').val(akunDebitId).trigger('change')
      }
      if (akunKreditId) {
        $('#add-akun-kredit').val(akunKreditId).trigger('change')
      }
      calculateTotal()
    })

    $('#modal-add').modal('show')
  }

  const renderFileLampiran = (item) => {
    if (item?.file_lampiran) {
      const fileUrl = base_url + '/' + item.file_lampiran;
      const fileExtension = item.file_lampiran.split('.').pop().toLowerCase();
      const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension);
      const isPdf = fileExtension === 'pdf';

      if (isImage) {
        // For images, show preview button
        return `
          <td class="text-center">
            <button class="btn btn-info btn-sm" onclick="window.open('${fileUrl}', '_blank')" title="Lihat Lampiran">
              <i class="bi bi-image"></i>
            </button>
          </td>
        `;
      } else {
        // For PDF and other files, show download button
        return `
          <td class="text-center">
            <a href="${fileUrl}" target="_blank" class="btn btn-info btn-sm" title="Lihat/Download Lampiran">
              <i class="bi bi-file-earmark"></i>
            </a>
          </td>
        `;
      }
    } else {
      // Return empty cell if no file attachment
      return `<td class="text-center"></td>`;
    }
  }

  const renderAction = (item) => {
    // Use base64 encoding to store item data safely in data attribute
    const itemData = btoa(unescape(encodeURIComponent(JSON.stringify(item))));
    return `
      <td class="text-center">
        <button class="btn btn-danger btn-sm" onclick="deleteLaba('${item?.id}')"><i class="bi bi-trash"></i></button>
        <button class="btn btn-warning btn-sm btn-edit-laba" data-item="${itemData}"><i class="bi bi-pencil"></i></button>
      </td>
    `;
  }



  // Function to handle sorting
  const handleSort = (column) => {
    // If clicking the same column, toggle order; otherwise, set to ASC
    if (sortBy === column) {
      sortOrder = sortOrder === 'ASC' ? 'DESC' : 'ASC';
    } else {
      sortBy = column;
      sortOrder = 'ASC';
    }
    
    // Update button icons
    updateSortIcons();
    
    // Reload data with new sorting
    getData();
  }
  
  // Function to update sort button icons
  const updateSortIcons = () => {
    $('.sort-btn').each(function() {
      const column = $(this).data('column');
      const icon = $(this).find('i');
      
      if (sortBy === column) {
        if (sortOrder === 'ASC') {
          icon.removeClass('bi-arrow-down-up bi-arrow-down').addClass('bi-arrow-up');
          $(this).css('color', '#007bff');
        } else {
          icon.removeClass('bi-arrow-down-up bi-arrow-up').addClass('bi-arrow-down');
          $(this).css('color', '#007bff');
        }
      } else {
        icon.removeClass('bi-arrow-up bi-arrow-down').addClass('bi-arrow-down-up');
        $(this).css('color', '#6c757d');
      }
    });
  }

  const getData = (link) => {
    // Jika link diberikan (untuk pagination), gunakan link tersebut dan tambahkan parameter sorting jika belum ada
    if (link) {
      const url = new URL(link, window.location.origin);
      // Tambahkan parameter sorting jika belum ada
      if (!url.searchParams.has('sort_by')) {
        url.searchParams.set('sort_by', sortBy);
      }
      if (!url.searchParams.has('sort_order')) {
        url.searchParams.set('sort_order', sortOrder);
      }
      link = url.pathname + url.search;
    }
    
    // Ambil nilai cabang - jika "0" tetap kirim sebagai 0, bukan null
    const cabangVal = $('#cabang').val();
    const cabangParam = (cabangVal === '' || cabangVal === null) ? null : cabangVal;
    
    // Tentukan tanggal dari bulan filter atau custom date
    let dateStart = $('#date-start').val();
    let dateEnd = $('#date-end').val();
    
    // Jika custom date kosong, gunakan bulan filter
    if (!dateStart || !dateEnd) {
      const bulanVal = $('#bulan-filter').val(); // format: YYYY-MM
      if (bulanVal) {
        const [year, month] = bulanVal.split('-');
        dateStart = `${year}-${month}-01`;
        // Hitung hari terakhir bulan
        const lastDay = new Date(year, month, 0).getDate();
        dateEnd = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
      }
    }
    
    $.ajax({
      url: link ?? base_url + '/api/laba.php',
      method: 'GET',
      data: {
        date_start: dateStart,
        date_end: dateEnd,
        tipe: $('#jenis').val() ?? null,
        kategori: $('#filter-kategori').val() ?? null,
        cabang: cabangParam,
        keterangan: $('#keterangan').val(),
        name: $('#pj-filter').val(), // PJ/Penanggung Jawab
        per_page: $('#per-page').val() ?? 10,
        sort_by: sortBy,
        sort_order: sortOrder,
      },
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      beforeSend: () => {
        const colspan = levelAdmin == 'super admin' ? 11 : 10;
        $('#table-data').html(`<tr><td class="text-center" colspan="${colspan}">Loading...</td></tr>`)
      },
      success: (res) => {
        let html = ''
        if (res.success && res.data && res.data.data) {
          // Hitung offset untuk nomor urut berdasarkan halaman
          const currentPage = res.data?.current_page || 1;
          const perPage = res.data?.per_page || 10;
          const startNumber = (currentPage - 1) * perPage;
          
          res.data.data.forEach((item, index) => {
            html += `
              <tr>
                <td class="text-center">${startNumber + index + 1}</td>
                <td class="text-center">${item.created_at || '-'}</td>
                <td class="text-center">${item.date || '-'}</td>
                <td>${item.tipe==1?'Pengeluaran':'Pendapatan'}</td>
                <td>${item.kategori?.name || '-'}</td>
                <td>${item.keterangan || '-'}</td>
                <td class="text-right">${item.cabang?.name || '-'}</td>
                <td class="text-right">${toRupiah(item.jumlah,false)}</td>
                <td class="text-right">${item?.name || "-"}</td>
                ${renderFileLampiran(item)}
                ${levelAdmin == 'super admin' ? renderAction(item) : ''}
              </tr>`
          })
          $('#table-data').html(html)
          
          // Update sort icons after data is loaded
          updateSortIcons();
          
          // Update period text
          let periodStart = $('#date-start').val();
          let periodEnd = $('#date-end').val();
          if (!periodStart || !periodEnd) {
            // Gunakan bulan filter
            const bulanVal = $('#bulan-filter').val();
            if (bulanVal) {
              const [year, month] = bulanVal.split('-');
              periodStart = `${year}-${month}-01`;
              const lastDay = new Date(year, month, 0).getDate();
              periodEnd = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
            }
          }
          if (periodStart && periodEnd) {
            $('#period').text(`${periodStart.split('-').reverse().join('/')} s/d ${periodEnd.split('-').reverse().join('/')}`);
          }
          
          let pagination = ''
          if (res.data?.links && res.data.links.length > 0) {
            pagination += `<nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center">`
            res.data.links.forEach((item, index) => {
              if (item.url && item.label && (index === 0 || index === res.data.links.length - 1 || !isNaN(item.label))) {
                pagination += `<li class="page-item ${item.active?'active':''}"><button class="page-link" onclick="getData('${item.url}')">${item.label}</button></li>`
              }
            })
            pagination += `</ul></nav>`
          }
          $('#pagination').html(pagination)
        } else {
          const colspan = levelAdmin == 'super admin' ? 10 : 9;
          $('#table-data').html(`<tr><td class="text-center" colspan="${colspan}">Data Tidak Ditemukan</td></tr>`)
        }
      },
      error: (err) => {
        console.log(err)
        const colspan = levelAdmin == 'super admin' ? 10 : 9;
        $('#table-data').html(`<tr><td class="text-center" colspan="${colspan}">Data Tidak Ditemukan</td></tr>`)
      }
    })
  }

  // Fungsi untuk menghitung total
  const calculateTotal = () => {
    const nominal = parseFloat($('#add-nominal').val()) || 0
    const bunga = parseFloat($('#add-bunga').val()) || 0
    const pajak = parseFloat($('#add-pajak').val()) || 0

    const bungaAmount = (nominal * bunga) / 100
    const pajakAmount = (nominal * pajak) / 100
    const total = nominal + bungaAmount + pajakAmount

    // Format as number (not currency) for form submission, but display formatted
    $('#add-total').val(total.toFixed(2))
  }

  // Fungsi untuk update label berdasarkan jenis transaksi
  const updateLabelsByJenisTransaksi = () => {
    const jenisTransaksi = $('#add-jenis-transaksi').val()
    const labelDebit = $('#label-akun-debit')
    const labelKredit = $('#label-akun-kredit')

    // Mapping label berdasarkan jenis transaksi
    const labelMapping = {
      'pemasukan': {
        debit: 'Simpan ke (Debit)',
        kredit: 'Diterima dari (Kredit)'
      },
      'pengeluaran': {
        debit: 'Untuk biaya (Debit)',
        kredit: 'Diambil dari (Kredit)'
      },
      'hutang': {
        debit: 'Simpan ke (Debit)',
        kredit: 'Hutang dari (Kredit)'
      },
      'piutang': {
        debit: 'Simpan ke (Debit)',
        kredit: 'Dari (Kredit)'
      },
      'tanam_modal': {
        debit: 'Modal (Debit)',
        kredit: 'Modal (Kredit)'
      },
      'tarik_modal': {
        debit: 'Modal (Debit)',
        kredit: 'Diambil dari (Kredit)'
      },
      'transfer_uang': {
        debit: 'ke (Debit)',
        kredit: 'dari (Kredit)'
      },
      'pemasukan_piutang': {
        debit: 'Simpan ke (Debit)',
        kredit: 'Diterima dari (Kredit)'
      },
      'transfer_hutang': {
        debit: 'Untuk biaya (Debit)',
        kredit: 'Diambil dari (Kredit)'
      }
    }

    if (jenisTransaksi && labelMapping[jenisTransaksi]) {
      labelDebit.html(labelMapping[jenisTransaksi].debit + ' <span class="text-danger">*</span>')
      labelKredit.html(labelMapping[jenisTransaksi].kredit + ' <span class="text-danger">*</span>')
    } else {
      // Default label
      labelDebit.html('Akun Debit <span class="text-danger">*</span>')
      labelKredit.html('Akun Kredit <span class="text-danger">*</span>')
    }
  }

  // Fungsi untuk mengambil dan menerapkan mapping default berdasarkan jenis transaksi
  const applyTransactionMapping = () => {
    const jenisTransaksi = $('#add-jenis-transaksi').val()
    const cabangId = $('#add-cabang').val() || '<?php echo $_SESSION['user_cabang'] ?? ''; ?>'
    
    if (!jenisTransaksi || !cabangId) return
    
    $.ajax({
      url: base_url + '/api/transaksi-mapping.php',
      method: 'GET',
      data: {
        cabang: cabangId,
        jenis_transaksi: jenisTransaksi
      },
      dataType: 'json',
      success: function(response) {
        if (response.success && response.data && response.data.length > 0) {
          const mapping = response.data[0]
          
          // Terapkan akun debit jika ada
          if (mapping.akun_debit) {
            $('#add-akun-debit').val(mapping.akun_debit).trigger('change')
          }
          
          // Terapkan akun kredit jika ada
          if (mapping.akun_kredit) {
            $('#add-akun-kredit').val(mapping.akun_kredit).trigger('change')
          }
          
          // Tampilkan notifikasi kecil bahwa mapping diterapkan
          if (mapping.akun_debit || mapping.akun_kredit) {
            console.log('Mapping diterapkan untuk jenis transaksi:', jenisTransaksi)
          }
        }
      },
      error: function(err) {
        console.log('Mapping tidak ditemukan atau error:', err)
      }
    })
  }

  // Function to update dates from bulan filter
  function updateDatesFromBulan() {
    const bulanVal = $('#bulan-filter').val(); // format: YYYY-MM
    if (bulanVal) {
      const [year, month] = bulanVal.split('-');
      const lastDay = new Date(year, month, 0).getDate();
      // Set internal reference (tidak perlu isi input date)
      window.currentDateStart = `${year}-${month}-01`;
      window.currentDateEnd = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;
    }
  }
  
  const getKategori = (cabangId = null) => {
    // Get cabang from parameter, form, or session
    if (!cabangId) {
      cabangId = $('#add-cabang').val() || '<?php echo $_SESSION['user_cabang'] ?? ''; ?>';
    }

    const url = base_url + '/api/laba-kategori.php' + (cabangId ? '?cabang=' + cabangId : '');

    // Return a Promise so we can wait for the data to load
    return $.ajax({
      url: url,
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      success: (res) => {
        let htmlFilter = '<option value="">Semua Kategori</option>'
        let htmlDebit = '<option value="">Pilih Akun Debit</option>'
        let htmlKredit = '<option value="">Pilih Akun Kredit</option>'

        if (res.success && res.data) {
          res.data.forEach((item, index) => {
            const displayText = item.kode_akun ? `${item.kode_akun} - ${item.name}` : item.name
            htmlFilter += `<option value="${item.id}">${displayText}</option>`
            htmlDebit += `<option value="${item.id}">${displayText}</option>`
            htmlKredit += `<option value="${item.id}">${displayText}</option>`
          })
        }

        // Update filter kategori
        $('#filter-kategori').html(htmlFilter)
        // Update form akun debit dan kredit
        $('#add-akun-debit').html(htmlDebit)
        $('#add-akun-kredit').html(htmlKredit)

        // Initialize Select2 untuk kategori setelah data dimuat (only if not already initialized)
        if (!$('#filter-kategori').hasClass('select2-hidden-accessible')) {
          $('#filter-kategori').select2({
            theme: 'bootstrap4',
            placeholder: 'Cari atau pilih kategori',
            allowClear: true
          })
        }

        // Destroy and reinitialize Select2 for akun debit and kredit to ensure fresh options
        if ($('#add-akun-debit').hasClass('select2-hidden-accessible')) {
          $('#add-akun-debit').select2('destroy')
        }
        $('#add-akun-debit').select2({
          theme: 'bootstrap4',
          placeholder: 'Cari atau pilih akun debit',
          allowClear: true,
          dropdownParent: $('#modal-add')
        })

        if ($('#add-akun-kredit').hasClass('select2-hidden-accessible')) {
          $('#add-akun-kredit').select2('destroy')
        }
        $('#add-akun-kredit').select2({
          theme: 'bootstrap4',
          placeholder: 'Cari atau pilih akun kredit',
          allowClear: true,
          dropdownParent: $('#modal-add')
        })
      },
    })
  }

  $('#btn-add-modal').click(() => {
    $('#form-type').val('create')
    $('#modal-title').text('Tambah Transaksi')
    // Reset form
    $('#form-add')[0].reset()
    $('#add-akun-debit, #add-akun-kredit').val(null).trigger('change')
    $('#add-bunga, #add-pajak').val('0')
    $('#add-total').val('')
    // Set default date to today
    const today = new Date().toISOString().split('T')[0]
    $('#add-tanggal').val(today)
    // Reset labels to default
    updateLabelsByJenisTransaksi()
  })

  $(document).ready(function() {
    // Set initial dates from bulan filter (default: bulan ini)
    updateDatesFromBulan();
    getKategori()
    
    // Initialize sort icons
    updateSortIcons();
    
    getData()
    
    // Event listener untuk tombol sorting
    $(document).on('click', '.sort-btn', function(e) {
      e.preventDefault();
      const column = $(this).data('column');
      handleSort(column);
    });
    
    // Event listener untuk bulan filter change
    $('#bulan-filter').on('change', function() {
      updateDatesFromBulan();
      // Clear custom dates when bulan changed
      $('#date-start').val('');
      $('#date-end').val('');
    });
    
    // Event listener untuk custom date change - clear bulan selection visual
    $('#date-start, #date-end').on('change', function() {
      // Ketika user mengisi custom date, kosongkan bulan filter
      if ($('#date-start').val() && $('#date-end').val()) {
        // Biarkan custom date digunakan
      }
    });
    
    // Enter key untuk search
    $('#keterangan, #pj-filter').on('keypress', function(e) {
      if (e.which === 13) {
        getData();
      }
    });

    // Event listener untuk reload akun saat cabang berubah
    $('#add-cabang').on('change', function() {
      const cabangId = $(this).val()
      if (cabangId) {
        getKategori(cabangId)
        // Reset akun yang dipilih
        $('#add-akun-debit, #add-akun-kredit').val(null).trigger('change')
      }
    })

    // Event listeners untuk menghitung total otomatis
    $('#add-nominal, #add-bunga, #add-pajak').on('input', function() {
      calculateTotal()
    })

    // Event listener untuk perubahan jenis transaksi
    $('#add-jenis-transaksi').on('change', function() {
      updateLabelsByJenisTransaksi()
      // Hanya apply mapping jika mode create (bukan edit)
      if ($('#form-type').val() === 'create') {
        applyTransactionMapping()
      }
    })

    // Event delegation untuk tombol edit (karena tombol dibuat secara dinamis)
    $(document).on('click', '.btn-edit-laba', function() {
      try {
        // Get base64 encoded data from attribute and decode it
        const itemDataStr = $(this).attr('data-item');
        if (itemDataStr) {
          // Decode base64 to JSON string, then parse
          const jsonStr = decodeURIComponent(escape(atob(itemDataStr)));
          const itemData = JSON.parse(jsonStr);
          editLaba(itemData);
        } else {
          console.error('Item data not found in data-item attribute');
          alert('Data tidak ditemukan');
        }
      } catch (e) {
        console.error('Error parsing item data:', e);
        console.error('Raw data:', $(this).attr('data-item'));
        console.error('Error details:', e.message, e.stack);
        alert('Terjadi kesalahan saat memuat data untuk diedit: ' + e.message);
      }
    })

    // Button click triggers form submission
    $('#btn-add').on('click', function() {
      const tanggal = $('#add-tanggal')
      const jenisTransaksi = $('#add-jenis-transaksi')
      const akunDebit = $('#add-akun-debit')
      const akunKredit = $('#add-akun-kredit')
      const nominal = $('#add-nominal')
      const keterangan = $('#add-keterangan')
      const pj = $('#add-pj')

      // Reset validation
      tanggal.removeClass('is-invalid')
      jenisTransaksi.removeClass('is-invalid')
      akunDebit.removeClass('is-invalid')
      akunKredit.removeClass('is-invalid')
      nominal.removeClass('is-invalid')
      keterangan.removeClass('is-invalid')
      pj.removeClass('is-invalid')

      // Validation
      let isValid = true
      if (!tanggal.val()) {
        tanggal.addClass('is-invalid')
        isValid = false
      }
      if (!jenisTransaksi.val()) {
        jenisTransaksi.addClass('is-invalid')
        isValid = false
      }
      if (!akunDebit.val()) {
        akunDebit.addClass('is-invalid')
        isValid = false
      }
      if (!akunKredit.val()) {
        akunKredit.addClass('is-invalid')
        isValid = false
      }
      if (!nominal.val() || parseFloat(nominal.val()) <= 0) {
        nominal.addClass('is-invalid')
        isValid = false
      }
      if (!keterangan.val() || keterangan.val().trim() === '') {
        keterangan.addClass('is-invalid')
        isValid = false
      }
      if (!pj.val() || pj.val().trim() === '') {
        pj.addClass('is-invalid')
        isValid = false
      }

      if (!isValid) {
        Swal.fire({
          icon: "error",
          title: "Validasi Gagal",
          text: "Periksa kembali isian, field yang bertanda * wajib diisi",
        });
        return
      }

      // Validasi akun debit dan kredit tidak boleh sama
      if (akunDebit.val() === akunKredit.val()) {
        Swal.fire({
          icon: "error",
          title: "Validasi Gagal",
          text: "Akun Debit dan Akun Kredit tidak boleh sama",
        });
        akunDebit.addClass('is-invalid')
        akunKredit.addClass('is-invalid')
        return
      }

      // Get cabang value - if disabled, get from selected option
      let cabangValue = $('#add-cabang').val();
      if (!cabangValue && $('#add-cabang').prop('disabled')) {
        cabangValue = $('#add-cabang option:selected').val();
      }

      // Calculate total
      const nominalVal = parseFloat(nominal.val()) || 0
      const bungaVal = parseFloat($('#add-bunga').val()) || 0
      const pajakVal = parseFloat($('#add-pajak').val()) || 0
      const bungaAmount = (nominalVal * bungaVal) / 100
      const pajakAmount = (nominalVal * pajakVal) / 100
      const total = nominalVal + bungaAmount + pajakAmount

      // Check if file is selected
      const fileInput = $('#add-file-lampiran')[0];
      const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

      // Prepare form data
      let formData;
      let contentType = 'application/json';
      let processData = true;

      if (hasFile) {
        // Use FormData for file upload
        formData = new FormData();
        
        // Add action and ID first if in edit mode (use POST with action=update for file upload)
        if ($('#form-type').val() === 'edit' && $('#add-id').val()) {
          formData.append('action', 'update');
          formData.append('id', $('#add-id').val());
        }
        
        formData.append('date', tanggal.val());
        formData.append('jenis_transaksi', jenisTransaksi.val());
        formData.append('akun_debit', akunDebit.val());
        formData.append('akun_kredit', akunKredit.val());
        formData.append('nominal', nominalVal);
        formData.append('jumlah', total);
        formData.append('bunga', bungaVal);
        formData.append('pajak', pajakVal);
        formData.append('total', total);
        formData.append('keterangan', keterangan.val().trim());
        formData.append('cabang', cabangValue);
        formData.append('name', pj.val().trim());
        if ($('#add-tag').val().trim()) {
          formData.append('tag', $('#add-tag').val().trim());
        }
        formData.append('file_lampiran', fileInput.files[0]);
        contentType = false; // Let jQuery set it automatically for FormData
        processData = false; // Don't process FormData
      } else {
        // Use JSON for regular data
        formData = {
          date: tanggal.val(),
          jenis_transaksi: jenisTransaksi.val(),
          akun_debit: akunDebit.val(),
          akun_kredit: akunKredit.val(),
          nominal: nominalVal,
          jumlah: total,
          bunga: bungaVal,
          pajak: pajakVal,
          total: total,
          keterangan: keterangan.val().trim(),
          cabang: cabangValue,
          name: pj.val().trim(),
          tag: $('#add-tag').val().trim() || null
        };
      }

      // Debug: Log form data
      console.log('Form Data to be sent:', formData);

      if ($('#form-type').val() == 'create') {
        return createOperasional(formData, contentType, processData)
      } else if ($('#form-type').val() == 'edit') {
        // Validasi ID harus ada untuk edit
        const editId = $('#add-id').val();
        console.log('Edit ID:', editId);
        console.log('Has File:', hasFile);
        console.log('Form Data before sending:', formData);
        
        if (!editId || editId === '') {
          Swal.fire({
            icon: "error",
            title: "Validasi Gagal",
            text: "ID transaksi tidak ditemukan. Silakan tutup modal dan coba lagi.",
          });
          return;
        }
        
        // ID sudah ditambahkan ke FormData saat membuat FormData (jika hasFile)
        // Jika tidak ada file, tambahkan ID ke object JSON
        if (!hasFile) {
          formData.id = editId;
        }
        
        // Verify ID is in FormData if hasFile
        if (hasFile && formData instanceof FormData) {
          console.log('FormData entries:');
          for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
          }
        }
        
        return updateOperasional(formData, contentType, processData)
      } else {
        Swal.fire({
          icon: "error",
          title: "Oops...",
          text: "Terjadi kesalahan - tipe pengiriman tidak ditemukan !",
        });
      }
    });

    $('#btn-close').on('click', function() {
      // Reset validation classes
      $('#add-tanggal, #add-jenis-transaksi, #add-akun-debit, #add-akun-kredit, #add-nominal, #add-keterangan, #add-pj').removeClass('is-invalid')
      $('#form-add')[0].reset()
      // Reset Select2
      $('#add-akun-debit, #add-akun-kredit').val(null).trigger('change')
      $('#add-bunga, #add-pajak').val('0')
      $('#add-total').val('')
      $('#btn-add').prop('disabled', false).html('Simpan')
    })
  })

  function createOperasional(formData, contentType = 'application/json', processData = true) {
    $.ajax({
      url: base_url + '/api/laba.php',
      method: 'POST',
      data: processData ? JSON.stringify(formData) : formData,
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      contentType: contentType,
      processData: processData,
      beforeSend: () => {
        $('#btn-add').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...')
      },
      success: (res) => {
        console.log(res)
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Berhasil!",
            text: res.message || "Data berhasil disimpan",
          });
          getData()
          // Reset validation classes
          $('#add-tanggal, #add-jenis-transaksi, #add-akun-debit, #add-akun-kredit, #add-nominal, #add-keterangan, #add-pj').removeClass('is-invalid')
          $('#form-add')[0].reset()
          $('#add-akun-debit, #add-akun-kredit').val(null).trigger('change')
          $('#add-bunga, #add-pajak').val('0')
          $('#add-total').val('')
          $('#btn-close').click()
        } else {
          alert(res.message || 'Terjadi Kesalahan')
        }
        $('#btn-add').prop('disabled', false).html('Simpan')
      },
      error: (err) => {
        console.log('Error:', err)
        $('#btn-add').prop('disabled', false).html('Simpan')
        let errorMsg = "Terjadi kesalahan";
        let errorDetails = "";
        try {
          const response = JSON.parse(err.responseText);
          errorMsg = response.message || errorMsg;
          if (response.errors && Array.isArray(response.errors)) {
            errorDetails = "\n\nDetail:\n" + response.errors.join("\n");
          }
          if (response.received_data) {
            console.log('Received data by API:', response.received_data);
          }
        } catch (e) {
          errorMsg = err.responseText || errorMsg;
        }
        alert('Terjadi Kesalahan - ' + errorMsg + errorDetails)
      }
    });
  }

  function updateOperasional(formData, contentType = 'application/json', processData = true) {
    // Use POST for FormData (file upload), PUT for JSON
    const usePost = (contentType === false || !processData); // FormData uses contentType=false and processData=false
    
    $.ajax({
      url: base_url + '/api/laba.php',
      method: usePost ? 'POST' : 'PUT',
      data: processData ? JSON.stringify(formData) : formData,
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      contentType: contentType,
      processData: processData,
      beforeSend: () => {
        $('#btn-add').prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...')
      },
      success: (res) => {
        console.log(res)
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "Berhasil!",
            text: res.message || "Data berhasil diupdate",
          });
          getData()
          // Reset validation classes
          $('#add-tanggal, #add-jenis-transaksi, #add-akun-debit, #add-akun-kredit, #add-nominal, #add-keterangan, #add-pj').removeClass('is-invalid')
          $('#form-add')[0].reset()
          $('#add-akun-debit, #add-akun-kredit').val(null).trigger('change')
          $('#add-bunga, #add-pajak').val('0')
          $('#add-total').val('')
          $('#btn-close').click()
        } else {
          alert(res.message || 'Terjadi Kesalahan')
        }
        $('#btn-add').prop('disabled', false).html('Simpan')
      },
      error: (err) => {
        console.log('Error:', err)
        $('#btn-add').prop('disabled', false).html('Simpan')
        let errorMsg = "Terjadi kesalahan";
        let errorDetails = "";
        try {
          const response = JSON.parse(err.responseText);
          errorMsg = response.message || errorMsg;
          if (response.errors && Array.isArray(response.errors)) {
            errorDetails = "\n\nDetail:\n" + response.errors.join("\n");
          }
          if (response.received_data) {
            console.log('Received data by API:', response.received_data);
          }
        } catch (e) {
          errorMsg = err.responseText || errorMsg;
        }
        alert('Terjadi Kesalahan - ' + errorMsg + errorDetails)
      }
    });
  }
</script>


<?php include '_footer.php'; ?>