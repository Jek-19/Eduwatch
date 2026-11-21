/* Teacher dashboard: student list, class-wide at-risk list and risk summary from api/teacher_dashboard.php. */
(function () {
  'use strict';
  var h = EW.h;
  var q = EW.param('q').slice(0, 60);
  var riskChart = null;

  function render(d) {
    EW.$('loading').hidden = true;
    if (!d.semester) { EW.$('no-semester').hidden = false; return; }
    EW.$('app').hidden = false;
    EW.$('sem-meta').textContent = d.semester.label + ' \u00b7 passing grade ' + EW.fmtNum(d.passing_grade, 2) + ' (1 = highest, 5 = lowest)';

    if (d.new_account) { // shown only once; the server forgets it after sending
      EW.$('na-name').textContent = d.new_account.name;
      EW.$('na-id').textContent = d.new_account.id;
      EW.$('na-pw').textContent = d.new_account.password;
      EW.$('new-account').hidden = false;
    }

    EW.$('n-total').textContent = d.total;
    EW.$('n-high').textContent = d.counts.high;
    EW.$('n-medium').textContent = d.counts.medium;
    EW.$('n-low').textContent = d.counts.low;

    /* ---- at-risk list ---- */
    var list = EW.clear(EW.$('risk-list'));
    EW.$('no-risk').hidden = d.at_risk.length > 0;
    d.at_risk.forEach(function (a) {
      list.appendChild(h('li', {},
        h('div', { class: 'risk-list-head' },
          h('a', { href: 'grades.html?student=' + encodeURIComponent(a.student_id) }, h('strong', { text: a.last_name + ', ' + a.first_name })),
          h('span', { class: 'muted', text: a.student_id }),
          EW.riskBadge(a.risk_level, a.risk_label)),
        a.reasons.length ? h('div', { class: 'muted small', text: a.reasons.join(' ') }) : null));
    });

    /* ---- doughnut chart ---- */
    if (riskChart) { riskChart.destroy(); }
    riskChart = EW.charts.riskDoughnut(EW.$('chart-risk'), {
      labels: ['High', 'Medium', 'Low', 'No data'],
      values: [d.counts.high, d.counts.medium, d.counts.low, d.counts.nodata]
    });

    /* ---- student table ---- */
    var body = EW.clear(EW.$('students-body'));
    if (!d.students.length) {
      body.appendChild(h('tr', {}, h('td', { colspan: '7', class: 'muted', text: 'No students found.' })));
    }
    d.students.forEach(function (s) {
      var id = encodeURIComponent(s.student_id);
      body.appendChild(h('tr', {},
        h('td', { text: s.student_id }),
        h('td', { text: s.last_name }),
        h('td', { text: s.first_name }),
        h('td', { text: s.with_makeup_grades ? 'Yes' : 'No' }),
        h('td', { class: 'num', text: s.general_avg === null ? '\u2013' : EW.fmtNum(s.general_avg, 2) }),
        h('td', {}, EW.riskBadge(s.risk_level, s.risk_label)),
        h('td', { class: 'actions' },
          h('a', { class: 'btn btn-small', href: 'grades.html?student=' + id, text: 'Grades' }), ' ',
          h('a', { class: 'btn btn-small', href: 'student_qr.html?student=' + id, text: 'QR' }))));
    });
  }

  function load() { return EW.api.get('teacher_dashboard.php', { q: q }).then(render); }

  EW.page.start({ role: 'teacher', active: 'teacher.html' }).then(function () {
    EW.$('q').value = q;
    EW.$('clear-search').hidden = q === '';
    return load();
  }).catch(EW.page.fail);

  EW.$('recalc').addEventListener('click', function () {
    var btn = EW.$('recalc'), alerts = EW.$('alerts');
    EW.clear(alerts);
    EW.busy(btn, true);
    EW.api.post('predict.php', {}).then(function (res) {
      return load().then(function () { EW.showAlert(alerts, 'success', res.message); });
    }).catch(function (err) {
      if (!EW.api.handleAuthError(err)) { EW.showAlert(alerts, 'error', err.message); }
    }).then(function () { EW.busy(btn, false); });
  });
})();
