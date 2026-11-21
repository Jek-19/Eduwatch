/*
 * Small UI helpers shared by every page. Text is always inserted with textContent / text nodes,
 * never as raw HTML, so nothing a user types (names, grades, notes) can inject markup.
 */
(function (w, d) {
  'use strict';
  var EW = w.EW = w.EW || {};

  /** h('div', {class: 'card', onclick: fn}, 'text', childNode, [more]) -> Element */
  EW.h = function (tag, attrs) {
    var el = d.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v === null || v === undefined || v === false) { return; }
      if (k === 'class') { el.className = v; }
      else if (k === 'text') { el.textContent = v; }
      else if (k.slice(0, 2) === 'on' && typeof v === 'function') { el.addEventListener(k.slice(2), v); }
      else if (v === true) { el.setAttribute(k, ''); }
      else { el.setAttribute(k, v); }
    });
    (function add(list) {
      list.forEach(function (c) {
        if (c === null || c === undefined || c === false) { return; }
        if (Array.isArray(c)) { add(c); }
        else if (c.nodeType) { el.appendChild(c); }
        else { el.appendChild(d.createTextNode(String(c))); }
      });
    })(Array.prototype.slice.call(arguments, 2));
    return el;
  };

  EW.clear = function (node) { while (node.firstChild) { node.removeChild(node.firstChild); } return node; };
  EW.$ = function (id) { return d.getElementById(id); };
  EW.param = function (name) { return new URLSearchParams(w.location.search).get(name) || ''; };
  EW.homeFor = function (role) { return role === 'teacher' ? 'teacher.html' : 'student.html'; };

  /** Number with fixed decimals, rounding .5 upwards like the PHP version did. */
  EW.fmtNum = function (n, dec) {
    dec = dec === undefined ? 1 : dec;
    var f = Math.pow(10, dec);
    return (Math.round((Number(n) + Number.EPSILON) * f) / f).toFixed(dec);
  };

  EW.riskBadge = function (level, label) {
    var lv = ['high', 'medium', 'low', 'nodata'].indexOf(level) >= 0 ? level : 'nodata';
    return EW.h('span', { class: 'badge badge-' + lv, text: label || lv });
  };

  EW.alertBox = function (type, message) {
    var t = ['success', 'error', 'info', 'warning'].indexOf(type) >= 0 ? type : 'info';
    return EW.h('div', { class: 'alert alert-' + t, role: 'alert', text: message });
  };

  /** Replace the contents of a container with one or more alerts. */
  EW.showAlert = function (container, type, messages) {
    EW.clear(container);
    [].concat(messages).forEach(function (m) { container.appendChild(EW.alertBox(type, m)); });
  };

  /** Disable a button while a request is running. */
  EW.busy = function (button, isBusy) { if (button) { button.disabled = !!isBusy; } };

  /* One-time messages that must survive a page change (replaces PHP flash messages). */
  EW.flash = {
    set: function (type, message) {
      try { sessionStorage.setItem('ew_flash', JSON.stringify([{ type: type, message: message }])); } catch (e) { /* ignore */ }
    },
    take: function () {
      try {
        var raw = sessionStorage.getItem('ew_flash');
        sessionStorage.removeItem('ew_flash');
        return raw ? JSON.parse(raw) : [];
      } catch (e) { return []; }
    }
  };

  EW.theme = {
    apply: function (theme) {
      var t = theme === 'dark' ? 'dark' : 'light';
      d.documentElement.setAttribute('data-theme', t);
      try { localStorage.setItem('ew_theme', t); } catch (e) { /* ignore */ }
    }
  };

  /* Small behaviours that used to need inline attributes (blocked by the Content-Security-Policy). */
  d.addEventListener('click', function (ev) {
    var t = ev.target;
    var el = t.closest ? t.closest('[data-confirm]') : null;
    if (el && !w.confirm(el.getAttribute('data-confirm'))) { ev.preventDefault(); }
    var pr = t.closest ? t.closest('[data-print]') : null;
    if (pr) { w.print(); }
  });
})(window, document);
