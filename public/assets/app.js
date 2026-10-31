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

  /* ---------- Zähler-Formular: analog/digital, Register-Zeilen, eindeutige OBIS ---------- */
  var regs = $('#regs');
  if (regs) {
    var tariffs = JSON.parse(regs.getAttribute('data-tariffs'));
    var kind = $('#kind'), analogBox = $('#analogbox'), digitalBox = $('#digitalbox'), errBox = $('#obiserr');
    var idx = $$('.regrow', regs).length;

    var setDisabled = function (box, dis) {
      $$('input, select', box).forEach(function (el) { el.disabled = dis; });
    };
    var syncKind = function () {
      var analog = kind.value === 'analog';
      analogBox.hidden = !analog; digitalBox.hidden = analog;
      setDisabled(analogBox, !analog); setDisabled(digitalBox, analog);
    };
    kind.addEventListener('change', syncKind);
    syncKind();

    var obisValues = function () {
      return $$('[name$="[obis]"]', regs).map(function (i) { return i.value.trim().toLowerCase(); });
    };
    var findDuplicate = function () {
      var seen = {}, dup = null;
      obisValues().forEach(function (v) { if (v === '') { return; } if (seen[v]) { dup = v; } seen[v] = true; });
      return dup;
    };
    var markDuplicates = function () {
      var counts = {};
      obisValues().forEach(function (v) { if (v) { counts[v] = (counts[v] || 0) + 1; } });
      $$('[name$="[obis]"]', regs).forEach(function (i) {
        var v = i.value.trim().toLowerCase();
        i.classList.toggle('is-invalid', v !== '' && counts[v] > 1);
      });
      var d = findDuplicate();
      errBox.hidden = !d;
      errBox.textContent = d ? 'Die OBIS-Kennzahl „' + d + '“ ist mehrfach vorhanden – jede Kennzahl darf nur einmal vorkommen.' : '';
      return !d;
    };
    regs.addEventListener('input', function (e) { if (e.target.name && /\[obis\]$/.test(e.target.name)) { markDuplicates(); } });

    var addRow = function (obis, label, tariff) {
      if (obis && obisValues().indexOf(obis.toLowerCase()) !== -1) { markDuplicates(); return; } // schon vorhanden
      var row = document.createElement('div');
      row.className = 'regrow';
      var opts = Object.keys(tariffs).map(function (k) {
        return '<option value="' + k + '"' + (k === tariff ? ' selected' : '') + '>' + tariffs[k] + '</option>';
      }).join('');
      row.innerHTML =
        '<input type="hidden" name="reg[' + idx + '][id]" value="0">' +
        '<input name="reg[' + idx + '][obis]" class="form-control" placeholder="OBIS z. B. 1.8.1" aria-label="OBIS-Kennzahl">' +
        '<input name="reg[' + idx + '][label]" class="form-control" placeholder="Bezeichnung z. B. Hochtarif" aria-label="Bezeichnung">' +
        '<select name="reg[' + idx + '][tariff]" class="form-select" aria-label="Abrechnung">' + opts + '</select>' +
        '<button type="button" class="btn btn-outline-danger" data-remove-row title="Register entfernen"><i class="bi bi-x-lg"></i></button>';
      row.querySelector('[name$="[obis]"]').value = obis || '';
      row.querySelector('[name$="[label]"]').value = label || '';
      regs.appendChild(row);
      idx++;
      markDuplicates();
    };
    $('#addreg').addEventListener('click', function () { addRow('', '', 'ET'); });
    regs.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-remove-row]');
      if (btn && $$('.regrow', regs).length > 1) { btn.closest('.regrow').remove(); markDuplicates(); }
    });
    $$('[data-preset]').forEach(function (b) {
      b.addEventListener('click', function () {
        var p = b.getAttribute('data-preset');
        // leere Standardzeile ersetzen, wenn noch nichts eingetragen ist
        var rows = $$('.regrow', regs);
        if (rows.length === 1 && $('[name$="[obis]"]', rows[0]).value.trim() === '' && $('[name$="[label]"]', rows[0]).value.trim() === '') { rows[0].remove(); }
        if (p === 'single') { addRow('1.8.0', 'Bezug', 'ET'); }
        if (p === 'multi') { addRow('1.8.1', 'Hochtarif (HT)', 'HT'); addRow('1.8.2', 'Niedertarif (NT)', 'NT'); }
        if (p === 'export') { addRow('2.8.0', 'Einspeisung', 'EXPORT'); }
      });
    });
    $('#meterform').addEventListener('submit', function (e) {
      if (kind.value === 'digital' && !markDuplicates()) { e.preventDefault(); errBox.scrollIntoView({ block: 'center' }); }
    });
  }

  /* ---------- Keine Browser-Vorschläge in Eingabefeldern ---------- */
  $$('form').forEach(function (f) { f.setAttribute('autocomplete', 'off'); });
  $$('input, textarea').forEach(function (el) {
    var t = (el.getAttribute('type') || 'text').toLowerCase();
    if (t === 'password' || t === 'hidden' || t === 'checkbox' || t === 'radio' || t === 'file' || t === 'submit' || t === 'button') { return; }
    if (el.getAttribute('autocomplete') === 'one-time-code') { return; }
    el.setAttribute('autocomplete', 'off');
  });

  /* ---------- kWh-Felder: ganze Zahlen mit Tausenderpunkten ---------- */
  var fmtKwh = function (digits) { return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
  $$('input[data-kwh]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var v = inp.value, caret = inp.selectionStart, before = v.slice(0, caret).replace(/\D/g, '').length;
      var comma = v.indexOf(',');
      if (comma >= 0) { v = v.slice(0, comma); }               // Nachkommastellen verwerfen
      var digits = v.replace(/\D/g, '').replace(/^0+(?=\d)/, '');
      var out = fmtKwh(digits);
      if (out === inp.value) { return; }
      inp.value = out;
      var pos = 0, seen = 0;                                    // Cursor hinter dieselbe Ziffer setzen
      while (pos < out.length && seen < before) { if (/\d/.test(out[pos])) { seen++; } pos++; }
      try { inp.setSelectionRange(pos, pos); } catch (e) {}
    });
    inp.addEventListener('paste', function (e) {
      var t = (e.clipboardData || window.clipboardData).getData('text');
      if (!t) { return; }
      e.preventDefault();
      t = t.trim();
      var n;
      if (t.indexOf(',') >= 0) { n = parseFloat(t.replace(/\./g, '').replace(',', '.')); }
      else if (/^\d{1,3}(\.\d{3})+$/.test(t)) { n = parseFloat(t.replace(/\./g, '')); }
      else { n = parseFloat(t.replace(/[^\d.\-]/g, '')); }
      inp.value = isFinite(n) && n >= 0 ? fmtKwh(String(Math.round(n))) : '';
      inp.dispatchEvent(new Event('input', { bubbles: true }));
    });
    inp.value = inp.value ? fmtKwh(inp.value.replace(/\D/g, '')) : '';
  });

  /* ---------- Vertragsformular: Preise passend zum Zähler ---------- */
  var cMeter = document.getElementById('contract_meter');
  if (cMeter) {
    var syncPrices = function () {
      var opt = cMeter.options[cMeter.selectedIndex];
      var t = ((opt && opt.getAttribute('data-tariffs')) || '').split(',');
      var has = function (x) { return t.indexOf(x) >= 0; };
      var multi = has('HT') || has('NT'), single = has('ET') || (!multi);
      var show = { price_et: single, price_ht: multi, price_nt: multi, feedin: has('EXPORT') };
      $$('[data-price]').forEach(function (box) {
        var on = !!show[box.getAttribute('data-price')];
        box.hidden = !on;
        $$('input', box).forEach(function (i) { i.disabled = !on; });
      });
      var hint = document.getElementById('pricehint');
      if (hint) { hint.textContent = multi ? 'Zähler mit HT/NT: Hoch- und Niedertarifpreis erforderlich.' : 'Zähler mit Eintarif: nur ein Arbeitspreis erforderlich.'; }
    };
    cMeter.addEventListener('change', syncPrices);
    syncPrices();
  }

  // Vertrag: weitere Gutschrift-Zeile
  var addCredit = document.getElementById('addcredit');
  if (addCredit) {
    addCredit.addEventListener('click', function () {
      var box = document.getElementById('credits'), rows = box.querySelectorAll('.creditrow');
      var clone = rows[rows.length - 1].cloneNode(true), idx = rows.length;
      clone.querySelectorAll('input,select').forEach(function (el) {
        el.name = el.name.replace(/credit\[\d+\]/, 'credit[' + idx + ']');
        if (el.tagName === 'SELECT') { el.selectedIndex = 0; } else { el.value = ''; }
      });
      box.appendChild(clone);
    });
  }

  /* ---------- Dashboard-Diagramme ---------- */
  var usageCanvas = $('#usageChart');
  var costCanvas = $('#costChart');
  if (!usageCanvas && !costCanvas) { return; }

  var COLORS = { ET: '#2563eb', HT: '#ea580c', NT: '#0891b2', EXPORT: '#16a34a', OTHER: '#7c8794' };
  var SHADES = [1, 0.62, 0.4];
  var usageChart, costChart, reloaders = [];
  document.addEventListener('mb-theme', function () { reloaders.forEach(function (f) { f(); }); });

  var waitForChart = function (cb) {
    if (window.Chart) { return cb(); }
    setTimeout(function () { waitForChart(cb); }, 100);
  };
  var textColor = function () { return getComputedStyle(document.body).color; };
  var gridColor = function () { return 'rgba(128,128,128,.25)'; };
  var fmt = function (v, unit) {
    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: unit === '€' ? 2 : 0 }).format(v) + ' ' + unit;
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
    var refYear = $('#refyear'), refCy = $('#refcy');
    years.forEach(function (y) { refCy.insertAdjacentHTML('beforeend', '<option value="' + y.index + '">' + y.label + '</option>'); });
    if (years.length) { refCy.value = years[years.length - 1].index; }

    var loadUsage = function () {
      var p = { type: 'usage', meter: ctl.getAttribute('data-meter'), range: state.range, contract: ctl.getAttribute('data-contract') };
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
        if (d.target) {
          sets.push({ type: 'line', label: d.target_label, data: d.labels.map(function () { return d.target; }), borderColor: '#f59e0b', backgroundColor: '#f59e0b',
            borderDash: [6, 4], borderWidth: 2, pointRadius: 0, pointHoverRadius: 0, fill: false, order: 0, stack: 'target', _any: false });
        }
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
              y: { stacked: true, beginAtZero: true, title: { display: true, text: d.unit, color: textColor() }, ticks: { color: textColor(), callback: function (v) { return new Intl.NumberFormat('de-DE').format(v); } }, grid: { color: gridColor() } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: textColor(), usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16 } },
              tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y, d.unit); } } }
            }
          }
        });
      });
    };
    var syncControls = function () {
      refYear.hidden = state.range !== 'year';
      refCy.hidden = state.range !== 'contract';
      $$('[data-range]', ctl).forEach(function (b) {
        b.className = 'btn btn-sm ' + (b.getAttribute('data-range') === state.range ? 'btn-primary' : 'btn-outline-primary');
      });
    };
    $$('[data-range]', ctl).forEach(function (b) {
      b.addEventListener('click', function () { state.range = b.getAttribute('data-range'); syncControls(); loadUsage(); });
    });
    [refYear, refCy].forEach(function (el) { el.addEventListener('change', loadUsage); });
    syncControls();
    reloaders.push(function () { if (usageChart) { loadUsage(); } });
    waitForChart(loadUsage);
  }

  /* Kosten */
  var cctl = $('#costctl');
  if (cctl && costCanvas) {
    var cyears = JSON.parse(cctl.getAttribute('data-years') || '[]');
    var sel = $('#costcy');
    cyears.forEach(function (y) { sel.insertAdjacentHTML('beforeend', '<option value="' + y.index + '">' + y.label + '</option>'); });
    if (cyears.length) { sel.value = cyears[cyears.length - 1].index; }
    var PAL = ['#2563eb', '#7aa7f5', '#16a34a', '#7bd3a0'];
    var loadCost = function () {
      get(cctl.getAttribute('data-api'), { type: 'cost', contract: cctl.getAttribute('data-contract'), cy: sel.value }).then(function (d) {
        $('#costtitle').textContent = d.title || '';
        var sets = d.datasets.map(function (s, i) {
          return { label: s.label, data: s.data, borderColor: PAL[i], backgroundColor: PAL[i], borderDash: s.dashed ? [6, 5] : [], tension: 0.15, spanGaps: false, pointRadius: s.dashed ? 2 : 3, borderWidth: 2.5 };
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
              y: { beginAtZero: true, title: { display: true, text: '€', color: textColor() }, ticks: { color: textColor(), callback: function (v) { return new Intl.NumberFormat('de-DE').format(v); } }, grid: { color: gridColor() } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: textColor(), usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16 } },
              tooltip: { callbacks: { label: function (c) { return c.parsed.y == null ? '' : c.dataset.label + ': ' + fmt(c.parsed.y, '€'); } } }
            }
          }
        });
      });
    };
    sel.addEventListener('change', loadCost);
    reloaders.push(function () { if (costChart) { loadCost(); } });
    waitForChart(loadCost);
  }
})();
