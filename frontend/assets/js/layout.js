/*
 * Page start-up: finds out who is logged in, protects the page by role, and draws the shared header and footer.
 *   EW.page.start({role: 'student' | 'teacher' | 'any' | 'guest', active: 'teacher.html', allowForced: false})
 * resolves with the logged-in user (or null on guest pages).
 *   guest   - login/forgot/reset/QR pages: people who are already logged in are sent to their dashboard
 *   student / teacher - only that role may see the page
 *   any     - any logged-in user (Settings)
 */
(function (w, d) {
  'use strict';
  var EW = w.EW = w.EW || {};
  var h = EW.h;
  var APP_NAME = 'EduWatch';

  function drawHeader(user, active, hideNav) {
    var host = EW.$('site-header');
    if (!host) { return; }
    EW.clear(host);
    host.className = 'topbar';
    var inner = h('div', { class: 'topbar-inner' });
    inner.appendChild(h('a', { class: 'brand', href: user ? EW.homeFor(user.role) : 'login.html' },
      h('span', { class: 'brand-mark', text: 'E' }), ' ' + APP_NAME));

    if (user) {
      var items = user.role === 'teacher'
        ? [['teacher.html', 'Dashboard'], ['audit.html', 'Audit log'], ['settings.html', 'Settings']]
        : [['student.html', 'Dashboard'], ['settings.html', 'Settings']];
      var nav = h('nav', { class: 'nav' });
      if (!hideNav) {
        items.forEach(function (it) {
          nav.appendChild(h('a', { href: it[0], class: active === it[0] ? 'active' : null, text: it[1] }));
        });
      }
      nav.appendChild(h('button', { class: 'linklike', type: 'button', text: 'Log out', onclick: logout }));
      inner.appendChild(nav);
      inner.appendChild(h('span', { class: 'who' }, user.full_name + ' ',
        h('small', { text: '(' + (user.role === 'teacher' ? 'staff' : 'student') + ')' })));
    }
    host.appendChild(inner);
  }

  function drawFooter() {
    var host = EW.$('site-footer');
    if (!host) { return; }
    host.className = 'footer';
    EW.clear(host).appendChild(h('div', { class: 'container', text: APP_NAME + ' - Academic Early-Warning and Dropout Prediction System' }));
  }

  /** Messages saved by the previous page (e.g. "Grade added") appear at the top of this one. */
  function drawFlash() {
    var host = EW.$('flash');
    if (!host) { return; }
    EW.clear(host);
    EW.flash.take().forEach(function (f) {
      host.appendChild(h('div', { class: 'container narrow' }, EW.alertBox(f.type, f.message)));
    });
  }

  function logout() {
    var done = function () { w.location.replace('login.html?out=1'); };
    EW.api.post('logout.php', {}).then(done, done); // even if the session was already gone, go to the login page
  }

  function hideLoading() { var l = EW.$('loading'); if (l) { l.hidden = true; } }

  function showNotAllowed(user, needed) {
    hideLoading();
    var main = EW.$('app') || d.querySelector('main');
    if (!main) { return; }
    main.hidden = false;
    EW.clear(main);
    main.className = 'container narrow';
    main.appendChild(h('div', { class: 'card' },
      h('h1', { text: 'Not allowed' }),
      h('p', { text: 'This page is only for ' + needed + ' accounts.' }),
      h('p', {}, h('a', { class: 'btn', href: EW.homeFor(user.role), text: 'Back to my dashboard' }))));
  }

  function showFatal(err) {
    hideLoading();
    var host = EW.$('flash');
    var box = h('div', { class: 'container narrow' }, EW.alertBox('error', err && err.message ? err.message : 'Something went wrong.'));
    if (host) { host.appendChild(box); } else { d.body.insertBefore(box, d.body.firstChild); }
  }

  EW.page = {
    showFatal: showFatal,
    /** Use as the .catch of a page's data request: redirects on a lost session, otherwise shows the error. */
    fail: function (err) {
      if (!EW.api.handleAuthError(err)) { showFatal(err); }
    },
    start: function (opts) {
      opts = opts || {};
      var role = opts.role || 'guest';
      drawFooter();
      return EW.api.session().then(function (s) {
        var user = s.authenticated ? s.user : null;

        if (role === 'guest') {
          if (user) {
            w.location.replace(user.must_change ? 'settings.html?force=1' : EW.homeFor(user.role));
            return new Promise(function () {}); // stay quiet while the browser navigates away
          }
          drawHeader(null);
          drawFlash();
          return null;
        }

        if (!user) {
          w.location.replace(s.status === 'timeout' ? 'login.html?timeout=1' : 'login.html');
          return new Promise(function () {});
        }
        if (user.must_change && !opts.allowForced) {
          w.location.replace('settings.html?force=1');
          return new Promise(function () {});
        }
        EW.theme.apply(user.theme);
        drawHeader(user, opts.active, !!opts.hideNav || (user.must_change && !!opts.allowForced));
        drawFlash();
        if (role !== 'any' && user.role !== role) {
          showNotAllowed(user, role === 'teacher' ? 'staff' : 'student');
          return new Promise(function () {});
        }
        return user;
      }).catch(function (err) {
        drawHeader(null);
        showFatal(err);
        return new Promise(function () {});
      });
    }
  };
})(window, document);
