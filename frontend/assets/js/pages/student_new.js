/* Add a student (teacher only): creates the academic record and the login account through api/student_create.php. */
(function () {
  'use strict';
  var alerts = EW.$('alerts');
  var submit = EW.$('submit');

  EW.page.start({ role: 'teacher', active: 'teacher.html' });

  EW.$('student-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    EW.clear(alerts);
    EW.busy(submit, true);
    EW.api.post('student_create.php', {
      student_id: EW.$('student_id').value.trim(),
      last_name: EW.$('last_name').value.trim(),
      first_name: EW.$('first_name').value.trim(),
      email: EW.$('email').value.trim(),
      makeup: EW.$('makeup').checked ? '1' : '0'
    }).then(function (res) {
      EW.flash.set('success', res.message);
      window.location.replace('teacher.html'); // the temporary password is shown once on the dashboard
    }, function (err) {
      if (EW.api.handleAuthError(err)) { return; }
      var list = err.data && err.data.errors ? err.data.errors : [err.message];
      EW.showAlert(alerts, 'error', list);
      EW.busy(submit, false);
    });
  });
})();
