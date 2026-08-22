/**
 * NUMART — dark mode global (localStorage + toggle navbar).
 */
(function (window) {
  'use strict';

  var STORAGE_KEY = 'numart_dark_mode';
  var LEGACY_KEY = 'numart_pos_dark_mode';
  var BODY_CLASS = 'numart-dark-mode';
  var POS_CLASS = 'bl-dark-mode';

  function readStored() {
    try {
      var v = localStorage.getItem(STORAGE_KEY);
      if (v === null) {
        v = localStorage.getItem(LEGACY_KEY);
      }
      return v === '1';
    } catch (e) {
      return false;
    }
  }

  function writeStored(enabled) {
    try {
      var val = enabled ? '1' : '0';
      localStorage.setItem(STORAGE_KEY, val);
      localStorage.setItem(LEGACY_KEY, val);
    } catch (e) {}
  }

  function applyClasses(enabled) {
    var root = document.documentElement;
    if (enabled) {
      root.classList.add(BODY_CLASS);
      if (document.body) {
        document.body.classList.add(BODY_CLASS, POS_CLASS);
      }
    } else {
      root.classList.remove(BODY_CLASS);
      if (document.body) {
        document.body.classList.remove(BODY_CLASS, POS_CLASS);
      }
    }
    updateToggleUi(enabled);
  }

  function updateToggleUi(enabled) {
    var btn = document.getElementById('numart-dark-mode-toggle');
    if (!btn) {
      return;
    }
    var icon = btn.querySelector('i');
    var label = btn.querySelector('.numart-dark-mode-label');
    if (icon) {
      icon.className = enabled ? 'fas fa-sun' : 'fas fa-moon';
    }
    if (label) {
      label.textContent = enabled ? 'Light' : 'Dark';
    }
    btn.setAttribute('title', enabled ? 'Mode terang' : 'Mode gelap');
    btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
  }

  var NumartDarkMode = {
    isEnabled: readStored,
    setEnabled: function (enabled) {
      writeStored(!!enabled);
      applyClasses(!!enabled);
    },
    toggle: function () {
      NumartDarkMode.setEnabled(!readStored());
    },
    applyEarly: function () {
      applyClasses(readStored());
    },
    init: function () {
      applyClasses(readStored());
      var btn = document.getElementById('numart-dark-mode-toggle');
      if (btn && !btn.dataset.bound) {
        btn.dataset.bound = '1';
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          NumartDarkMode.toggle();
        });
      }
      document.addEventListener('keydown', function (e) {
        if (e.altKey && !e.ctrlKey && !e.shiftKey && (e.key === 'd' || e.key === 'D')) {
          e.preventDefault();
          NumartDarkMode.toggle();
        }
      });
    }
  };

  window.NumartDarkMode = NumartDarkMode;

  if (document.body) {
    NumartDarkMode.applyEarly();
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      NumartDarkMode.applyEarly();
    });
  }
})(window);
