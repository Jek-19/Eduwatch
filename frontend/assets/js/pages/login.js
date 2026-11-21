/* Login page. The QR scanner sends the student ID here (?id=...). It only pre-fills the box; the password is still required. */
(function () {
  'use strict';
  var alerts = EW.$('alerts');
  var form = EW.$('login-form');
  var submit = EW.$('submit');

  EW.page.start({ role: 'guest' }).then(function () {
    var id = EW.param('id');
    if (/^[A-Za-z0-9._-]{1,30}$/.test(id)) { EW.$('username').value = id; EW.$('password').focus(); }

    if (EW.param('reset') === '1') {
      EW.showAlert(alerts, 'success', 'Your password was changed. You can log in with your new password.');
    } else if (EW.param('out') === '1') {
      EW.showAlert(alerts, 'success', 'You have been logged out.');
    } else if (EW.param('timeout') === '1') {
      EW.showAlert(alerts, 'success', 'You were logged out because of inactivity. Please log in again.');
    }
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(alerts);
    EW.busy(submit, true);
    EW.api.post('login.php', {
      username: EW.$('username').value.trim(),
      password: EW.$('password').value
    }).then(function (res) {
      EW.api.setCsrf(res.csrf);
      EW.theme.apply(res.theme);
      if (res.must_change) {
        EW.flash.set('info', 'Welcome! Please choose a new password before you continue.');
        window.location.replace('settings.html?force=1');
      } else {
        window.location.replace(EW.homeFor(res.role));
      }
    }, function (err) {
      EW.showAlert(alerts, 'error', err.message);
      EW.$('password').value = '';
      EW.busy(submit, false);
    });
  });
})();
