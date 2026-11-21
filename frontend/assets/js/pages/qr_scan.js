/* QR Scan page: reads a student-ID QR code and sends the person to login.html?id=... (password still required). */
(function () {
  'use strict';
  var ID_PATTERN = /^(124|224)-\d{5}$/;
  var statusEl = document.getElementById('qr-status');
  var startBtn = document.getElementById('qr-start');
  var stopBtn = document.getElementById('qr-stop');
  var scanner = null;
  var handled = false;

  EW.page.start({ role: 'guest' }); // people who are already logged in go to their dashboard instead

  function say(type, text) {
    statusEl.className = 'alert alert-' + type;
    statusEl.textContent = text;
  }

  function stopScanner() {
    if (!scanner) { return Promise.resolve(); }
    var s = scanner;
    scanner = null;
    return s.stop().catch(function () {}).then(function () { try { s.clear(); } catch (e) {} });
  }

  function onScanSuccess(text) {
    if (handled) { return; }
    var value = String(text || '').trim();
    if (!ID_PATTERN.test(value)) {
      say('warning', 'That QR code is not an EduWatch student ID. Try again or type your ID on the login page.');
      return;
    }
    handled = true;
    say('success', 'ID ' + value + ' found. Taking you to the login page...');
    stopScanner().then(function () {
      window.location.href = 'login.html?id=' + encodeURIComponent(value);
    });
  }

  startBtn.addEventListener('click', function () {
    if (typeof Html5Qrcode === 'undefined') {
      say('error', 'The QR scanner could not load. Please type your ID on the login page.');
      return;
    }
    handled = false;
    startBtn.hidden = true;
    stopBtn.hidden = false;
    say('info', 'Starting camera...');
    scanner = new Html5Qrcode('reader');
    scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 240, height: 240 } },
      onScanSuccess,
      function () { /* no QR in this frame - keep looking */ }
    ).then(function () {
      say('info', 'Camera is on. Hold the QR code inside the square.');
    }).catch(function (err) {
      scanner = null;
      startBtn.hidden = false;
      stopBtn.hidden = true;
      say('error', 'Could not start the camera (' + (err && err.message ? err.message : err) + '). Check the camera permission, or use localhost/https.');
    });
  });

  stopBtn.addEventListener('click', function () {
    stopScanner().then(function () {
      startBtn.hidden = false;
      stopBtn.hidden = true;
      say('info', 'Camera stopped.');
    });
  });

  window.addEventListener('beforeunload', function () { stopScanner(); });
})();
