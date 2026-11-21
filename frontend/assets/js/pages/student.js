/* Student dashboard (read-only): fills the page from api/student_dashboard.php and draws the charts. */
(function () {
  'use strict';
  var h = EW.h;

  function render(d) {
    EW.$('loading').hidden = true;
    if (!d.record) { EW.$('no-record').hidden = false; return; }

    var s = d.student, risk = d.risk;
    EW.$('app').hidden = false;
    EW.$('hello').textContent = 'Hi, ' + s.first_name;
    EW.$('student-meta').textContent = s.last_name + ', ' + s.first_name + ' \u00b7 ID ' + s.student_id + ' \u00b7 ' + d.semester.label;

    EW.$('risk-card').classList.add('risk-' + risk.level);
    EW.$('risk-badge').appendChild(EW.riskBadge(risk.level, risk.label));
    EW.$('insight').textContent = d.message;

    if (risk.level !== 'nodata') {
      var why = EW.$('why');
      why.hidden = false;
      why.open = risk.level === 'high' || risk.level === 'medium';
      risk.reasons.forEach(function (r) { EW.$('why-list').appendChild(h('li', { text: r })); });
      if (risk.general_avg !== null) {
        EW.$('gen-avg-num').textContent = EW.fmtNum(risk.general_avg, 2);
        EW.$('pass-num').textContent = EW.fmtNum(d.passing_grade, 2) + ' or better';
        EW.$('gen-avg').hidden = false;
      }
    }

    if (!d.grades.length) { EW.$('no-grades').hidden = false; return; }
    EW.$('grades-area').hidden = false;

    /* ---- table ---- */
    var head = EW.$('grades-head');
    head.appendChild(h('th', { text: 'Subject' }));
    d.terms.forEach(function (t) { head.appendChild(h('th', { class: 'num', text: t.label })); });
    head.appendChild(h('th', { class: 'num', text: 'Average' }));

    var body = EW.$('grades-body');
    d.grades.forEach(function (g) {
      var tr = h('tr', {}, h('td', { text: g.subject }));
      d.terms.forEach(function (t) {
        var v = g.scores[t.key];
        tr.appendChild(h('td', { class: 'num', text: v === undefined ? '\u2013' : EW.fmtNum(v, 2) }));
      });
      tr.appendChild(h('td', { class: 'num' + (g.failing ? ' text-bad' : '') }, h('strong', { text: EW.fmtNum(g.average, 2) })));
      body.appendChild(tr);
    });
    EW.$('makeup-note').hidden = !s.with_makeup_grades;

    /* ---- charts: plain data goes to the chart component (assets/js/charts.js) ---- */
    EW.charts.subjectBar(EW.$('chart-bar'), {
      labels: d.grades.map(function (g) { return g.subject; }),
      values: d.grades.map(function (g) { return Number(EW.fmtNum(g.average, 2)); }),
      pass: d.passing_grade,
      margin: d.borderline_margin
    });
    EW.charts.trendLine(EW.$('chart-line'), {
      labels: d.terms.map(function (t) { return t.label; }),
      datasets: d.grades.map(function (g) {
        return {
          label: g.subject,
          data: d.terms.map(function (t) { return g.scores[t.key] === undefined ? null : g.scores[t.key]; })
        };
      }),
      pass: d.passing_grade
    });
  }

  EW.page.start({ role: 'student', active: 'student.html' }).then(function () {
    return EW.api.get('student_dashboard.php');
  }).then(render, EW.page.fail);
})();
