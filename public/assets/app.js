/* meterio by MeckByte – Vanilla JS (keine Build-Tools) */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- Bestätigungsdialoge ---------- */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    var sub = e.submitter;
    if (sub && sub.getAttribute('data-confirm') && !window.confirm(sub.getAttribute('data-confirm'))) { e.preventDefault(); }
  });
  $$('[data-autosubmit]').forEach(function (el) { el.addEventListener('change', function () { el.form.submit(); }); });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = $(b.getAttribute('data-copy'));
      if (!t || !navigator.clipboard) { return; }
      var txt = $$('li', t).map(function (li) { return li.textContent.trim(); }).join('\n');
      navigator.clipboard.writeText(txt).then(function () { b.textContent = 'Kopiert ✓'; });
    });
  });

  /* ---------- QR-Code (2FA) ---------- */
  var qr = $('#qr');
  if (qr) {
    var draw = function () {
      if (!window.qrcode) { return setTimeout(draw, 100); }
      var q = window.qrcode(0, 'M');
      q.addData(qr.getAttribute('data-uri'));
      q.make();
      qr.innerHTML = q.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
    };
    draw();
  }

  /* ---------- Zähler-Formular: Register-Zeilen ---------- */
  var regs = $('#regs');
  if (regs) {
    var tariffs = JSON.parse(regs.getAttribute('data-tariffs'));
    var idx = $$('.regrow', regs).length;
    var addRow = function (obis, label, tariff) {
      var row = document.createElement('div');
      row.className = 'regrow';
      var opts = Object.keys(tariffs).map(function (k) {
        return '<option value="' + k + '"' + (k === tariff ? ' selected' : '') + '>' + tariffs[k] + '</option>';
      }).join('');
      row.innerHTML =
        '<input type="hidden" name="reg[' + idx + '][id]" value="0">' +
        '<input name="reg[' + idx + '][obis]" placeholder="OBIS z. B. 1.8.1" aria-label="OBIS-Kennzahl">' +
        '<input name="reg[' + idx + '][label]" placeholder="Bezeichnung z. B. Hochtarif" aria-label="Bezeichnung">' +
        '<select name="reg[' + idx + '][tariff]" aria-label="Abrechnung">' + opts + '</select>' +
        '<button type="button" class="secondary outline small" data-remove-row title="Register entfernen">✕</button>';
      row.querySelector('[name$="[obis]"]').value = obis || '';
      row.querySelector('[name$="[label]"]').value = label || '';
      regs.appendChild(row);
      idx++;
    };
    $('#addreg').addEventListener('click', function () { addRow('', '', 'ET'); });
    regs.addEventListener('click', function (e) {
      if (e.target.hasAttribute('data-remove-row')) {
        if ($$('.regrow', regs).length > 1) { e.target.closest('.regrow').remove(); }
      }
    });
    $$('[data-preset]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = b.getAttribute('data-preset');
        if (p === 'single') { addRow('1.8.0', 'Bezug', 'ET'); }
        if (p === 'multi') { addRow('1.8.1', 'Hochtarif (HT)', 'HT'); addRow('1.8.2', 'Niedertarif (NT)', 'NT'); }
        if (p === 'export') { addRow('2.8.0', 'Einspeisung', 'EXPORT'); }
      });
    });
  }

  /* ---------- Dashboard-Diagramme ---------- */
  var usageCanvas = $('#usageChart');
  var costCanvas = $('#costChart');
  if (!usageCanvas && !costCanvas) { return; }

  var COLORS = { ET: '#2563eb', HT: '#ea580c', NT: '#0891b2', EXPORT: '#16a34a', OTHER: '#7c8794' };
  var SHADES = [1, 0.62, 0.4];
  var usageChart, costChart;

  var waitForChart = function (cb) {
    if (window.Chart) { return cb(); }
    setTimeout(function () { waitForChart(cb); }, 100);
  };
  var textColor = function () { return getComputedStyle(document.body).color; };
  var gridColor = function () { return 'rgba(128,128,128,.25)'; };
  var fmt = function (v, unit) {
    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: unit === '€' ? 2 : 1 }).format(v) + ' ' + unit;
  };
  var get = function (api, params) {
    var qs = Object.keys(params).filter(function (k) { return params[k] !== '' && params[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
    return fetch(api + '?' + qs, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); });
  };
  var withAlpha = function (hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
  };

  /* Verbrauch */
  var ctl = $('#usagectl');
  if (ctl) {
    var state = { range: 'year' };
    var years = JSON.parse(ctl.getAttribute('data-years') || '[]');
    var refMonth = $('#refmonth'), refYear = $('#refyear'), refCy = $('#refcy');
    years.forEach(function (y) { refCy.insertAdjacentHTML('beforeend', '<option value="' + y.index + '">' + y.label + '</option>'); });
    if (years.length) { refCy.value = years[years.length - 1].index; }

    var loadUsage = function () {
      var p = { type: 'usage', meter: ctl.getAttribute('data-meter'), range: state.range, contract: ctl.getAttribute('data-contract') };
      if (state.range === 'month') { p.ref = refMonth.value; }
      if (state.range === 'year') { p.ref = refYear.value; }
      if (state.range === 'contract') { p.cy = refCy.value; }
      get(ctl.getAttribute('data-api'), p).then(function (d) {
        $('#usagetitle').textContent = d.title || '';
        var counters = {};
        var sets = d.datasets.map(function (s) {
          var n = counters[s.tariff] = (counters[s.tariff] || 0) + 1;
          var c = COLORS[s.tariff] || COLORS.OTHER;
          return { label: s.label, data: s.data, backgroundColor: withAlpha(c, SHADES[Math.min(n - 1, 2)]), borderRadius: 3, _any: s.any };
        });
        var any = sets.some(function (s) { return s._any; });
        $('#usageempty').hidden = any;
        if (usageChart) { usageChart.destroy(); }
        usageChart = new Chart(usageCanvas, {
          type: 'bar',
          data: { labels: d.labels, datasets: sets },
          options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
              x: { stacked: true, ticks: { color: textColor() }, grid: { display: false } },
              y: { stacked: true, beginAtZero: true, title: { display: true, text: d.unit, color: textColor() }, ticks: { color: textColor() }, grid: { color: gridColor() } }
            },
            plugins: {
              legend: { labels: { color: textColor() } },
              tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y, d.unit); } } }
            }
          }
        });
      });
    };
    var syncControls = function () {
      refMonth.hidden = state.range !== 'month';
      refYear.hidden = state.range !== 'year';
      refCy.hidden = state.range !== 'contract';
      $$('[data-range]', ctl).forEach(function (b) {
        b.className = b.getAttribute('data-range') === state.range ? 'secondary' : 'secondary outline';
      });
    };
    $$('[data-range]', ctl).forEach(function (b) {
      b.addEventListener('click', function () { state.range = b.getAttribute('data-range'); syncControls(); loadUsage(); });
    });
    [refMonth, refYear, refCy].forEach(function (el) { el.addEventListener('change', loadUsage); });
    syncControls();
    waitForChart(loadUsage);
  }

  /* Kosten */
  var cctl = $('#costctl');
  if (cctl && costCanvas) {
    var cyears = JSON.parse(cctl.getAttribute('data-years') || '[]');
    var sel = $('#costcy');
    cyears.forEach(function (y) { sel.insertAdjacentHTML('beforeend', '<option value="' + y.index + '">' + y.label + '</option>'); });
    if (cyears.length) { sel.value = cyears[cyears.length - 1].index; }
    var PAL = ['#2563eb', '#2563eb', '#16a34a', '#16a34a'];
    var loadCost = function () {
      get(cctl.getAttribute('data-api'), { type: 'cost', contract: cctl.getAttribute('data-contract'), cy: sel.value }).then(function (d) {
        $('#costtitle').textContent = d.title || '';
        var sets = d.datasets.map(function (s, i) {
          return { label: s.label, data: s.data, borderColor: PAL[i], backgroundColor: PAL[i], borderDash: s.dashed ? [6, 5] : [], tension: 0.15, spanGaps: false, pointRadius: 3 };
        });
        if (costChart) { costChart.destroy(); }
        costChart = new Chart(costCanvas, {
          type: 'line',
          data: { labels: d.labels, datasets: sets },
          options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
              x: { ticks: { color: textColor() }, grid: { display: false } },
              y: { beginAtZero: true, title: { display: true, text: '€', color: textColor() }, ticks: { color: textColor() }, grid: { color: gridColor() } }
            },
            plugins: {
              legend: { labels: { color: textColor() } },
              tooltip: { callbacks: { label: function (c) { return c.parsed.y == null ? '' : c.dataset.label + ': ' + fmt(c.parsed.y, '€'); } } }
            }
          }
        });
      });
    };
    sel.addEventListener('change', loadCost);
    waitForChart(loadCost);
  }
})();
