<?php
include '_header.php';
include '_nav.php';
include '_sidebar.php';
require_once 'aksi/coa-link-mirror-lib.php';

if ($levelLogin != 'admin' && $levelLogin != 'super admin') {
	echo "<script>document.location.href='bo';</script>";
	exit;
}

coa_link_mirror_ensure_table($conn);
$listCabang = query('SELECT * FROM toko ORDER BY toko_cabang');
$cabangTokoDefault = 1;
foreach ($listCabang as $c) {
	$cb = (int) ($c['toko_cabang'] ?? 0);
	if ($cb > 0) {
		$cabangTokoDefault = $cb;
		break;
	}
}
?>

<style>
  .coa-pane { max-height: 62vh; overflow: auto; border: 1px solid #dee2e6; border-radius: .25rem; }
  .coa-pane table { margin-bottom: 0; }
  .coa-pane thead th { position: sticky; top: 0; z-index: 2; background: #343a40; color: #fff; }
  .coa-row { cursor: pointer; }
  .coa-row.selected { background: #cfe2ff !important; }
  .coa-row.linked { background: #e8f5e9; }
  .coa-row.linked.selected { background: #b6d4fe !important; }
  .coa-arrow-col { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem; min-height: 280px; }
  .coa-arrow-col .btn { width: 100%; max-width: 120px; }
  .coa-saldo { font-variant-numeric: tabular-nums; white-space: nowrap; }
  .coa-toolbar .form-control { height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem; }
</style>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-8">
          <h1><i class="fa fa-link"></i> Link COA ke Nugrosir</h1>
          <p class="text-muted mb-0">
            Kiri = akun canonical Grosir. Kanan = akun follower toko.
            Link satu arah: saldo Grosir ditampilkan pada toko; toko tidak mengubah saldo canonical.
          </p>
        </div>
        <div class="col-sm-4">
          <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="bo">Home</a></li>
            <li class="breadcrumb-item"><a href="laba-kategori">COA</a></li>
            <li class="breadcrumb-item active">Link Nugrosir</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="alert alert-info py-2 mb-3">
        <i class="fa fa-info-circle"></i>
        Pilih akun di panel kanan (toko), lalu klik <strong>← Link</strong> untuk menyambungkan ke Nugrosir (kode sama).
        Gunakan <strong>Unlink →</strong> untuk memutus. Panel kanan juga bisa tambah / edit / hapus / duplikat akun COA toko.
      </div>

      <div class="card card-outline card-primary">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
          <h3 class="card-title mb-0"><i class="fa fa-exchange-alt"></i> Panel Linking</h3>
          <div class="d-flex align-items-center" style="gap:.5rem;">
            <span class="badge badge-success" id="statLinked">0 terhubung</span>
            <button type="button" class="btn btn-sm btn-outline-info" id="btnSyncAll">
              <i class="fa fa-sync"></i> Sinkron Ulang
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="row">
            <!-- LEFT: Nugrosir -->
            <div class="col-lg-5">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0 text-primary"><i class="fa fa-building"></i> Nugrosir (cabang 0)</h5>
                <small class="text-muted" id="countLeft">0 akun</small>
              </div>
              <div class="form-row coa-toolbar mb-2">
                <div class="col">
                  <input type="text" class="form-control" id="qLeft" placeholder="Cari kode / nama Nugrosir…">
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-sm btn-primary" id="btnReloadLeft"><i class="fa fa-search"></i></button>
                </div>
              </div>
              <div class="coa-pane">
                <table class="table table-sm table-hover mb-0">
                  <thead>
                    <tr>
                      <th style="width:90px;">Kode</th>
                      <th>Nama</th>
                      <th class="text-right" style="width:110px;">Saldo</th>
                      <th style="width:70px;">Link</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyLeft">
                    <tr><td colspan="4" class="text-center text-muted py-4">Memuat…</td></tr>
                  </tbody>
                </table>
              </div>
              <small class="text-muted d-block mt-1" id="selLeftInfo">Belum dipilih</small>
            </div>

            <!-- CENTER: arrows -->
            <div class="col-lg-2">
              <div class="coa-arrow-col">
                <div class="text-center text-muted small mb-1">Arahkan link</div>
                <button type="button" class="btn btn-success" id="btnConnect" title="Hubungkan akun toko terpilih ke Nugrosir">
                  <i class="fa fa-arrow-left"></i> Link
                </button>
                <button type="button" class="btn btn-outline-danger" id="btnUnlink" title="Putus link akun terpilih">
                  Unlink <i class="fa fa-arrow-right"></i>
                </button>
                <hr class="w-100 my-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDupToNugrosir" title="Duplikat akun toko ke Nugrosir + opsional link">
                  <i class="fa fa-copy"></i> Duplikat →
                </button>
              </div>
            </div>

            <!-- RIGHT: Toko -->
            <div class="col-lg-5">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0 text-success"><i class="fa fa-store"></i> Akun Toko</h5>
                <small class="text-muted" id="countRight">0 akun</small>
              </div>
              <div class="form-row coa-toolbar mb-2">
                <div class="col-md-5">
                  <select class="form-control" id="cabangToko">
                    <?php foreach ($listCabang as $c) :
						$cb = (int) ($c['toko_cabang'] ?? 0);
						if ($cb === 0) {
							continue;
						}
						$label = trim(($c['toko_nama'] ?? '') . ' ' . ($c['toko_kota'] ?? ''));
						?>
                      <option value="<?= $cb; ?>" <?= $cb === $cabangTokoDefault ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($label !== '' ? $label : ('Cabang ' . $cb), ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col">
                  <input type="text" class="form-control" id="qRight" placeholder="Cari kode / nama toko…">
                </div>
                <div class="col-auto">
                  <button type="button" class="btn btn-sm btn-primary" id="btnReloadRight"><i class="fa fa-search"></i></button>
                </div>
              </div>
              <div class="btn-group btn-group-sm mb-2">
                <button type="button" class="btn btn-outline-success" id="btnAddToko"><i class="fa fa-plus"></i> Tambah</button>
                <button type="button" class="btn btn-outline-primary" id="btnEditToko"><i class="fa fa-edit"></i> Edit</button>
                <button type="button" class="btn btn-outline-secondary" id="btnDupToko"><i class="fa fa-copy"></i> Duplikat</button>
                <button type="button" class="btn btn-outline-danger" id="btnDelToko"><i class="fa fa-trash"></i> Hapus</button>
              </div>
              <div class="coa-pane">
                <table class="table table-sm table-hover mb-0">
                  <thead>
                    <tr>
                      <th style="width:90px;">Kode</th>
                      <th>Nama</th>
                      <th class="text-right" style="width:110px;">Saldo</th>
                      <th style="width:70px;">Link</th>
                    </tr>
                  </thead>
                  <tbody id="tbodyRight">
                    <tr><td colspan="4" class="text-center text-muted py-4">Memuat…</td></tr>
                  </tbody>
                </table>
              </div>
              <small class="text-muted d-block mt-1" id="selRightInfo">Belum dipilih</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>
</div>

<!-- Modal CRUD / Duplikat toko -->
<div class="modal fade" id="modalTokoAkun" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTokoTitle">Akun Toko</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tokoMode" value="create">
        <input type="hidden" id="tokoId" value="">
        <div class="form-group">
          <label>Cabang</label>
          <select class="form-control" id="tokoCabang">
            <?php foreach ($listCabang as $c) :
				$cb = (int) ($c['toko_cabang'] ?? 0);
				if ($cb === 0) {
					continue;
				}
				$label = trim(($c['toko_nama'] ?? '') . ' ' . ($c['toko_kota'] ?? ''));
				?>
              <option value="<?= $cb; ?>"><?= htmlspecialchars($label !== '' ? $label : ('Cabang ' . $cb), ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Kode Akun</label>
          <input type="text" class="form-control" id="tokoKode" maxlength="50">
        </div>
        <div class="form-group">
          <label>Nama Akun</label>
          <input type="text" class="form-control" id="tokoNama" maxlength="150">
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Kategori</label>
            <select class="form-control" id="tokoKategori">
              <option value="aktiva">Aktiva</option>
              <option value="pasiva">Pasiva</option>
              <option value="modal">Modal</option>
              <option value="pendapatan">Pendapatan</option>
              <option value="beban">Beban</option>
            </select>
          </div>
          <div class="form-group col-md-6">
            <label>Tipe</label>
            <select class="form-control" id="tokoTipe">
              <option value="debit">Debit</option>
              <option value="kredit">Kredit</option>
            </select>
          </div>
        </div>
        <div class="form-group" id="wrapSaldoCreate">
          <label>Saldo awal</label>
          <input type="number" class="form-control" id="tokoSaldo" value="0" step="0.01">
        </div>
        <div class="form-group d-none" id="wrapDupTarget">
          <label>Duplikat ke</label>
          <select class="form-control" id="tokoDupTarget">
            <option value="same">Cabang toko yang sama (kode baru)</option>
            <option value="0">Nugrosir (cabang 0)</option>
          </select>
          <div class="custom-control custom-checkbox mt-2">
            <input type="checkbox" class="custom-control-input" id="tokoAlsoLink" checked>
            <label class="custom-control-label" for="tokoAlsoLink">Langsung link ke Nugrosir (jika target Nugrosir)</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary" id="btnSaveToko">Simpan</button>
      </div>
    </div>
  </div>
</div>

<?php include '_footer.php'; ?>

<script>
(function () {
  var leftItems = [];
  var rightItems = [];
  var selLeftId = null;
  var selRightId = null;

  function esc(s) {
    return $('<div>').text(s == null ? '' : String(s)).html();
  }
  function fmt(n) {
    return Number(n || 0).toLocaleString('id-ID');
  }
  function toastOk(msg) {
    if (window.Swal) Swal.fire({ icon: 'success', title: 'OK', text: msg || '', timer: 1400, showConfirmButton: false });
    else alert(msg || 'OK');
  }
  function toastErr(msg) {
    if (window.Swal) Swal.fire('Gagal', msg || 'Terjadi kesalahan', 'error');
    else alert(msg || 'Gagal');
  }

  function findItem(list, id) {
    for (var i = 0; i < list.length; i++) {
      if (Number(list[i].id) === Number(id)) return list[i];
    }
    return null;
  }

  function renderPane(side) {
    var items = side === 'left' ? leftItems : rightItems;
    var $tb = side === 'left' ? $('#tbodyLeft') : $('#tbodyRight');
    var selId = side === 'left' ? selLeftId : selRightId;
    $tb.empty();
    if (!items.length) {
      $tb.append('<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada akun.</td></tr>');
    } else {
      items.forEach(function (it) {
        var cls = 'coa-row' + (it.linked ? ' linked' : '') + (Number(it.id) === Number(selId) ? ' selected' : '');
        var badge = it.linked
          ? '<span class="badge badge-success">ON</span>'
          : '<span class="badge badge-secondary">—</span>';
        $tb.append(
          '<tr class="' + cls + '" data-id="' + it.id + '">' +
            '<td><code>' + esc(it.kode_akun) + '</code></td>' +
            '<td>' + esc(it.name) + '</td>' +
            '<td class="text-right coa-saldo">' + fmt(it.saldo) + '</td>' +
            '<td class="text-center">' + badge + '</td>' +
          '</tr>'
        );
      });
    }
    $('#' + (side === 'left' ? 'countLeft' : 'countRight')).text(items.length + ' akun');
    updateSelInfo();
  }

  function updateSelInfo() {
    var L = findItem(leftItems, selLeftId);
    var R = findItem(rightItems, selRightId);
    $('#selLeftInfo').html(L
      ? 'Terpilih: <code>' + esc(L.kode_akun) + '</code> ' + esc(L.name) + (L.linked ? ' <span class="badge badge-success">linked</span>' : '')
      : 'Belum dipilih');
    $('#selRightInfo').html(R
      ? 'Terpilih: <code>' + esc(R.kode_akun) + '</code> ' + esc(R.name) + (R.linked ? ' <span class="badge badge-success">linked</span>' : '')
      : 'Belum dipilih');
    var linkedCount = rightItems.filter(function (x) { return x.linked; }).length;
    $('#statLinked').text(linkedCount + ' terhubung (toko ini)');
  }

  function loadPanels() {
    return $.getJSON('api/coa-link-mirror.php', {
      action: 'panels',
      cabang_toko: $('#cabangToko').val(),
      q_left: $('#qLeft').val(),
      q_right: $('#qRight').val()
    }).done(function (res) {
      if (!res || !res.ok) {
        toastErr((res && res.message) || 'Gagal memuat');
        return;
      }
      leftItems = res.left || [];
      rightItems = res.right || [];
      if (selLeftId && !findItem(leftItems, selLeftId)) selLeftId = null;
      if (selRightId && !findItem(rightItems, selRightId)) selRightId = null;
      renderPane('left');
      renderPane('right');
    }).fail(function () {
      toastErr('Koneksi bermasalah');
    });
  }

  function postAction(action, data) {
    return $.ajax({
      url: 'api/coa-link-mirror.php?action=' + encodeURIComponent(action),
      method: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify(data || {})
    });
  }

  $('#tbodyLeft').on('click', 'tr.coa-row', function () {
    selLeftId = Number($(this).data('id'));
    renderPane('left');
  });
  $('#tbodyRight').on('click', 'tr.coa-row', function () {
    selRightId = Number($(this).data('id'));
    renderPane('right');
    // Auto-highlight matching kode di Nugrosir bila ada
    var R = findItem(rightItems, selRightId);
    if (R) {
      var match = leftItems.find(function (x) { return x.kode_akun === R.kode_akun; });
      if (match) {
        selLeftId = match.id;
        renderPane('left');
      }
    }
  });

  $('#btnReloadLeft, #btnReloadRight').on('click', loadPanels);
  $('#cabangToko').on('change', function () {
    selRightId = null;
    loadPanels();
  });
  $('#qLeft, #qRight').on('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); loadPanels(); }
  });

  $('#btnConnect').on('click', function () {
    var R = findItem(rightItems, selRightId);
    var L = findItem(leftItems, selLeftId);
    if (!R || !L) {
      toastErr('Pilih akun canonical Grosir dan akun follower toko');
      return;
    }
    if (L.kode_akun !== R.kode_akun) {
      toastErr('Kode akun Grosir dan toko wajib sama persis');
      return;
    }
    doConnect(L.id, R.id);
  });

  function doConnect(grosirId, tokoId) {
    var $btn = $('#btnConnect').prop('disabled', true);
    postAction('connect', { grosir_akun_id: grosirId, toko_akun_id: tokoId })
      .done(function (res) {
        if (res && res.ok) { toastOk(res.message); loadPanels(); }
        else toastErr((res && res.message) || 'Gagal link');
      })
      .fail(function () { toastErr('Koneksi bermasalah'); })
      .always(function () { $btn.prop('disabled', false); });
  }

  $('#btnUnlink').on('click', function () {
    var R = findItem(rightItems, selRightId);
    var L = findItem(leftItems, selLeftId);
    var payload = null;
    if (R && R.linked && R.link_id) {
      payload = { link_id: R.link_id };
    } else if (L && L.linked && L.link_id) {
      payload = { link_id: L.link_id };
    } else if (R && R.linked) {
      payload = { kode_akun: R.kode_akun, cabang_sumber: R.cabang };
    } else {
      toastErr('Pilih akun yang sudah ter-link');
      return;
    }
    var $btn = $('#btnUnlink').prop('disabled', true);
    postAction('unlink', payload)
      .done(function (res) {
        if (res && res.ok) { toastOk(res.message); loadPanels(); }
        else toastErr((res && res.message) || 'Gagal unlink');
      })
      .fail(function () { toastErr('Koneksi bermasalah'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  $('#btnSyncAll').on('click', function () {
    var $btn = $(this).prop('disabled', true);
    $.getJSON('api/coa-link-mirror.php', { action: 'sync' })
      .done(function (res) {
        if (res && res.ok) { toastOk(res.message); loadPanels(); }
        else toastErr((res && res.message) || 'Gagal sync');
      })
      .fail(function () { toastErr('Koneksi bermasalah'); })
      .always(function () { $btn.prop('disabled', false); });
  });

  function openModal(mode, fill) {
    $('#tokoMode').val(mode);
    $('#wrapSaldoCreate').toggleClass('d-none', mode !== 'create');
    $('#wrapDupTarget').toggleClass('d-none', mode !== 'duplicate' && mode !== 'dup_nugrosir');
    $('#tokoCabang').prop('disabled', mode === 'edit' || mode === 'duplicate' || mode === 'dup_nugrosir');
    var titles = {
      create: 'Tambah Akun Toko',
      edit: 'Edit Akun Toko',
      duplicate: 'Duplikat Akun Toko',
      dup_nugrosir: 'Duplikat ke Nugrosir'
    };
    $('#modalTokoTitle').text(titles[mode] || 'Akun Toko');
    fill = fill || {};
    $('#tokoId').val(fill.id || '');
    $('#tokoCabang').val(fill.cabang || $('#cabangToko').val());
    $('#tokoKode').val(fill.kode_akun || '');
    $('#tokoNama').val(fill.name || '');
    $('#tokoKategori').val((fill.kategori || 'aktiva').toLowerCase());
    $('#tokoTipe').val((fill.tipe_akun || 'debit').toLowerCase());
    $('#tokoSaldo').val(fill.saldo != null ? fill.saldo : 0);
    if (mode === 'dup_nugrosir') {
      $('#tokoDupTarget').val('0');
      $('#tokoAlsoLink').prop('checked', true);
    } else if (mode === 'duplicate') {
      $('#tokoDupTarget').val('same');
      if (fill.kode_akun) $('#tokoKode').val(fill.kode_akun + '-COPY');
      if (fill.name) $('#tokoNama').val(fill.name + ' (copy)');
    }
    $('#modalTokoAkun').modal('show');
  }

  $('#btnAddToko').on('click', function () {
    openModal('create', { cabang: $('#cabangToko').val() });
  });

  $('#btnEditToko').on('click', function () {
    var R = findItem(rightItems, selRightId);
    if (!R) { toastErr('Pilih akun toko dulu'); return; }
    openModal('edit', R);
  });

  $('#btnDupToko').on('click', function () {
    var R = findItem(rightItems, selRightId);
    if (!R) { toastErr('Pilih akun toko dulu'); return; }
    openModal('duplicate', R);
  });

  $('#btnDupToNugrosir').on('click', function () {
    var R = findItem(rightItems, selRightId);
    if (!R) { toastErr('Pilih akun toko di kanan dulu'); return; }
    openModal('dup_nugrosir', R);
  });

  $('#btnDelToko').on('click', function () {
    var R = findItem(rightItems, selRightId);
    if (!R) { toastErr('Pilih akun toko dulu'); return; }
    var go = function () {
      postAction('delete_toko', { id: R.id }).done(function (res) {
        if (res && res.ok) { toastOk(res.message); selRightId = null; loadPanels(); }
        else toastErr((res && res.message) || 'Gagal hapus');
      }).fail(function () { toastErr('Koneksi bermasalah'); });
    };
    if (window.Swal) {
      Swal.fire({
        title: 'Hapus akun?',
        text: R.kode_akun + ' — ' + R.name,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Hapus',
        confirmButtonColor: '#d33'
      }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (confirm('Hapus ' + R.kode_akun + '?')) go();
  });

  $('#btnSaveToko').on('click', function () {
    var mode = $('#tokoMode').val();
    var $btn = $(this).prop('disabled', true);
    var done = function (res) {
      $btn.prop('disabled', false);
      if (res && res.ok) {
        $('#modalTokoAkun').modal('hide');
        toastOk(res.message);
        loadPanels();
      } else toastErr((res && res.message) || 'Gagal simpan');
    };
    var fail = function () { $btn.prop('disabled', false); toastErr('Koneksi bermasalah'); };

    if (mode === 'create') {
      postAction('create_toko', {
        cabang: Number($('#tokoCabang').val()),
        kode_akun: $('#tokoKode').val(),
        name: $('#tokoNama').val(),
        kategori: $('#tokoKategori').val(),
        tipe_akun: $('#tokoTipe').val(),
        saldo: Number($('#tokoSaldo').val() || 0)
      }).done(done).fail(fail);
      return;
    }
    if (mode === 'edit') {
      postAction('update_toko', {
        id: Number($('#tokoId').val()),
        kode_akun: $('#tokoKode').val(),
        name: $('#tokoNama').val(),
        kategori: $('#tokoKategori').val(),
        tipe_akun: $('#tokoTipe').val()
      }).done(done).fail(fail);
      return;
    }
    if (mode === 'duplicate' || mode === 'dup_nugrosir') {
      var targetSel = $('#tokoDupTarget').val();
      var targetCabang = targetSel === '0' ? 0 : Number($('#tokoCabang').val());
      postAction('duplicate', {
        akun_id: Number($('#tokoId').val()),
        target_cabang: targetCabang,
        kode_akun: $('#tokoKode').val(),
        name: $('#tokoNama').val(),
        also_link: targetCabang === 0 && $('#tokoAlsoLink').is(':checked')
      }).done(done).fail(fail);
      return;
    }
    $btn.prop('disabled', false);
  });

  loadPanels();
})();
</script>
