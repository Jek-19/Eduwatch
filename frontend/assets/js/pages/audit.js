/* Audit log (teacher only): who changed which grade, when, and from what to what (api/audit.php). */
(function () {
  'use strict';
  var h = EW.h;
  var q = EW.param('student').slice(0, 10);

  function score(v) { return v === null ? '\u2013' : EW.fmtNum(v, 2); }

  EW.page.start({ role: 'teacher', active: 'audit.html' }).then(function () {
    EW.$('student').value = q;
    EW.$('clear-filter').hidden = q === '';
    return EW.api.get('audit.php', { student: q });
  }).then(function (d) {
    var body = EW.clear(EW.$('audit-body'));
    if (!d.rows.length) {
      body.appendChild(h('tr', {}, h('td', { colspan: '7', class: 'muted', text: 'Nothing has been logged yet.' })));
    }
    d.rows.forEach(function (r) {
      var what = r.detail ? r.detail : (r.subject || '') + (r.term_label ? ' \u00b7 ' + r.term_label : '');
      body.appendChild(h('tr', {},
        h('td', { text: r.when }),
        h('td', { text: r.by }),
        h('td', { text: r.action_label }),
        h('td', {}, r.student_id, r.last_name ? h('span', { class: 'muted', text: ' (' + r.last_name + ')' }) : null),
        h('td', { text: what }),
        h('td', { class: 'num', text: score(r.old_score) }),
        h('td', { class: 'num', text: score(r.new_score) })));
    });
    EW.$('loading').hidden = true;
    EW.$('app').hidden = false;
  }).catch(EW.page.fail);
})();
