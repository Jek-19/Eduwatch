/* Grades for one student (teacher only): add / edit / delete grades and the makeup flag through api/grades.php. */
(function () {
  'use strict';
  var h = EW.h;
  var studentId = EW.param('student').slice(0, 10);
  var alerts = EW.$('alerts');
  var firstLoad = true;
  var terms = [];

  function notFound(err) {
    if (err.code === 'not_found' || err.code === 'no_semester') {
      EW.flash.set('error', err.message);
      window.location.replace('teacher.html');
      return;
    }
    EW.page.fail(err);
  }

  /** Run a change on the server, show the message, then redraw the page from fresh data. */
  function act(body, button) {
    EW.clear(alerts);
    EW.busy(button, true);
    body.student = studentId;
    return EW.api.post('grades.php', body).then(function (res) {
      return load().then(function () { EW.showAlert(alerts, res.type || 'success', res.message); return true; });
    }, function (err) {
      if (!EW.api.handleAuthError(err)) { EW.showAlert(alerts, 'error', err.message); }
      return false;
    }).then(function (ok) { EW.busy(button, false); return ok; });
  }

  function cell(d, row, t) {
    var c = row.cells[t.key];
    if (!c) { return h('td', {}, h('span', { class: 'muted', text: '\u2013' })); }
    var input = h('input', {
      class: 'score-input', type: 'text', inputmode: 'decimal', maxlength: '6',
      value: EW.fmtNum(c.score, 2), 'aria-label': row.subject + ' ' + t.label + ' grade'
    });
    var save = h('button', { class: 'btn btn-small', type: 'submit', text: 'Save' });
    var del = h('button', {
      class: 'btn btn-small btn-danger', type: 'button', text: 'Delete',
      onclick: function () { if (window.confirm('Delete this grade?')) { act({ action: 'delete', grade_id: c.id }, del); } }
    });
    var form = h('form', {
      class: 'cell-form', method: 'post', action: 'grades.html',
      onsubmit: function (ev) { ev.preventDefault(); act({ action: 'update', grade_id: c.id, score: input.value }, save); }
    }, input, ' ', save, ' ', del);
    return h('td', {}, form);
  }

  function render(d) {
    terms = d.terms;
    var s = d.student;
    EW.$('student-name').textContent = s.last_name + ', ' + s.first_name;
    EW.$('student-meta').textContent = 'ID ' + s.student_id + ' \u00b7 ' + d.semester.label;
    EW.$('grades-title').textContent = 'Grades (' + d.semester.label + ')';
    EW.clear(EW.$('risk-badge')).appendChild(EW.riskBadge(d.risk.level, d.risk.label));

    var reasons = EW.clear(EW.$('reasons'));
    d.risk.reasons.forEach(function (r) { reasons.appendChild(h('li', { text: r })); });
    EW.$('makeup').checked = s.with_makeup_grades;

    var head = EW.clear(EW.$('grades-head'));
    head.appendChild(h('th', { text: 'Subject' }));
    d.terms.forEach(function (t) { head.appendChild(h('th', { text: t.label })); });
    var body = EW.clear(EW.$('grades-body'));
    d.grades.forEach(function (row) {
      var tr = h('tr', {}, h('td', { text: row.subject }));
      d.terms.forEach(function (t) { tr.appendChild(cell(d, row, t)); });
      body.appendChild(tr);
    });
    EW.$('no-grades').hidden = d.grades.length > 0;
    EW.$('grades-wrap').hidden = d.grades.length === 0;

    if (firstLoad) { // fill the "Add a grade" dropdowns once, so a half-typed form is not wiped on every refresh
      d.subjects.forEach(function (sub) { EW.$('subject_id').appendChild(h('option', { value: sub.id, text: sub.code + ' - ' + sub.name })); });
      d.terms.forEach(function (t) { EW.$('term').appendChild(h('option', { value: t.key, text: t.label })); });
      firstLoad = false;
    }
    EW.$('loading').hidden = true;
    EW.$('app').hidden = false;
  }

  function load() { return EW.api.get('grades.php', { student: studentId }).then(render); }

  EW.page.start({ role: 'teacher', active: 'teacher.html' }).then(function () {
    if (!studentId) { notFound({ code: 'not_found', message: 'That student was not found.' }); return null; }
    return load();
  }).catch(notFound);

  EW.$('makeup-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    act({ action: 'makeup', makeup: EW.$('makeup').checked ? '1' : '0' }, ev.target.querySelector('button'));
  });

  EW.$('add-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = ev.target.querySelector('button[type="submit"]');
    act({ action: 'add', subject_id: EW.$('subject_id').value, term: EW.$('term').value, score: EW.$('score').value }, btn)
      .then(function (ok) { if (ok) { EW.$('score').value = ''; } });
  });
})();
