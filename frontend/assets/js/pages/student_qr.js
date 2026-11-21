/*
 * Printable QR code for a student ID (teacher only), drawn entirely in the browser with the bundled qrcode-generator library.
 * The QR contains ONLY the student ID, so it is safe to print on an ID card: scanning it just fills in the ID on the login page.
 */
(function () {
  'use strict';
  var studentId = EW.param('student').slice(0, 10);

  EW.page.start({ role: 'teacher', active: 'teacher.html' }).then(function () {
    return EW.api.get('student_info.php', { student: studentId });
  }).then(function (res) {
    var s = res.student;
    EW.$('student-name').textContent = s.last_name + ', ' + s.first_name;
    EW.$('qr-id').textContent = s.student_id;
    var target = EW.$('qr-target');
    target.setAttribute('aria-label', 'QR code for student ID ' + s.student_id);
    if (typeof qrcode === 'undefined') {
      EW.showAlert(EW.$('alerts'), 'error', 'The QR code library could not load.');
      return;
    }
    var qr = qrcode(0, 'M');
    qr.addData(s.student_id);
    qr.make();
    target.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 4, scalable: true }); // SVG made by the library from the ID only
  }).catch(function (err) {
    if (err.code === 'not_found') {
      EW.flash.set('error', err.message);
      window.location.replace('teacher.html');
      return;
    }
    EW.page.fail(err);
  });
})();
