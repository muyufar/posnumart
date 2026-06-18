(function (global) {
  'use strict';

  var CHANNEL_NAME = 'numart_layar_konsumen';
  var WINDOW_NAME = 'numart_layar_konsumen';

  function screenMetrics(sc) {
    return {
      left: sc.availLeft,
      top: sc.availTop,
      width: sc.availWidth,
      height: sc.availHeight
    };
  }

  function getCurrentScreenMetrics() {
    return screenMetrics(global.screen);
  }

  function getFallbackSecondScreen() {
    var s = global.screen;
    if (global.screenLeft >= s.availLeft + s.availWidth - 8) {
      return getCurrentScreenMetrics();
    }
    return {
      left: s.availLeft + s.availWidth,
      top: s.availTop,
      width: s.availWidth,
      height: s.availHeight
    };
  }

  async function getSecondScreenTarget() {
    if (global.getScreenDetails) {
      try {
        var details = await global.getScreenDetails();
        var screens = details.screens || [];
        if (screens.length > 1) {
          var current = details.currentScreen;
          for (var i = 0; i < screens.length; i++) {
            if (screens[i] !== current) {
              return screenMetrics(screens[i]);
            }
          }
          for (var j = 0; j < screens.length; j++) {
            if (!screens[j].isPrimary) {
              return screenMetrics(screens[j]);
            }
          }
        }
        if (details.currentScreen) {
          return screenMetrics(details.currentScreen);
        }
      } catch (err) {
        /* permission denied or unsupported */
      }
    }
    return getFallbackSecondScreen();
  }

  function buildWindowFeatures(metrics) {
    return [
      'popup=yes',
      'menubar=no',
      'toolbar=no',
      'location=no',
      'status=no',
      'scrollbars=no',
      'resizable=yes',
      'left=' + Math.round(metrics.left),
      'top=' + Math.round(metrics.top),
      'width=' + Math.round(metrics.width),
      'height=' + Math.round(metrics.height)
    ].join(',');
  }

  function appendAutoDisplayParam(url) {
    if (/[?&]auto_display=1(?:&|$)/.test(url)) {
      return url;
    }
    return url + (url.indexOf('?') >= 0 ? '&' : '?') + 'auto_display=1';
  }

  function applyPseudoFullscreen(enabled) {
    global.document.documentElement.classList.toggle('lk-pseudo-fs', !!enabled);
    global.document.body.classList.toggle('lk-pseudo-fs', !!enabled);
  }

  function isFullscreen() {
    return !!(global.document.fullscreenElement || global.document.webkitFullscreenElement);
  }

  async function requestFullscreen() {
    var el = global.document.documentElement;
    try {
      if (el.requestFullscreen) {
        await el.requestFullscreen();
        return true;
      }
      if (el.webkitRequestFullscreen) {
        el.webkitRequestFullscreen();
        return true;
      }
    } catch (err) {
      return false;
    }
    return false;
  }

  function showDisplayHint(show) {
    var hint = global.document.getElementById('lk-display-hint');
    if (hint) {
      hint.hidden = !show;
    }
  }

  async function moveToSecondMonitorAndFullscreen(options) {
    options = options || {};
    var metrics = await getSecondScreenTarget();

    try {
      global.moveTo(metrics.left, metrics.top);
      global.resizeTo(metrics.width, metrics.height);
    } catch (err) {
      /* blocked in some embedded contexts */
    }

    applyPseudoFullscreen(true);

    var fsOk = await requestFullscreen();
    if (!fsOk && options.showHint !== false) {
      showDisplayHint(true);
    } else {
      showDisplayHint(false);
    }

    return { metrics: metrics, fullscreen: fsOk };
  }

  function postDisplayCommand() {
    try {
      var channel = new BroadcastChannel(CHANNEL_NAME);
      channel.postMessage({ type: 'display_second_monitor' });
      channel.close();
    } catch (err) {
      /* BroadcastChannel unsupported */
    }
  }

  async function openFromKasir(url, existingRef) {
    var metrics = await getSecondScreenTarget();
    var targetUrl = appendAutoDisplayParam(url);
    var features = buildWindowFeatures(metrics);
    var win = global.open(targetUrl, WINDOW_NAME, features);

    if (win && !win.closed) {
      try {
        win.moveTo(metrics.left, metrics.top);
        win.resizeTo(metrics.width, metrics.height);
      } catch (err) {
        /* cross-origin or blocked */
      }
    } else if (existingRef && !existingRef.closed) {
      existingRef.focus();
    }

    global.setTimeout(postDisplayCommand, 400);
    return win;
  }

  function initDisplayPage() {
    var params = new URLSearchParams(global.location.search);
    var autoDisplay = params.get('auto_display') === '1';

    function activateSecondMonitorDisplay() {
      showDisplayHint(false);
      moveToSecondMonitorAndFullscreen({ showHint: false });
    }

    try {
      var channel = new BroadcastChannel(CHANNEL_NAME);
      channel.onmessage = function (ev) {
        if (ev.data && ev.data.type === 'display_second_monitor') {
          moveToSecondMonitorAndFullscreen();
        }
      };
    } catch (err) {
      /* BroadcastChannel unsupported */
    }

    global.document.addEventListener('fullscreenchange', function () {
      var bar = global.document.getElementById('lk-display-bar');
      if (bar) {
        bar.hidden = isFullscreen();
      }
      if (!isFullscreen()) {
        applyPseudoFullscreen(false);
      }
    });

    var btn = global.document.getElementById('lk-btn-second-monitor');
    if (btn) {
      btn.addEventListener('click', activateSecondMonitorDisplay);
    }

    var hintBtn = global.document.getElementById('lk-display-hint-btn');
    if (hintBtn) {
      hintBtn.addEventListener('click', activateSecondMonitorDisplay);
    }

    global.document.addEventListener('keydown', function (e) {
      if (e.key !== 'F8' && e.keyCode !== 119) {
        return;
      }
      var tag = (e.target && e.target.tagName) || '';
      if (/^(INPUT|TEXTAREA|SELECT)$/i.test(tag)) {
        return;
      }
      e.preventDefault();
      activateSecondMonitorDisplay();
    });

    if (autoDisplay) {
      global.setTimeout(function () {
        moveToSecondMonitorAndFullscreen();
      }, 300);
    }
  }

  global.NumartLayarKonsumen = {
    WINDOW_NAME: WINDOW_NAME,
    getSecondScreenTarget: getSecondScreenTarget,
    buildWindowFeatures: buildWindowFeatures,
    moveToSecondMonitorAndFullscreen: moveToSecondMonitorAndFullscreen,
    openFromKasir: openFromKasir,
    initDisplayPage: initDisplayPage
  };
})(window);
