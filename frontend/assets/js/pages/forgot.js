/* Forgot password, steps 1-2: ask for a one-time reset link by email. */
(function () {
  'use strict';
  var alerts = EW.$('alerts');
  var submit = EW.$('submit');

  EW.page.start({ role: 'guest' });

  EW.$('forgot-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(alerts);
    EW.busy(submit, true);
    EW.api.post('forgot.php', { email: EW.$('email').value.trim() }).then(function (res) {
      EW.$('minutes').textContent = res.minutes;
      // Demo mode only: the backend shows the link instead of emailing it (see backend/config.php).
      if (res.demo_link && /^https?:\/\//i.test(res.demo_link)) {
        var a = EW.$('demo-link');
        a.href = res.demo_link;
        a.textContent = res.demo_link;
        EW.$('demo-box').hidden = false;
      }
      EW.$('step-form').hidden = true;
      EW.$('step-done').hidden = false;
    }, function (err) {
      EW.showAlert(alerts, 'error', err.message);
      EW.busy(submit, false);
    });
  });
})();
