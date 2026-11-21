/* Front door: send people to their dashboard if they are logged in, otherwise to the login page. */
(function () {
  'use strict';
  EW.api.session().then(function (s) {
    if (s.authenticated) {
      window.location.replace(s.user.must_change ? 'settings.html?force=1' : EW.homeFor(s.user.role));
    } else {
      window.location.replace('login.html');
    }
  }, function (err) {
    EW.page.showFatal(err);
  });
})();
