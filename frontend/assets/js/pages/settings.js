/* Settings (both roles): dark mode, tone for insight messages (students), change password. Also the forced "choose a new password" step. */
(function () {
  'use strict';
  var alerts = EW.$('alerts');
  var pwAlerts = EW.$('pw-alerts');
  var forced = false;

  function radio(name, value) {
    var el = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
    if (el) { el.checked = true; }
  }
  function chosen(name) {
    var el = document.querySelector('input[name="' + name + '"]:checked');
    return el ? el.value : '';
  }

  EW.page.start({ role: 'any', active: 'settings.html', allowForced: true }).then(function () {
    return EW.api.get('settings.php');
  }).then(function (d) {
    forced = d.forced;
    var isStudent = d.role === 'student';
    radio('theme', d.settings.theme);
    radio('tone', d.settings.tone);
    EW.$('tone-fieldset').hidden = !isStudent;
    EW.$('prefs-title').textContent = isStudent ? 'Appearance & messages' : 'Appearance';
    EW.$('prefs-card').hidden = forced;
    EW.$('forced-note').hidden = !forced;
    if (forced) { EW.$('current-label').textContent = 'Current (temporary) password'; }
  }, EW.page.fail);

  EW.$('prefs-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(alerts);
    var btn = EW.$('prefs-submit');
    EW.busy(btn, true);
    EW.api.post('settings.php', { theme: chosen('theme'), tone: chosen('tone') }).then(function (res) {
      EW.theme.apply(res.settings.theme);
      EW.showAlert(alerts, 'success', res.message);
      window.scrollTo(0, 0);
    }, function (err) {
      if (!EW.api.handleAuthError(err)) { EW.showAlert(alerts, 'error', err.message); }
    }).then(function () { EW.busy(btn, false); });
  });

  EW.$('pw-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(pwAlerts);
    var btn = EW.$('pw-submit');
    EW.busy(btn, true);
    EW.api.post('change_password.php', {
      current: EW.$('current').value,
      password: EW.$('password').value,
      password2: EW.$('password2').value
    }).then(function (res) {
      EW.flash.set('success', res.message);
      window.location.replace(EW.homeFor(res.role));
    }, function (err) {
      if (!EW.api.handleAuthError(err)) {
        EW.showAlert(pwAlerts, 'error', err.message);
        EW.busy(btn, false);
      }
    });
  });
})();
