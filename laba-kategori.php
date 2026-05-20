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

// Get list of cabang for filter and display
$listCabang = query("SELECT * FROM toko ORDER BY toko_nama");
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-9">
          <h1>Data Kategori Laba</h1>
        </div>
        <div class="col-sm-3">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item active">Kategori Laba</li>
          </ol>
        </div>
      </div>
    </div><!-- /.container-fluid -->
  </section>
  <section class="content">
    <div class="container-fluid">
      <div class="card card-primary">
        <div class="card-header d-flex align-content-center">
          <h3 class="card-title">Data Kategori Laba</h3>
          <div class="ml-auto d-flex" style="gap: 0.5rem;">
            <?php
            // Cek apakah ada data dengan kode_akun dan name null
            $check_null = query("SELECT COUNT(*) as jumlah FROM laba_kategori WHERE (kode_akun IS NULL OR kode_akun = '' OR kode_akun = '-') AND (name IS NULL OR name = '' OR name = '-')");
            $jumlah_null = $check_null[0]['jumlah'] ?? 0;
            if ($jumlah_null > 0):
            ?>
            <a href="hapus-akun-null.php" class="btn btn-danger btn-sm d-flex justify-content-around align-content-center" style="gap: 0.2rem;">
              <i class="fa fa-trash"></i>
              <span>Hapus Akun Null (<?php echo $jumlah_null; ?>)</span>
            </a>
            <?php endif; ?>
            <a href="recalculate-laba-kategori.php" class="btn btn-info btn-sm d-flex justify-content-around align-content-center" style="gap: 0.2rem;">
              <i class="fa fa-calculator"></i>
              <span>Hitung Ulang Saldo</span>
            </a>
            <button
              id="btn-add-modal"
              class="btn btn-success btn-sm d-flex justify-content-around align-content-center"
              data-toggle="modal"
              data-target="#modal-add"
              style="gap: 0.2rem;">
              <i class="bi bi-plus"></i>
              <span>Tambah</span>
            </button>
          </div>
        </div>
        <div class="card-body">
          <!-- Filter dan Pencarian -->
          <div class="row mb-3">
            <div class="col-md-3">
              <div class="form-group">
                <label>Filter Cabang</label>
                <select class="form-control" id="filter-cabang">
                  <option value="">Semua Cabang</option>
                  <option value="0">PCNU (Default)</option>
                  <?php 
                  foreach ($listCabang as $c) : 
                  ?>
                    <option value="<?= $c['toko_cabang']; ?>"
                      <?= (isset($_SESSION['user_cabang']) && $_SESSION['user_cabang'] == $c['toko_cabang']) ? ' selected' : '' ?>>
                      <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Filter Kategori</label>
                <select class="form-control" id="filter-kategori">
                  <option value="">Semua Kategori</option>
                  <option value="aktiva">Aktiva</option>
                  <option value="pasiva">Pasiva</option>
                  <option value="modal">Modal</option>
                  <option value="pendapatan">Pendapatan</option>
                  <option value="beban">Beban</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Filter Tipe Akun</label>
                <select class="form-control" id="filter-tipe-akun">
                  <option value="">Semua Tipe</option>
                  <option value="debit">Debit</option>
                  <option value="kredit">Kredit</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Cari</label>
                <input type="text" class="form-control" id="search-input" placeholder="Cari nama atau kode akun...">
              </div>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <button type="button" class="btn btn-primary btn-sm" id="btn-apply-filter">
                <i class="fas fa-filter"></i> Terapkan Filter
              </button>
              <button type="button" class="btn btn-secondary btn-sm" id="btn-reset-filter">
                <i class="fas fa-redo"></i> Reset
              </button>
            </div>
          </div>
          
          <div class="table-responsive">
            <table class="table table-striped table-bordered" id="laba-kategori-table">
              <thead class="thead-default">
                <tr>
                  <th class="text-center" style="width: 50px;">No</th>
                  <th class="sortable-th text-nowrap" data-sort="kode_akun" style="cursor: pointer;" title="Klik untuk urutkan kode akun">
                    Kode Akun <span class="sort-indicator text-primary font-weight-bold" aria-hidden="true"></span>
                  </th>
                  <th class="text-center text-nowrap" style="width: 110px;">Level COA</th>
                  <th class="sortable-th" data-sort="name" style="cursor: pointer;" title="Klik untuk urutkan nama">
                    Nama Kategori <span class="sort-indicator text-primary font-weight-bold" aria-hidden="true"></span>
                  </th>
                  <th>Kategori</th>
                  <th>Tipe Akun</th>
                  <th>Cabang</th>
                  <th class="text-right">Saldo</th>
                  <th class="text-center" style="width: 150px;">Aksi</th>
                </tr>
              </thead>
              <tbody id="table-data"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="modal-add" data-backdrop="static" data-keyboard="false" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modal-title">Tambah Kategori Laba</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="form-add">
          <input type="hidden" id="form-type" value="create">
          <input type="hidden" id="add-id" value="">

          <div class="form-group">
            <label for="add-cabang-kategori">Cabang <span class="text-danger">*</span></label>
            <select class="form-control" id="add-cabang-kategori" required>
              <option value="">Pilih Cabang</option>
              <?php foreach ($listCabang as $c) : ?>
                <option value="<?= $c['toko_cabang']; ?>"
                  <?= $_SESSION['user_cabang'] == $c['toko_cabang'] ? ' selected' : '' ?>>
                  <?= $c['toko_nama']; ?> - <?= $c['toko_kota']; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <small class="form-text text-muted">Akun ini akan hanya tersedia untuk cabang yang dipilih</small>
            <div class="invalid-feedback">
              Cabang harus diisi
            </div>
          </div>

          <div class="form-group">
            <label for="add-kategori">Kategori <span class="text-danger">*</span></label>
            <select class="form-control" id="add-kategori" required>
              <option value="">Pilih Kategori</option>
              <option value="aktiva">Aktiva</option>
              <option value="pasiva">Pasiva</option>
              <option value="modal">Modal</option>
              <option value="pendapatan">Pendapatan</option>
              <option value="beban">Beban</option>
            </select>
            <small class="form-text text-muted">Menentukan kelompok neraca / laba rugi untuk hierarki COA</small>
            <div class="invalid-feedback">
              Kategori harus diisi
            </div>
          </div>

          <div class="form-group" id="coa-level-type-wrap">
            <label for="add-coa-level-type">Jenis / Level akun <span class="text-danger">*</span></label>
            <select class="form-control" id="add-coa-level-type">
              <option value="1">Kepala Level 1 (akar COA, tanpa induk)</option>
              <option value="2">Kepala Level 2 (induk: level 1)</option>
              <option value="3">Kepala Level 3 (induk: level 2)</option>
              <option value="4" selected>Sub akun / rinci (induk: level 3)</option>
            </select>
            <small class="form-text text-muted">Level 1 tidak membutuhkan pilihan induk; level lain pilih induk di bawah.</small>
          </div>

          <div id="coa-hierarchy-wrap">
            <p class="text-muted small mb-2" id="coa-hierarchy-hint">Pilih induk sesuai level. Untuk sub akun, pilih rantai level 1 → 2 → 3.</p>
            <div class="form-group" id="wrap-coa-l1">
              <label for="add-coa-l1">Induk: Kepala Akun Level 1 <span class="text-danger">*</span></label>
              <select class="form-control" id="add-coa-l1">
                <option value="">Pilih kepala level 1</option>
              </select>
              <div class="invalid-feedback">Pilih induk level 1</div>
            </div>
            <div class="form-group" id="wrap-coa-l2">
              <label for="add-coa-l2">Induk: Kepala Akun Level 2 <span class="text-danger">*</span></label>
              <select class="form-control" id="add-coa-l2">
                <option value="">Pilih kepala level 2</option>
              </select>
              <div class="invalid-feedback">Pilih induk level 2</div>
            </div>
            <div class="form-group" id="wrap-coa-l3">
              <label for="add-coa-l3">Induk: Kepala Akun Level 3 <span class="text-danger">*</span></label>
              <select class="form-control" id="add-coa-l3">
                <option value="">Pilih kepala level 3</option>
              </select>
              <div class="invalid-feedback">Pilih induk level 3</div>
            </div>
          </div>

          <div class="form-group">
            <label for="add-name" id="add-name-label">Nama Sub Akun (COA) <span class="text-danger">*</span></label>
            <input type="text" name="name" id="add-name" class="form-control" placeholder="Contoh: KAS KECIL, BIAYA LOGISTIK" required>
            <small class="form-text text-muted" id="add-name-hint">Nama akun rinci di bawah kepala level 3</small>
            <div class="invalid-feedback">
              Nama sub akun harus diisi
            </div>
          </div>

          <div class="form-group">
            <label for="add-kode-akun">Kode Akun</label>
            <input type="text" name="kode_akun" id="add-kode-akun" class="form-control" placeholder="Contoh: 1-10001, 5-50001">
            <small class="form-text text-muted">Opsional: Kode akun untuk neraca</small>
          </div>

          <div class="form-group">
            <label for="add-tipe-akun">Tipe Akun <span class="text-danger">*</span></label>
            <select class="form-control" id="add-tipe-akun" required>
              <option value="">Pilih Tipe Akun</option>
              <option value="debit">Debit</option>
              <option value="kredit">Kredit</option>
            </select>
            <div class="invalid-feedback">
              Tipe akun harus diisi
            </div>
          </div>

          <div class="form-group">
            <label for="add-saldo">Saldo Awal</label>
            <input type="number" step="0.01" name="saldo" id="add-saldo" class="form-control" placeholder="0" value="0">
            <small class="form-text text-muted">Saldo awal untuk neraca</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="btn-close" data-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="btn-add">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script src="./dist/js/utils.js"></script>
<script>
  const base_url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '')
  const cabangList = <?php echo json_encode($listCabang); ?>;
  const defaultUserCabang = <?php echo json_encode(isset($_SESSION['user_cabang']) ? (string) $_SESSION['user_cabang'] : ''); ?>;

  /** null = urutan default API (kategori, nama); selain itu: kode_akun | name */
  let tableSortState = { field: null, dir: 'asc' }

  function updateSortHeaderUI() {
    $('#laba-kategori-table .sortable-th').each(function() {
      const f = $(this).data('sort')
      const $ind = $(this).find('.sort-indicator')
      if (tableSortState.field === f) {
        $ind.text(tableSortState.dir === 'asc' ? ' ▲' : ' ▼')
        $(this).attr('title', tableSortState.dir === 'asc' ? 'Urut naik (A→Z); klik untuk balik' : 'Urut turun (Z→A); klik untuk balik')
      } else {
        $ind.text('')
        $(this).attr('title', 'Klik untuk urutkan kolom ini (naik lalu turun)')
      }
    })
  }

  function getCoaTargetLevel() {
    const v = parseInt($('#add-coa-level-type').val(), 10)
    return (v >= 1 && v <= 4) ? v : 4
  }

  function applyCoaLevelMode() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    const t = getCoaTargetLevel()
    $('#wrap-coa-l1').toggle(t >= 2)
    $('#wrap-coa-l2').toggle(t >= 3)
    $('#wrap-coa-l3').toggle(t >= 4)
    const hint = $('#coa-hierarchy-hint')
    if (t === 1) {
      hint.text('Akun level 1 tidak memiliki induk. Isi nama dan simpan.')
    } else if (t === 2) {
      hint.text('Pilih satu kepala level 1 sebagai induk.')
    } else if (t === 3) {
      hint.text('Pilih rantai induk: level 1 lalu level 2.')
    } else {
      hint.text('Pilih rantai induk: level 1 → 2 → 3 untuk sub akun rinci.')
    }
    if (t === 1) {
      $('#add-name-label').html('Nama Kepala Akun Level 1 <span class="text-danger">*</span>')
      $('#add-name-hint').show().text('Akar COA pada kelompok kategori yang dipilih')
    } else if (t === 2) {
      $('#add-name-label').html('Nama Kepala Akun Level 2 <span class="text-danger">*</span>')
      $('#add-name-hint').show().text('Nama di bawah induk level 1')
    } else if (t === 3) {
      $('#add-name-label').html('Nama Kepala Akun Level 3 <span class="text-danger">*</span>')
      $('#add-name-hint').show().text('Nama di bawah induk level 2')
    } else {
      $('#add-name-label').html('Nama Sub Akun (COA) <span class="text-danger">*</span>')
      $('#add-name-hint').show().text('Nama akun rinci di bawah kepala level 3')
    }
  }

  function resetCoaSelect($sel, placeholder) {
    $sel.empty().append($('<option>', { value: '', text: placeholder }))
  }

  function loadCoaHierarchyLevel(parentId, level, targetSelectId, done) {
    const cab = $('#add-cabang-kategori').val()
    const kat = $('#add-kategori').val()
    const $sel = $(targetSelectId)
    const ph = level === 1
      ? 'Pilih kepala level 1'
      : (level === 2 ? 'Pilih kepala level 2' : 'Pilih kepala level 3')
    resetCoaSelect($sel, ph)
    if (!cab || !kat) {
      if (done) done()
      return
    }
    $.ajax({
      url: base_url + '/api/laba-kategori.php',
      method: 'GET',
      data: { for_hierarchy: 1, cabang: cab, kategori: kat, parent_id: parentId },
      dataType: 'json',
      success: (res) => {
        if (!res.success && res.message) {
          Swal.fire({ icon: 'warning', title: 'Hierarki COA', text: res.message })
        }
        if (res.success && res.data && res.data.length) {
          res.data.forEach((row) => {
            const code = row.kode_akun ? ' (' + row.kode_akun + ')' : ''
            $sel.append($('<option>', { value: row.id, text: row.name + code }))
          })
        }
        if (done) done()
      },
      error: (xhr) => {
        let msg = 'Gagal memuat hierarki COA'
        try {
          const j = JSON.parse(xhr.responseText)
          if (j.message) msg = j.message
        } catch (e) { /* ignore */ }
        Swal.fire({ icon: 'error', title: 'Hierarki COA', text: msg })
        if (done) done()
      }
    })
  }

  function refreshCoaLevel1() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    const t = getCoaTargetLevel()
    if (t < 2) return
    $('#add-coa-l2').val('')
    $('#add-coa-l3').val('')
    resetCoaSelect($('#add-coa-l2'), 'Pilih kepala level 2')
    resetCoaSelect($('#add-coa-l3'), 'Pilih kepala level 3')
    loadCoaHierarchyLevel(0, 1, '#add-coa-l1', null)
  }

  const deleteKategori = (id) => {
    Swal.fire({
      title: "Are you sure?",
      text: "You won't be able to revert this!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Yes, delete it!"
    }).then((result) => {
      if (result.value) {
        $.ajax({
          url: `${base_url}/api/laba-kategori.php?id=${id}`,
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

  const setCoaHierarchyEditable = (editable) => {
    $('#add-coa-level-type').prop('disabled', !editable)
    $('#add-coa-l1, #add-coa-l2, #add-coa-l3').prop('disabled', !editable)
  }

  const editKategori = (item) => {
    $('#form-type').val('edit')
    $('#modal-title').text('Edit Kategori Laba')
    $('#coa-level-type-wrap').show()
    $('#coa-hierarchy-wrap').show()
    setCoaHierarchyEditable(true)
    $('#add-name-hint').show()
    $('#add-id').val(item.id)
    $('#add-name').val(item.name)
    $('#add-kode-akun').val(item.kode_akun || '')
    $('#add-kategori').val(item.kategori)
    $('#add-tipe-akun').val(item.tipe_akun)
    $('#add-saldo').val(item.saldo_asli !== undefined && item.saldo_asli !== null ? item.saldo_asli : (item.saldo || 0))
    $('#add-cabang-kategori').val(item.cabang !== undefined && item.cabang !== null ? String(item.cabang) : '')

    const lvl = parseInt(item.level, 10)
    if (lvl >= 1 && lvl <= 4) {
      $('#add-coa-level-type').val(String(lvl))
    } else {
      $('#add-coa-level-type').val('4')
    }
    applyCoaLevelMode()

    const finishEditLoad = () => {
      $('#modal-add').modal('show')
    }

    const ajaxParams = { id: item.id, with_ancestors: 1 }
    if (item.cabang !== undefined && item.cabang !== null && item.cabang !== '') {
      ajaxParams.cabang = item.cabang
    }

    $.ajax({
      url: base_url + '/api/laba-kategori.php',
      method: 'GET',
      data: ajaxParams,
      dataType: 'json',
      success: (res) => {
        if (!res.success || !res.data) {
          finishEditLoad()
          return
        }
        const row = res.data
        const anc = row.ancestor_ids || []
        const level = parseInt(row.level, 10) || parseInt(item.level, 10) || 4
        $('#add-coa-level-type').val(String(level >= 1 && level <= 4 ? level : 4))
        applyCoaLevelMode()

        if (level === 1) {
          loadCoaHierarchyLevel(0, 1, '#add-coa-l1', finishEditLoad)
          return
        }
        loadCoaHierarchyLevel(0, 1, '#add-coa-l1', () => {
          if (anc[0]) $('#add-coa-l1').val(String(anc[0]))
          if (level === 2) {
            finishEditLoad()
            return
          }
          loadCoaHierarchyLevel(parseInt(anc[0], 10), 2, '#add-coa-l2', () => {
            if (anc[1]) $('#add-coa-l2').val(String(anc[1]))
            if (level === 3) {
              finishEditLoad()
              return
            }
            loadCoaHierarchyLevel(parseInt(anc[1], 10), 3, '#add-coa-l3', () => {
              if (anc[2]) $('#add-coa-l3').val(String(anc[2]))
              finishEditLoad()
            })
          })
        })
      },
      error: () => {
        finishEditLoad()
      }
    })
  }

  const getData = () => {
    // Get filter values
    const filterCabang = $('#filter-cabang').val() || '';
    const filterKategori = $('#filter-kategori').val() || '';
    const filterTipeAkun = $('#filter-tipe-akun').val() || '';
    const search = $('#search-input').val() || '';
    
    // Build query parameters
    const params = new URLSearchParams();
    
    // IMPORTANT: Only send cabang parameter if explicitly selected
    // If "Semua Cabang" is selected (empty string), don't send cabang parameter
    // This allows API to return all accounts (including cabang 0 and NULL)
    // Note: filterCabang can be '0' (string) for PCNU, which is a valid selection
    if (filterCabang !== '') {
      // User explicitly selected a cabang (including 0 for PCNU)
      params.append('cabang', filterCabang);
    }
    // If filterCabang is empty (Semua Cabang), don't send cabang parameter
    // This will make API return all accounts without cabang filter
    
    if (filterKategori) params.append('kategori', filterKategori);
    if (filterTipeAkun) params.append('tipe_akun', filterTipeAkun);
    if (search) params.append('search', search);
    if (tableSortState.field) {
      params.append('sort', tableSortState.field)
      params.append('order', tableSortState.dir)
    }

    const queryString = params.toString();
    const url = base_url + '/api/laba-kategori.php' + (queryString ? '?' + queryString : '');
    
    $.ajax({
      url: url,
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
      beforeSend: () => {
        $('#table-data').html('<tr><td class="text-center" colspan="9">Loading...</td></tr>')
      },
      success: (res) => {
        let html = ''
        if (res.success && res.data && res.data.length > 0) {
          res.data.forEach((item, index) => {
            const kategoriBadge = {
              'aktiva': 'badge-info',
              'pasiva': 'badge-warning',
              'modal': 'badge-success',
              'pendapatan': 'badge-primary',
              'beban': 'badge-danger'
            }
            const tipeBadge = {
              'debit': 'badge-primary',
              'kredit': 'badge-success'
            }
            const lvl = parseInt(item.level, 10) || 0
            const levelUi = {
              1: { badge: 'badge-dark', label: 'L1', title: 'Kepala Level 1 (akar)', border: '#343a40' },
              2: { badge: 'badge-primary', label: 'L2', title: 'Kepala Level 2', border: '#007bff' },
              3: { badge: 'badge-info', label: 'L3', title: 'Kepala Level 3', border: '#17a2b8' },
              4: { badge: 'badge-success', label: 'Sub', title: 'Sub akun / rinci', border: '#28a745' }
            }
            const lu = levelUi[lvl] || { badge: 'badge-secondary', label: '-', title: 'Level tidak diketahui', border: '#6c757d' }
            const namePad = lvl > 1 ? Math.min((lvl - 1) * 14, 48) : 0
            // Get cabang name
            let cabangName = '-';
            if (item.cabang === 0 || item.cabang === '0') {
              cabangName = 'PCNU (Default)';
            } else if (item.cabang) {
              // Try to get cabang name from list
              const cabangItem = cabangList.find(c => c.toko_cabang == item.cabang);
              if (cabangItem) {
                cabangName = cabangItem.toko_nama + ' - ' + cabangItem.toko_kota;
              } else {
                cabangName = 'Cabang ' + item.cabang;
              }
            }
            
            // Format saldo
            const saldo = parseFloat(item.saldo || 0)
            const saldoFormatted = saldo.toLocaleString('id-ID', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            })
            const saldoClass = saldo >= 0 ? 'text-success' : 'text-danger'
            const rowTip = (item.keterangan_hierarki || lu.title).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')

            html += `
              <tr style="border-left: 4px solid ${lu.border};" title="${rowTip}">
                <td class="text-center">${index + 1}</td>
                <td>${item.kode_akun || '-'}</td>
                <td class="text-center align-middle">
                  <span class="badge ${lu.badge} px-2 py-1" title="${lu.title}">${lu.label}</span>
                </td>
                <td style="${namePad ? 'padding-left: ' + namePad + 'px' : ''}">${item.name}</td>
                <td><span class="badge ${kategoriBadge[item.kategori] || 'badge-secondary'}">${item.kategori ? item.kategori.toUpperCase() : '-'}</span></td>
                <td><span class="badge ${tipeBadge[item.tipe_akun] || 'badge-secondary'}">${item.tipe_akun ? item.tipe_akun.toUpperCase() : '-'}</span></td>
                <td>${cabangName}</td>
                <td class="text-right ${saldoClass}"><strong>Rp ${saldoFormatted}</strong></td>
                <td class="text-center">
                  <button class="btn btn-warning btn-sm" onclick='editKategori(${JSON.stringify(item)})'><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-danger btn-sm" onclick="deleteKategori('${item.id}')"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            `
          })
        } else {
          html = '<tr><td class="text-center" colspan="9">Tidak ada data</td></tr>'
        }
        $('#table-data').html(html)
      },
      error: (err) => {
        console.log('Error loading data:', err)
        let errorMsg = 'Terjadi kesalahan saat memuat data'
        try {
          if (err.responseJSON && err.responseJSON.message) {
            errorMsg = err.responseJSON.message
          } else if (err.responseText) {
            const response = JSON.parse(err.responseText)
            errorMsg = response.message || errorMsg
          }
        } catch (e) {
          console.log('Error parsing response:', e)
        }
        $('#table-data').html(`<tr><td class="text-center" colspan="9">${errorMsg}</td></tr>`)
      }
    })
  }

  $('#btn-add-modal').click(() => {
    $('#form-type').val('create')
    $('#modal-title').text('Tambah Kategori Laba')
    $('#coa-level-type-wrap').show()
    $('#coa-hierarchy-wrap').show()
    setCoaHierarchyEditable(true)
    $('#form-add')[0].reset()
    $('#add-id').val('')
    $('#add-saldo').val('0')
    $('#add-name').removeClass('is-invalid')
    $('#add-kategori').removeClass('is-invalid')
    $('#add-tipe-akun').removeClass('is-invalid')
    $('#add-coa-l1, #add-coa-l2, #add-coa-l3').removeClass('is-invalid')
    $('#add-coa-level-type').val('4')
    if (defaultUserCabang !== '') {
      $('#add-cabang-kategori').val(defaultUserCabang)
    }
    resetCoaSelect($('#add-coa-l1'), 'Pilih kepala level 1')
    resetCoaSelect($('#add-coa-l2'), 'Pilih kepala level 2')
    resetCoaSelect($('#add-coa-l3'), 'Pilih kepala level 3')
    applyCoaLevelMode()
    setTimeout(() => { refreshCoaLevel1() }, 0)
  })

  $(document).on('change', '#add-coa-level-type', function() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    $('#add-coa-l1, #add-coa-l2, #add-coa-l3').removeClass('is-invalid')
    resetCoaSelect($('#add-coa-l1'), 'Pilih kepala level 1')
    resetCoaSelect($('#add-coa-l2'), 'Pilih kepala level 2')
    resetCoaSelect($('#add-coa-l3'), 'Pilih kepala level 3')
    applyCoaLevelMode()
    refreshCoaLevel1()
  })

  $(document).on('change', '#add-cabang-kategori, #add-kategori', function() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    refreshCoaLevel1()
  })

  $(document).on('change', '#add-coa-l1', function() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    const pid = $(this).val()
    $('#add-coa-l3').val('')
    resetCoaSelect($('#add-coa-l3'), 'Pilih kepala level 3')
    if (!pid) {
      resetCoaSelect($('#add-coa-l2'), 'Pilih kepala level 2')
      return
    }
    loadCoaHierarchyLevel(parseInt(pid, 10), 2, '#add-coa-l2', null)
  })

  $(document).on('change', '#add-coa-l2', function() {
    if (!['create', 'edit'].includes($('#form-type').val())) return
    const pid = $(this).val()
    if (!pid) {
      resetCoaSelect($('#add-coa-l3'), 'Pilih kepala level 3')
      return
    }
    loadCoaHierarchyLevel(parseInt(pid, 10), 3, '#add-coa-l3', null)
  })

  // Apply filter
  $('#btn-apply-filter').on('click', function() {
    getData()
  })

  // Reset filter
  $('#btn-reset-filter').on('click', function() {
    $('#filter-cabang').val('')
    $('#filter-kategori').val('')
    $('#filter-tipe-akun').val('')
    $('#search-input').val('')
    tableSortState = { field: null, dir: 'asc' }
    updateSortHeaderUI()
    getData()
  })

  $(document).on('click', '#laba-kategori-table .sortable-th', function() {
    const f = $(this).data('sort')
    if (!f) return
    if (tableSortState.field === f) {
      tableSortState.dir = tableSortState.dir === 'asc' ? 'desc' : 'asc'
    } else {
      tableSortState.field = f
      tableSortState.dir = 'asc'
    }
    updateSortHeaderUI()
    getData()
  })

  // Search on enter key
  $('#search-input').on('keypress', function(e) {
    if (e.which === 13) { // Enter key
      getData()
    }
  })

  // Auto search with delay (debounce)
  let searchTimeout
  $('#search-input').on('keyup', function() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      getData()
    }, 500) // Wait 500ms after user stops typing
  })

  // Filter change triggers reload
  $('#filter-cabang, #filter-kategori, #filter-tipe-akun').on('change', function() {
    getData()
  })

  $(document).ready(function() {
    updateSortHeaderUI()
    getData()

    // Button click triggers form submission
    $('#btn-add').on('click', function() {
      const name = $('#add-name')
      const kategori = $('#add-kategori')
      const tipe_akun = $('#add-tipe-akun')

      // Reset validation
      name.removeClass('is-invalid')
      kategori.removeClass('is-invalid')
      tipe_akun.removeClass('is-invalid')

      // Validation
      let isValid = true
      if (!name.val() || name.val().trim() === '') {
        name.addClass('is-invalid')
        isValid = false
      }
      if (!kategori.val() || kategori.val() === '') {
        kategori.addClass('is-invalid')
        isValid = false
      }
      if (!tipe_akun.val() || tipe_akun.val() === '') {
        tipe_akun.addClass('is-invalid')
        isValid = false
      }

      if (!isValid) {
        alert('Periksa kembali isian, field yang bertanda * wajib diisi')
        return
      }

      const cabangKategori = $('#add-cabang-kategori')
      if (!cabangKategori.val() || cabangKategori.val() === '') {
        cabangKategori.addClass('is-invalid')
        isValid = false
      }

      const isCreate = $('#form-type').val() === 'create'
      const isEdit = $('#form-type').val() === 'edit'
      const coaT = (isCreate || isEdit) ? getCoaTargetLevel() : 4
      if (isCreate || isEdit) {
        const l1 = $('#add-coa-l1')
        const l2 = $('#add-coa-l2')
        const l3 = $('#add-coa-l3')
        l1.removeClass('is-invalid')
        l2.removeClass('is-invalid')
        l3.removeClass('is-invalid')
        if (coaT >= 2 && !l1.val()) {
          l1.addClass('is-invalid')
          isValid = false
        }
        if (coaT >= 3 && !l2.val()) {
          l2.addClass('is-invalid')
          isValid = false
        }
        if (coaT >= 4 && !l3.val()) {
          l3.addClass('is-invalid')
          isValid = false
        }
      }

      if (!isValid) {
        alert('Periksa kembali isian, field yang bertanda * wajib diisi')
        return
      }

      const formData = {
        name: name.val().trim(),
        kode_akun: $('#add-kode-akun').val().trim(),
        kategori: kategori.val(),
        tipe_akun: tipe_akun.val(),
        saldo: parseFloat($('#add-saldo').val()) || 0,
        cabang: cabangKategori.val()
      }

      if (isCreate) {
        formData.coa_level = coaT
        if (coaT === 2) {
          formData.parent_id = $('#add-coa-l1').val()
        } else if (coaT === 3) {
          formData.parent_id = $('#add-coa-l2').val()
        } else if (coaT === 4) {
          formData.parent_id = $('#add-coa-l3').val()
        }
        return createKategori(formData)
      } else if (isEdit) {
        formData.id = $('#add-id').val()
        formData.coa_level = coaT
        if (coaT === 2) {
          formData.parent_id = $('#add-coa-l1').val()
        } else if (coaT === 3) {
          formData.parent_id = $('#add-coa-l2').val()
        } else if (coaT === 4) {
          formData.parent_id = $('#add-coa-l3').val()
        }
        return updateKategori(formData)
      } else {
        Swal.fire({
          icon: "error",
          title: "Oops...",
          text: "Terjadi kesalahan - tipe pengiriman tidak ditemukan !",
        });
      }
    })

    $('#btn-close').on('click', function() {
      $('#add-name').removeClass('is-invalid')
      $('#add-kategori').removeClass('is-invalid')
      $('#add-tipe-akun').removeClass('is-invalid')
      setCoaHierarchyEditable(true)
      $('#form-add')[0].reset()
      $('#btn-add').prop('disabled', false).html('Simpan')
    })
  })

  function createKategori(formData) {
    $.ajax({
      url: base_url + '/api/laba-kategori.php',
      method: 'POST',
      data: JSON.stringify(formData),
      contentType: 'application/json',
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
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
          $('#add-name').removeClass('is-invalid')
          $('#add-kategori').removeClass('is-invalid')
          $('#add-tipe-akun').removeClass('is-invalid')
          $('#form-add')[0].reset()
          $('#btn-close').click()
        } else {
          Swal.fire({
            icon: "error",
            title: "Error!",
            text: res.message || 'Terjadi Kesalahan'
          });
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
        } catch (e) {
          errorMsg = err.responseText || errorMsg;
        }
        Swal.fire({
          icon: "error",
          title: "Error!",
          text: errorMsg + errorDetails
        });
      }
    });
  }

  function updateKategori(formData) {
    $.ajax({
      url: base_url + '/api/laba-kategori.php',
      method: 'PUT',
      data: JSON.stringify(formData),
      contentType: 'application/json',
      headers: {
        'Accept': 'application/json',
      },
      dataType: 'json',
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
          $('#add-name').removeClass('is-invalid')
          $('#add-kategori').removeClass('is-invalid')
          $('#add-tipe-akun').removeClass('is-invalid')
          $('#form-add')[0].reset()
          $('#btn-close').click()
        } else {
          Swal.fire({
            icon: "error",
            title: "Error!",
            text: res.message || 'Terjadi Kesalahan'
          });
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
        } catch (e) {
          errorMsg = err.responseText || errorMsg;
        }
        Swal.fire({
          icon: "error",
          title: "Error!",
          text: errorMsg + errorDetails
        });
      }
    });
  }
</script>

<?php include '_footer.php'; ?>