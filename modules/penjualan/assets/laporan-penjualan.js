/* Laporan Penjualan — pagination + export XLS bertahap dengan progress */
(function (global) {
  function boot(CFG) {
    var API_URL = CFG.apiUrl;
    var EXPORT_PDF_URL = CFG.exportPdfUrl;
    var PER_PAGE = 100;
    var currentMode = 'transaksi';
    var pages = { transaksi: 1, detail: 1, barang: 1, customer: 1 };
    var cachedData = { transaksi: null, detail: null, barang: null, customer: null };
    var cachedSummary = null;
    var exportAbort = false;

    function fmtNum(n) { return new Intl.NumberFormat('id-ID').format(n || 0); }
    function fmtRp(n) { return 'Rp ' + fmtNum(Math.round(n || 0)); }
    function fmtQty(n) {
      var v = parseFloat(n) || 0;
      return Math.abs(v - Math.round(v)) < 0.0001 ? fmtNum(v) : fmtNum(v.toFixed(2));
    }
    function fmtMargin(n) {
      var v = parseFloat(n);
      if (isNaN(v)) return '-';
      var cls = v < 0 ? 'text-danger' : (v < 5 ? 'text-warning' : 'text-success');
      return '<span class="' + cls + ' font-weight-bold">' + fmtNum(v.toFixed(1)) + '%</span>';
    }
    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function fmtDate(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    function filterBase() {
      return {
        dari: $('#dari').val(),
        sampai: $('#sampai').val(),
        customer_id: $('#customer_id').val(),
        status_bayar: $('#status_bayar').val(),
        metode_bayar: $('#metode_bayar').val(),
        kasir_id: $('#kasir_id').val()
      };
    }
    function filterQs() { return $.param(filterBase()); }

    function daysInRange(dari, sampai) {
      var a = new Date(dari + 'T00:00:00');
      var b = new Date(sampai + 'T00:00:00');
      var out = [];
      for (var t = a.getTime(); t <= b.getTime(); t += 86400000) {
        out.push(fmtDate(new Date(t)));
      }
      return out;
    }

    function periodeOk() {
      var dari = $('#dari').val(), sampai = $('#sampai').val();
      if (!dari || !sampai) return false;
      var days = daysInRange(dari, sampai).length;
      if (days > 31) {
        alert('Periode maksimal 31 hari. Saat ini ' + days + ' hari. Persempit rentang tanggal.');
        return false;
      }
      return true;
    }

    function updateSummary(s) {
      if (!s) return;
      cachedSummary = s;
      $('#summaryCards').show();
      $('#sumTrx').text(fmtNum(s.jumlah_transaksi));
      $('#sumTotal').text(fmtRp(s.total_penjualan));
      $('#sumLunas').text(fmtRp(s.total_lunas));
      $('#sumPiutang').text(fmtRp(s.total_piutang));
      $('#sumLaba').text(s.total_laba_kotor ? fmtRp(s.total_laba_kotor) : '-');
      $('#sumMargin').text(s.margin_persen ? (fmtNum(s.margin_persen) + '%') : '-');
    }

    function badgeStatus(label) {
      var cls = label === 'Lunas' ? 'success' : (label === 'Piutang Lunas' ? 'info' : 'warning');
      return '<span class="badge badge-' + cls + '">' + label + '</span>';
    }

    function bodySel(mode) {
      if (mode === 'detail') return '#bodyDetail';
      if (mode === 'barang') return '#bodyBarang';
      if (mode === 'customer') return '#bodyCustomer';
      return '#bodyTransaksi';
    }
    function pagerSel(mode) {
      if (mode === 'detail') return '#pagerDetail';
      if (mode === 'transaksi') return '#pagerTransaksi';
      return null;
    }

    function renderPager(mode, pg) {
      var sel = pagerSel(mode);
      if (!sel) return;
      if (!pg) { $(sel).empty().hide(); return; }
      var page = pg.page || 1;
      var totalPages = pg.total_pages || (pg.has_more ? page + 1 : page);
      var total = pg.total;
      var html = '<div class="text-muted small">';
      if (total != null) html += 'Total <strong>' + fmtNum(total) + '</strong> baris · ';
      html += 'Halaman <strong>' + page + '</strong>' + (pg.total_pages ? ' / ' + pg.total_pages : '') + '</div>';
      html += '<div class="btn-group">';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-page" data-mode="' + mode + '" data-page="1" ' + (page <= 1 ? 'disabled' : '') + '>&laquo;</button>';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-page" data-mode="' + mode + '" data-page="' + (page - 1) + '" ' + (page <= 1 ? 'disabled' : '') + '>Prev</button>';
      html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-page" data-mode="' + mode + '" data-page="' + (page + 1) + '" ' + ((pg.total_pages ? page >= totalPages : !pg.has_more) ? 'disabled' : '') + '>Next</button>';
      if (pg.total_pages) {
        html += '<button type="button" class="btn btn-sm btn-outline-secondary btn-page" data-mode="' + mode + '" data-page="' + totalPages + '" ' + (page >= totalPages ? 'disabled' : '') + '>&raquo;</button>';
      }
      html += '</div>';
      $(sel).html(html).css('display', 'flex').show();
    }

    function renderTransaksi(rows) {
      if (!rows || !rows.length) {
        $('#bodyTransaksi').html('<tr><td colspan="15" class="text-center">Tidak ada data</td></tr>');
        return;
      }
      var html = '';
      rows.forEach(function (r) {
        html += '<tr>' +
          '<td>' + r.no + '</td>' +
          '<td>' + esc(r.penjualan_invoice) + '</td>' +
          '<td>' + esc(r.invoice_tgl) + '</td>' +
          '<td>' + esc(r.customer_nama) + '</td>' +
          '<td>' + esc(r.kasir_nama) + '</td>' +
          '<td>' + esc(r.metode_bayar) + '</td>' +
          '<td class="text-center">-</td><td class="text-right">-</td>' +
          '<td class="text-right">' + fmtRp(r.invoice_sub_total) + '</td>' +
          '<td class="text-right">' + fmtRp(r.invoice_diskon) + '</td>' +
          '<td class="text-right">' + fmtRp(r.invoice_ongkir) + '</td>' +
          '<td class="text-right">' + fmtRp(r.invoice_bayar) + '</td>' +
          '<td class="text-right">' + fmtRp(r.sisa_piutang) + '</td>' +
          '<td>' + badgeStatus(r.status_bayar) + '</td>' +
          '<td><a href="penjualan-zoom?no=' + btoa(String(r.invoice_id)) + '" class="btn btn-xs btn-info" target="_blank"><i class="fa fa-eye"></i></a></td>' +
          '</tr>';
      });
      $('#bodyTransaksi').html(html);
    }

    function renderDetail(rows) {
      if (!rows || !rows.length) {
        $('#bodyDetail').html('<tr><td colspan="17" class="text-center">Tidak ada data</td></tr>');
        return;
      }
      var html = '', total = 0, modal = 0, laba = 0;
      rows.forEach(function (r) {
        total += r.subtotal || 0;
        modal += r.modal || 0;
        laba += r.laba_kotor || 0;
        html += '<tr>' +
          '<td>' + r.no + '</td><td>' + esc(r.penjualan_invoice) + '</td><td>' + esc(r.invoice_tgl) + '</td>' +
          '<td>' + esc(r.barang_kode || '-') + '</td><td>' + esc(r.barang_nama) + '</td><td>' + esc(r.kategori_nama) + '</td>' +
          '<td>' + esc(r.satuan_nama) + '</td><td class="text-right">' + fmtQty(r.barang_qty) + '</td>' +
          '<td class="text-right">' + fmtRp(r.harga_beli) + '</td>' +
          '<td class="text-right">' + fmtRp(r.keranjang_harga) + '</td>' +
          '<td class="text-right">' + fmtRp(r.modal) + '</td>' +
          '<td class="text-right">' + fmtRp(r.subtotal) + '</td>' +
          '<td class="text-right">' + fmtRp(r.laba_kotor) + '</td>' +
          '<td class="text-right">' + fmtMargin(r.margin_persen) + '</td>' +
          '<td>' + esc(r.customer_nama) + '</td>' +
          '<td>' + esc(r.metode_bayar) + '</td><td>' + badgeStatus(r.status_bayar) + '</td></tr>';
      });
      var marginTotal = modal > 0 ? ((laba / modal) * 100) : 0;
      html += '<tr class="font-weight-bold bg-light"><td colspan="10" class="text-right">TOTAL HALAMAN</td>' +
        '<td class="text-right">' + fmtRp(modal) + '</td>' +
        '<td class="text-right">' + fmtRp(total) + '</td>' +
        '<td class="text-right">' + fmtRp(laba) + '</td>' +
        '<td class="text-right">' + fmtMargin(marginTotal) + '</td><td colspan="3"></td></tr>';
      $('#bodyDetail').html(html);
    }

    function renderBarang(rows) {
      if (!rows || !rows.length) {
        $('#bodyBarang').html('<tr><td colspan="13" class="text-center">Tidak ada data</td></tr>');
        return;
      }
      var html = '', tQty = 0, tModal = 0, tJual = 0, tLaba = 0;
      rows.forEach(function (r) {
        tQty += r.total_qty || 0; tModal += r.total_modal || 0; tJual += r.total_penjualan || 0; tLaba += r.total_laba || 0;
        html += '<tr>' +
          '<td>' + r.no + '</td><td>' + esc(r.barang_kode) + '</td><td>' + esc(r.barang_nama) + '</td>' +
          '<td>' + esc(r.kategori_nama) + '</td><td>' + esc(r.satuan_nama) + '</td>' +
          '<td class="text-center">' + fmtNum(r.jumlah_transaksi) + '</td>' +
          '<td class="text-right">' + fmtQty(r.total_qty) + '</td>' +
          '<td class="text-right">' + fmtRp(r.harga_beli_avg) + '</td>' +
          '<td class="text-right">' + fmtRp(r.harga_jual_avg) + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_modal) + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_penjualan) + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_laba) + '</td>' +
          '<td class="text-right">' + fmtMargin(r.margin_persen) + '</td></tr>';
      });
      html += '<tr class="font-weight-bold bg-light"><td colspan="6" class="text-right">TOTAL</td>' +
        '<td class="text-right">' + fmtQty(tQty) + '</td><td></td><td></td>' +
        '<td class="text-right">' + fmtRp(tModal) + '</td>' +
        '<td class="text-right">' + fmtRp(tJual) + '</td>' +
        '<td class="text-right">' + fmtRp(tLaba) + '</td>' +
        '<td class="text-right">' + fmtMargin(tModal > 0 ? (tLaba / tModal * 100) : 0) + '</td></tr>';
      $('#bodyBarang').html(html);
    }

    function renderCustomer(rows) {
      if (!rows || !rows.length) {
        $('#bodyCustomer').html('<tr><td colspan="8" class="text-center">Tidak ada data</td></tr>');
        return;
      }
      var html = '', tJual = 0, tLunas = 0, tPiut = 0, tSisa = 0;
      rows.forEach(function (r) {
        tJual += r.total_penjualan || 0; tLunas += r.total_lunas || 0; tPiut += r.total_piutang || 0; tSisa += r.sisa_piutang || 0;
        html += '<tr><td>' + r.no + '</td><td>' + esc(r.customer_nama) + '</td>' +
          '<td class="text-center">' + fmtNum(r.jumlah_transaksi) + '</td>' +
          '<td class="text-right">' + (r.total_qty != null ? fmtQty(r.total_qty) : '-') + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_penjualan) + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_lunas) + '</td>' +
          '<td class="text-right">' + fmtRp(r.total_piutang) + '</td>' +
          '<td class="text-right">' + fmtRp(r.sisa_piutang) + '</td></tr>';
      });
      html += '<tr class="font-weight-bold bg-light"><td colspan="4" class="text-right">TOTAL</td>' +
        '<td class="text-right">' + fmtRp(tJual) + '</td><td class="text-right">' + fmtRp(tLunas) + '</td>' +
        '<td class="text-right">' + fmtRp(tPiut) + '</td><td class="text-right">' + fmtRp(tSisa) + '</td></tr>';
      $('#bodyCustomer').html(html);
    }

    function renderMode(mode, rows) {
      if (mode === 'transaksi') renderTransaksi(rows);
      else if (mode === 'detail') renderDetail(rows);
      else if (mode === 'barang') renderBarang(rows);
      else renderCustomer(rows);
    }

    function apiGet(data) {
      return $.ajax({ url: API_URL, method: 'GET', dataType: 'json', timeout: 60000, data: data });
    }

    function loadMode(mode, force) {
      currentMode = mode;
      if (!periodeOk()) return;
      if (!force && cachedData[mode] && (mode === 'barang' || mode === 'customer')) {
        updateSummary(cachedSummary);
        renderMode(mode, cachedData[mode]);
        return;
      }
      var sel = bodySel(mode);
      $(sel).html('<tr><td colspan="17" class="text-center"><i class="fa fa-spinner fa-spin"></i> Memuat...</td></tr>');
      var payload = Object.assign({}, filterBase(), {
        mode: mode,
        page: pages[mode] || 1,
        per_page: PER_PAGE
      });
      if (cachedSummary && mode !== 'transaksi') payload.skip_summary = 1;

      apiGet(payload).done(function (res) {
        if (!res.success) {
          $(sel).html('<tr><td colspan="17" class="text-center text-danger">' + esc(res.message || 'Gagal memuat') + '</td></tr>');
          return;
        }
        if (res.summary) { cachedSummary = res.summary; updateSummary(res.summary); }
        else if (cachedSummary) updateSummary(cachedSummary);
        cachedData[mode] = res.data;
        renderMode(mode, res.data);
        renderPager(mode, res.pagination || null);
      }).fail(function (xhr) {
        var msg = 'Gagal memuat data';
        if (xhr.status === 401) msg = 'Sesi habis. Silakan login ulang.';
        else if (xhr.status === 504 || xhr.status === 502 || xhr.status === 0)
          msg = 'Server timeout. Coba halaman lain atau periode lebih pendek.';
        else if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
        else if (xhr.status) msg = 'Gagal memuat data (HTTP ' + xhr.status + ')';
        $(sel).html('<tr><td colspan="17" class="text-center text-danger">' + esc(msg) + '</td></tr>');
      });
    }

    function loadAll() {
      if (!periodeOk()) return;
      cachedData = { transaksi: null, detail: null, barang: null, customer: null };
      pages = { transaksi: 1, detail: 1, barang: 1, customer: 1 };
      loadMode(currentMode, true);
    }

    $(document).on('click', '.btn-page', function () {
      var mode = $(this).data('mode');
      var page = parseInt($(this).data('page'), 10) || 1;
      if (page < 1) return;
      pages[mode] = page;
      cachedData[mode] = null;
      loadMode(mode, true);
    });

    function setExportProgress(pct, status, detail) {
      pct = Math.max(0, Math.min(100, Math.round(pct)));
      $('#exportBar').css('width', pct + '%').text(pct + '%');
      if (status) $('#exportStatus').text(status);
      if (detail != null) $('#exportDetail').text(detail);
    }

    function downloadXls(filename, title, headers, rows) {
      var html = '\uFEFF<html><head><meta charset="UTF-8"></head><body>';
      html += '<h2>' + esc(title) + '</h2>';
      html += '<p>' + esc($('#dari').val() + ' s/d ' + $('#sampai').val()) + '</p>';
      html += '<p>Total baris: ' + rows.length + '</p>';
      html += '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
      headers.forEach(function (h) { html += '<th>' + esc(h) + '</th>'; });
      html += '</tr></thead><tbody>';
      rows.forEach(function (line) {
        html += '<tr>';
        line.forEach(function (cell) { html += '<td>' + esc(cell) + '</td>'; });
        html += '</tr>';
      });
      html += '</tbody></table></body></html>';
      var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = filename + '.xls';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1000);
    }

    function fetchAllPagesForRange(mode, dari, sampai) {
      var all = [];
      var page = 1;
      function next() {
        if (exportAbort) return $.Deferred().reject(new Error('Dibatalkan')).promise();
        return apiGet(Object.assign({}, filterBase(), {
          dari: dari, sampai: sampai, mode: mode, page: page, per_page: 500, skip_summary: 1, skip_count: 1
        })).then(function (res) {
          if (!res.success) return $.Deferred().reject(new Error(res.message || 'Gagal')).promise();
          var rows = res.data || [];
          all = all.concat(rows);
          if (!rows.length || rows.length < 500 || !res.pagination || !res.pagination.has_more) return all;
          page += 1;
          if (page > 200) return all;
          return next();
        });
      }
      return next();
    }

    function exportExcelWithProgress(mode) {
      if (!periodeOk()) return;
      exportAbort = false;
      $('#btnCancelExport').hide();
      $('#exportSpin').show();
      $('#modalExportProgress').modal('show');
      setExportProgress(1, 'Menyiapkan export...', '');

      var dari = $('#dari').val();
      var sampai = $('#sampai').val();
      var days = daysInRange(dari, sampai);
      var allRows = [];
      var i = 0;

      function finishOk(title, headers, mapped, fname) {
        setExportProgress(100, 'Menyusun file Excel...', fmtNum(mapped.length) + ' baris');
        downloadXls(fname, title, headers, mapped);
        $('#exportSpin').hide();
        setExportProgress(100, 'Selesai! File diunduh.', fmtNum(mapped.length) + ' baris');
        $('#btnCancelExport').show();
      }
      function fail(err) {
        $('#exportSpin').hide();
        setExportProgress(0, 'Export gagal', (err && err.message) ? err.message : String(err));
        $('#btnCancelExport').show();
      }

      if (mode === 'barang' || mode === 'customer') {
        setExportProgress(20, 'Memuat rekap...', '');
        apiGet(Object.assign({}, filterBase(), { mode: mode, skip_summary: 1 }))
          .done(function (res) {
            if (!res.success) return fail(new Error(res.message || 'Gagal'));
            var rows = res.data || [];
            if (mode === 'barang') {
              finishOk('LAPORAN REKAP PER BARANG + MARGIN',
                ['No', 'Kode', 'Nama', 'Kategori', 'Satuan', 'Trx', 'Qty', 'Harga Beli Avg', 'Harga Jual Avg', 'Modal', 'Penjualan', 'Laba', 'Margin %'],
                rows.map(function (r) {
                  return [r.no, r.barang_kode, r.barang_nama, r.kategori_nama, r.satuan_nama, r.jumlah_transaksi, r.total_qty, r.harga_beli_avg, r.harga_jual_avg, r.total_modal, r.total_penjualan, r.total_laba, r.margin_persen];
                }),
                'Laporan_Barang_Margin_' + dari + '_' + sampai);
            } else {
              finishOk('LAPORAN REKAP PER CUSTOMER',
                ['No', 'Customer', 'Trx', 'Qty', 'Penjualan', 'Lunas', 'Piutang', 'Sisa'],
                rows.map(function (r) {
                  return [r.no, r.customer_nama, r.jumlah_transaksi, r.total_qty, r.total_penjualan, r.total_lunas, r.total_piutang, r.sisa_piutang];
                }),
                'Laporan_Customer_' + dari + '_' + sampai);
            }
          })
          .fail(function (xhr) {
            fail(new Error((xhr.responseJSON && xhr.responseJSON.message) || ('HTTP ' + xhr.status)));
          });
        return;
      }

      function stepDay() {
        if (exportAbort) return fail(new Error('Dibatalkan'));
        if (i >= days.length) {
          var mapped, title, headers, fname;
          if (mode === 'detail') {
            title = 'LAPORAN DETAIL ITEM PENJUALAN + MARGIN';
            headers = ['No', 'Invoice', 'Tanggal', 'Kode', 'Nama', 'Kategori', 'Satuan', 'Qty', 'Harga Beli', 'Harga Jual', 'Modal', 'Subtotal', 'Laba', 'Margin %', 'Customer', 'Kasir', 'Metode', 'Status'];
            mapped = allRows.map(function (r, idx) {
              return [idx + 1, r.penjualan_invoice, r.invoice_tgl, r.barang_kode, r.barang_nama, r.kategori_nama, r.satuan_nama, r.barang_qty, r.harga_beli, r.keranjang_harga, r.modal, r.subtotal, r.laba_kotor, r.margin_persen, r.customer_nama, r.kasir_nama, r.metode_bayar, r.status_bayar];
            });
            fname = 'Laporan_Detail_Margin_' + dari + '_' + sampai;
          } else {
            title = 'LAPORAN TRANSAKSI PENJUALAN';
            headers = ['No', 'Invoice', 'Tanggal', 'Customer', 'Kasir', 'Metode', 'Sub Total', 'Diskon', 'Ongkir', 'Bayar', 'Sisa', 'Status'];
            mapped = allRows.map(function (r, idx) {
              return [idx + 1, r.penjualan_invoice, r.invoice_tgl, r.customer_nama, r.kasir_nama, r.metode_bayar, r.invoice_sub_total, r.invoice_diskon, r.invoice_ongkir, r.invoice_bayar, r.sisa_piutang, r.status_bayar];
            });
            fname = 'Laporan_Penjualan_' + dari + '_' + sampai;
          }
          return finishOk(title, headers, mapped, fname);
        }
        var day = days[i];
        var pct = (i / days.length) * 100;
        setExportProgress(pct, 'Memuat tanggal ' + day + ' (' + (i + 1) + '/' + days.length + ')', fmtNum(allRows.length) + ' baris terkumpul');
        return fetchAllPagesForRange(mode, day, day).then(function (rows) {
          allRows = allRows.concat(rows || []);
          i += 1;
          return stepDay();
        });
      }
      stepDay().fail(fail);
    }

    function openExport(fmt, mode, cetak) {
      mode = mode || currentMode || 'transaksi';
      if (fmt === 'excel') { exportExcelWithProgress(mode); return; }
      if (!periodeOk()) return;
      window.open(EXPORT_PDF_URL + '?' + filterQs() + '&mode=' + encodeURIComponent(mode) + (cetak ? '&print=1' : ''), '_blank');
    }

    function setPeriode(dari, sampai) {
      $('#dari').val(dari); $('#sampai').val(sampai);
      if (dari.substring(0, 7)) $('#filterBulan').val(dari.substring(0, 7));
    }

    $('#btnTerapkan').on('click', loadAll);
    $('#tabLaporan a[data-toggle="tab"]').on('shown.bs.tab', function (e) { loadMode($(e.target).data('mode')); });
    $('#btnExcel').on('click', function () { openExport('excel', currentMode); });
    $('#btnPdf').on('click', function () { openExport('pdf', currentMode); });
    $('#btnCetak').on('click', function () { openExport('pdf', currentMode, true); });
    $('.btn-export-mode').on('click', function () { openExport($(this).data('fmt'), $(this).data('mode')); });
    $('#btnCancelExport').on('click', function () { $('#modalExportProgress').modal('hide'); });

    $('#filterBulan').on('change', function () {
      var v = $(this).val(); if (!v) return;
      var p = v.split('-'), y = parseInt(p[0], 10), m = parseInt(p[1], 10);
      setPeriode(v + '-01', v + '-' + pad(new Date(y, m, 0).getDate()));
    });
    $('#btnQuickBulanIni').on('click', function () {
      var n = new Date(), y = n.getFullYear(), m = n.getMonth() + 1;
      setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(new Date(y, m, 0).getDate())); loadAll();
    });
    $('#btnQuickBulanLalu').on('click', function () {
      var n = new Date(); n.setDate(1); n.setMonth(n.getMonth() - 1);
      var y = n.getFullYear(), m = n.getMonth() + 1;
      setPeriode(y + '-' + pad(m) + '-01', y + '-' + pad(m) + '-' + pad(new Date(y, m, 0).getDate())); loadAll();
    });
    $('#btnQuick3Bln').on('click', function () {
      alert('Maksimal 31 hari. Gunakan filter bulan atau rentang manual ≤31 hari.');
    });
    $('#btnQuickTahunIni').on('click', function () {
      alert('Maksimal 31 hari. Pilih satu bulan lewat "Pilih Bulan".');
    });
    $('#btnQuickHariIni').on('click', function () { var t = fmtDate(new Date()); setPeriode(t, t); loadAll(); });

    $('.select2bs4').select2({ theme: 'bootstrap4' });
    loadAll();
  }

  global.LPJ_BOOT = boot;
  if (global.LPJ_CFG) boot(global.LPJ_CFG);
})(window);
