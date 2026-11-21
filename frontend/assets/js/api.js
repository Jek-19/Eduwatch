/*
 * API client: the only file that knows how to talk to the PHP backend.
 *   EW.api.get('teacher_dashboard.php', {q: 'cruz'})   -> Promise of the JSON answer
 *   EW.api.post('login.php', {username, password})     -> Promise of the JSON answer
 * Failed calls reject with an ApiError: {message, status, code, data}.
 * It also adds the CSRF token to every POST and sends the person to the right page when the session is gone.
 */
(function (w) {
  'use strict';
  var EW = w.EW = w.EW || {};
  var BASE = ((w.EW_CONFIG && w.EW_CONFIG.API_BASE) || '../backend/api').replace(/\/+$/, '');
  var csrf = '';
  var sessionPromise = null;

  function ApiError(message, status, code, data) {
    var err = new Error(message);
    err.name = 'ApiError';
    err.status = status || 0;
    err.code = code || 'error';
    err.data = data || {};
    return err;
  }

  function buildUrl(name, params) {
    var url = BASE + '/' + name;
    var parts = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v !== undefined && v !== null && v !== '') {
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
      }
    });
    return parts.length ? url + '?' + parts.join('&') : url;
  }

  /** Where to send the person when the server says "you cannot be here". Returns true if it redirected. */
  function handleAuthError(err) {
    var home = function (role) { return role === 'teacher' ? 'teacher.html' : 'student.html'; };
    if (err.code === 'not_logged_in') { w.location.replace('login.html'); return true; }
    if (err.code === 'timeout') { w.location.replace('login.html?timeout=1'); return true; }
    if (err.code === 'password_change_required') { w.location.replace('settings.html?force=1'); return true; }
    if (err.code === 'wrong_role' && err.data && err.data.role) { w.location.replace(home(err.data.role)); return true; }
    return false;
  }

  function send(method, name, params, body, retried) {
    var ready = (method === 'POST' && !csrf) ? loadSession(true) : Promise.resolve();
    return ready.then(function () {
      var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
      if (method === 'POST') {
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-Token'] = csrf;
        opts.body = JSON.stringify(body || {});
      }
      return fetch(buildUrl(name, params), opts);
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = null;
        try { data = JSON.parse(text); } catch (e) { /* not JSON */ }
        if (!data || typeof data !== 'object') {
          throw ApiError('The server sent an unexpected answer. Check that Apache and PHP are running and that the backend folder is next to the frontend folder.', res.status, 'bad_response');
        }
        if (!res.ok || data.ok === false) {
          throw ApiError(data.error || 'Something went wrong.', res.status, data.code, data);
        }
        return data;
      });
    }, function () {
      throw ApiError('Could not reach the server. Check that Apache and MySQL are running.', 0, 'network');
    }).catch(function (err) {
      // The CSRF token can go stale (e.g. session renewed in another tab): get a fresh one and try once more.
      if (err.code === 'csrf' && method === 'POST' && !retried) {
        return loadSession(true).then(function () { return send(method, name, params, body, true); });
      }
      throw err;
    });
  }

  /** Ask the server who is logged in (also gives us the CSRF token). Cached unless force = true. */
  function loadSession(force) {
    if (!sessionPromise || force) {
      sessionPromise = fetch(buildUrl('session.php'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || data.ok !== true) { throw ApiError('Could not read your session.', 0, 'bad_response'); }
          csrf = data.csrf || '';
          return data;
        })
        .catch(function (err) {
          sessionPromise = null;
          throw err.name === 'ApiError' ? err : ApiError('Could not reach the server. Check that Apache and MySQL are running.', 0, 'network');
        });
    }
    return sessionPromise;
  }

  EW.api = {
    get: function (name, params) { return send('GET', name, params); },
    post: function (name, body) { return send('POST', name, null, body); },
    session: loadSession,
    handleAuthError: handleAuthError,
    /** After login the session id (and token) change: remember the new token. */
    setCsrf: function (token) { if (token) { csrf = token; } sessionPromise = null; }
  };
})(window);
