/* Forgot password, step 3: open the emailed one-time link and choose a new password. */
(function () {
  'use strict';
  var alerts = EW.$('alerts');
  var submit = EW.$('submit');
  var token = EW.param('token');

  function showInvalid() {
    EW.$('checking').hidden = true;
    EW.$('valid').hidden = true;
    EW.$('invalid').hidden = false;
  }

  EW.page.start({ role: 'guest' }).then(function () {
    if (!/^[a-f0-9]{64}$/.test(token)) { showInvalid(); return; }
    EW.api.get('reset.php', { token: token }).then(function (res) {
      if (!res.valid) { showInvalid(); return; }
      EW.$('account').textContent = res.username;
      EW.$('checking').hidden = true;
      EW.$('valid').hidden = false;
    }, function (err) {
      // The server could not be reached or crashed: say so (this is not the same as an invalid link).
      EW.$('checking').hidden = true;
      EW.page.showFatal(err);
    });
  });

  EW.$('reset-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(alerts);
    EW.busy(submit, true);
    EW.api.post('reset.php', {
      token: token,
      password: EW.$('password').value,
      password2: EW.$('password2').value
    }).then(function () {
      window.location.replace('login.html?reset=1');
    }, function (err) {
      if (err.code === 'invalid_token') { showInvalid(); return; }
      EW.showAlert(alerts, 'error', err.message);
      EW.busy(submit, false);
    });
  });
})();
