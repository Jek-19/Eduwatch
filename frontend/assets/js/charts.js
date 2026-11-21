/*
 * Chart component wrapper (Deliverable 10 - data visualization).
 * Chart.js (third-party, bundled in js/vendor/) is used ONLY here. The pages never call Chart.js directly:
 * they hand plain data to EW.charts.* and get a chart back. To swap the chart library, only this file changes.
 *
 * Grading scale: 1.00 = highest, 5.00 = lowest. The grade axis is drawn upside down (1 at the top) so that a
 * better grade always looks higher on the chart. "pass" is the passing grade (3.00) and "margin" the borderline band.
 *
 *   EW.charts.subjectBar(canvas,   {labels, values, pass, margin})
 *   EW.charts.trendLine(canvas,    {labels, datasets: [{label, data}], pass})
 *   EW.charts.riskDoughnut(canvas, {labels, values})
 */
(function (w, d) {
  'use strict';
  var EW = w.EW = w.EW || {};
  var PALETTE = ['#2f6fed', '#e0852b', '#1f9d6b', '#a55eea', '#d64550', '#14a7b8', '#8c8c3d', '#d6479c'];

  /* Colours come from the CSS variables, so charts follow light / dark mode. Read when a chart is drawn. */
  function theme() {
    var root = getComputedStyle(d.documentElement);
    function v(name, fallback) { var x = root.getPropertyValue(name).trim(); return x || fallback; }
    return {
      text: v('--text', '#1d2433'), grid: v('--border', '#dde3ee'),
      bad: v('--bad', '#c4303d'), good: v('--good', '#18815a'), warn: v('--warn', '#b45f0a'), accent: v('--primary', '#5b3fd6')
    };
  }

  function ready() {
    if (typeof w.Chart === 'undefined') { return false; }
    var t = theme();
    w.Chart.defaults.color = t.text;
    w.Chart.defaults.borderColor = t.grid;
    w.Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    return true;
  }

  function same(labels, value) { return (labels || []).map(function () { return value; }); }

  var GRADE_MIN = 1, GRADE_MAX = 5;
  function fmt2(n) { return Number(n).toFixed(2); }

  EW.charts = {
    available: function () { return typeof w.Chart !== 'undefined'; },

    subjectBar: function (canvas, cfg) {
      if (!ready()) { return null; }
      var t = theme(), pass = cfg.pass || 3, margin = cfg.margin || 0.5;
      return new w.Chart(canvas, {
        data: {
          labels: cfg.labels,
          datasets: [
            {
              /* bars grow up from the bottom (grade 5) to the grade, so a taller bar = a better grade */
              type: 'bar', label: 'Average', data: cfg.values, borderRadius: 4, base: GRADE_MAX, minBarLength: 4,
              backgroundColor: (cfg.values || []).map(function (v) { return v > pass ? t.bad : (v > pass - margin ? t.warn : t.good); })
            },
            {
              type: 'line', label: 'Passing (' + fmt2(pass) + ')', data: same(cfg.labels, pass),
              borderColor: t.text, borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0, fill: false
            }
          ]
        },
        options: {
          maintainAspectRatio: false,
          scales: {
            y: {
              reverse: true, min: GRADE_MIN, max: GRADE_MAX, ticks: { stepSize: 0.5, callback: function (v) { return fmt2(v); } },
              title: { display: true, text: 'Grade (1.00 = highest, 5.00 = lowest)' }
            }
          },
          plugins: { legend: { position: 'bottom' } }
        }
      });
    },

    trendLine: function (canvas, cfg) {
      if (!ready()) { return null; }
      var t = theme(), pass = cfg.pass || 3;
      var sets = (cfg.datasets || []).map(function (ds, i) {
        var c = i === 0 ? t.accent : PALETTE[i % PALETTE.length];   // first line uses the accent colour
        /* clip:false so a point on the 1.00 / 5.00 edge of the chart is not cut in half */
        return { label: ds.label, data: ds.data, borderColor: c, backgroundColor: c, tension: 0.25, spanGaps: true, pointRadius: 3, clip: false };
      });
      sets.push({
        label: 'Passing (' + fmt2(pass) + ')', data: same(cfg.labels, pass),
        borderColor: t.text, borderDash: [6, 4], borderWidth: 1.5, pointRadius: 0
      });
      return new w.Chart(canvas, {
        type: 'line',
        data: { labels: cfg.labels, datasets: sets },
        options: {
          maintainAspectRatio: false,
          scales: {
            y: {
              reverse: true, min: GRADE_MIN, max: GRADE_MAX, ticks: { stepSize: 0.5, callback: function (v) { return fmt2(v); } },
              title: { display: true, text: 'Grade (1.00 = highest, 5.00 = lowest)' }
            }
          },
          plugins: { legend: { position: 'bottom' } }
        }
      });
    },

    riskDoughnut: function (canvas, cfg) {
      if (!ready()) { return null; }
      var t = theme();
      return new w.Chart(canvas, {
        type: 'doughnut',
        data: { labels: cfg.labels, datasets: [{ data: cfg.values, backgroundColor: [t.bad, t.warn, t.good, '#8a93a6'], borderWidth: 0 }] },
        options: { maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
      });
    }
  };
})(window, document);
