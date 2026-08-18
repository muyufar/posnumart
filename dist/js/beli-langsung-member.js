(function(window, $) {
  'use strict';

  function cfg() {
    return window.blMemberConfig || {};
  }

  function toBool(v) {
    return v === true || v === 1 || v === '1';
  }

  function normalizeTerm(term) {
    term = $.trim(term || '');
    if (term === 'Umum' || term === '-- Pilih Customer --') {
      return '';
    }
    return term;
  }

  function localResults($sel) {
    var out = [];
    var seen = {};
    $sel.find('option').each(function() {
      var $o = $(this);
      var id = String($o.val());
      if (id === '' || id === '0' || seen[id]) {
        return;
      }
      seen[id] = true;
      out.push({
        id: id,
        text: $.trim($o.text()),
        cabang: $o.data('cabang'),
        boleh_piutang: $o.data('boleh-piutang')
      });
    });
    return out;
  }

  function selectedMeta($sel) {
    var data = {};
    try {
      var selData = $sel.select2('data');
      if (selData && selData[0]) {
        data = selData[0];
      }
    } catch (e) {}

    var $opt = $sel.find('option:selected');
    var cabang = data.cabang !== undefined ? parseInt(data.cabang, 10) : parseInt($opt.data('cabang'), 10);
    var boleh = data.boleh_piutang !== undefined ? data.boleh_piutang : $opt.data('boleh-piutang');
    return {
      id: parseInt($sel.val(), 10) || 0,
      cabang: isNaN(cabang) ? null : cabang,
      boleh_piutang: boleh === undefined || boleh === null || boleh === '' ? null : toBool(boleh)
    };
  }

  function bolehPiutang($sel) {
    var meta = selectedMeta($sel);
    if (meta.id < 1) {
      return true;
    }
    if (meta.boleh_piutang !== null) {
      return meta.boleh_piutang;
    }
    var kasir = parseInt(cfg().cabangKasir, 10);
    if (meta.cabang === null || isNaN(kasir)) {
      return true;
    }
    return meta.cabang === kasir;
  }

  function alertPiutangTokoAsal() {
    alert('Piutang hanya untuk member toko ini. Member toko lain hanya bisa Cash atau Transfer.');
  }

  function initSelect($sel) {
    if (!$sel.length) {
      return;
    }

    var c = cfg();
    if ($sel.data('select2')) {
      $sel.select2('destroy');
    }

    $sel.select2({
      theme: 'bootstrap4',
      width: '100%',
      allowClear: false,
      minimumInputLength: 0,
      language: {
        noResults: function() {
          return 'Tidak ketemu. Ketik nama, kartu, atau HP.';
        },
        searching: function() {
          return 'Mencari member...';
        }
      },
      ajax: {
        url: c.searchUrl || 'api/beli-langsung-customer-search.php',
        dataType: 'json',
        delay: 200,
        cache: false,
        data: function(params) {
          return {
            q: normalizeTerm(params.term),
            tipe: c.tipe || 0,
            piutang: c.piutang || 0
          };
        },
        transport: function(params, success, failure) {
          var term = '';
          if (params && params.data) {
            term = normalizeTerm(params.data.q);
          }
          if (term === '') {
            success({ results: localResults($sel) });
            return;
          }
          var $req = $.ajax({
            url: c.searchUrl || 'api/beli-langsung-customer-search.php',
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
              q: term,
              tipe: c.tipe || 0,
              piutang: c.piutang || 0
            }
          });
          $req.then(success);
          $req.fail(failure);
          return $req;
        },
        processResults: function(data) {
          var results = (data && data.results) ? data.results : [];
          var conf = cfg();
          if (parseInt(conf.piutang, 10) !== 1 && parseInt(conf.tipe, 10) < 2) {
            var hasUmum = false;
            for (var i = 0; i < results.length; i++) {
              if (String(results[i].id) === '0') {
                hasUmum = true;
                break;
              }
            }
            if (!hasUmum) {
              results = [{
                id: '0',
                text: 'Umum',
                cabang: parseInt(conf.cabangKasir, 10) || 0,
                boleh_piutang: true
              }].concat(results);
            }
          }
          return { results: results };
        }
      }
    });

    $sel.off('select2:open.blMemberSearch').on('select2:open.blMemberSearch', function() {
      setTimeout(function() {
        var $search = $('.select2-container--open .select2-search__field');
        if (!$search.length) {
          return;
        }
        if (normalizeTerm($search.val()) === '') {
          $search.val('').trigger('input');
        }
      }, 0);
    });
  }

  function bindGuards($sel) {
    var c = cfg();
    var $form = $sel.closest('form');

    $(document).off('click.blMemberPiutang', '.bl-btn-piutang').on('click.blMemberPiutang', '.bl-btn-piutang', function(e) {
      if (!bolehPiutang($sel)) {
        e.preventDefault();
        alertPiutangTokoAsal();
      }
    });

    $sel.off('select2:select.blMemberGuard').on('select2:select.blMemberGuard', function() {
      if (parseInt(c.piutang, 10) === 1 && !bolehPiutang($sel)) {
        alertPiutangTokoAsal();
        if (c.cashUrl) {
          window.location.href = c.cashUrl;
        }
      }
    });

    if ($form.length) {
      $form.off('submit.blMemberGuard').on('submit.blMemberGuard', function(e) {
        if (parseInt(c.piutang, 10) === 1 && !bolehPiutang($sel)) {
          e.preventDefault();
          alertPiutangTokoAsal();
          return false;
        }
      });
    }
  }

  window.blInitMemberSelect = function(selector) {
    var $sel = $(selector || '#invoice_customer');
    initSelect($sel);
    bindGuards($sel);
    return $sel;
  };

  window.blMemberBolehPiutang = function(selector) {
    return bolehPiutang($(selector || '#invoice_customer'));
  };
})(window, jQuery);
