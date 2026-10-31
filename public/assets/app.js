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
    var kindRadios = $$('input[name="kind"]'), analogBox = $('#analogbox'), digitalBox = $('#digitalbox'), errBox = $('#obiserr');
    var idx = $$('.regrow', regs).length;

    var setDisabled = function (box, dis) {
      $$('input, select', box).forEach(function (el) { el.disabled = dis; });
    };
    var kindValue = function () { var c = kindRadios.filter(function (r) { return r.checked; })[0]; return c ? c.value : 'digital'; };
    var syncKind = function () {
      var analog = kindValue() === 'analog';
      analogBox.hidden = !analog; digitalBox.hidden = analog;
      setDisabled(analogBox, !analog); setDisabled(digitalBox, analog);
    };
    kindRadios.forEach(function (r) { r.addEventListener('change', syncKind); });
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
      if (kindValue() === 'digital' && !markDuplicates()) { e.preventDefault(); errBox.scrollIntoView({ block: 'center' }); }
    });
  }

  /* ---------- Ablesung: Plausibilität vorab prüfen, bei Warnung per Dialog bestätigen ---------- */
  var rform = document.getElementById('readingform');
  if (rform && window.bootstrap) {
    var force = document.getElementById('force'), modalEl = document.getElementById('plausModal');
    var plausModal = new bootstrap.Modal(modalEl), checking = false;
    var plausList = document.getElementById('plausList');
    var doSubmit = function () { checking = true; try { rform.requestSubmit ? rform.requestSubmit() : rform.submit(); } finally { checking = false; } };
    rform.addEventListener('submit', function (e) {
      if (checking) { return; }                       // bestätigt oder geprüft: normal absenden
      var sub = e.submitter;
      if (sub && sub.hasAttribute('formaction')) { return; }   // z. B. Foto löschen
      e.preventDefault();
      force.value = '';
      var fd = new FormData();
      ['_csrf', 'read_at', 'reason'].forEach(function (n) { var el = rform.elements[n]; if (el) { fd.append(n, el.value); } });
      $$('[name^="val["]', rform).forEach(function (el) { fd.append(el.name, el.value); });
      fd.append('ignore', rform.getAttribute('data-ignore') || '0');
      fetch(rform.getAttribute('data-check'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.warnings && d.warnings.length) {
            plausList.innerHTML = '';
            d.warnings.forEach(function (w) { var li = document.createElement('li'); li.textContent = w; plausList.appendChild(li); });
            plausModal.show();
          } else { doSubmit(); }
        })
        .catch(function () { doSubmit(); });          // Prüfung nicht erreichbar: Server prüft beim Speichern
    });
    document.getElementById('plausSave').addEventListener('click', function () { force.value = '1'; plausModal.hide(); doSubmit(); });
  }

  /* ---------- Fotos: Kamera oder Galerie, mehrere Bilder sammeln ---------- */
  var pick = document.getElementById('photopick');
  if (pick) {
    var inCam = $('#in-camera'), inFiles = $('#in-files'), thumbs = $('#newthumbs'), hint = $('#photohint');
    var maxFiles = parseInt(pick.getAttribute('data-max'), 10) || 8, maxBytes = (parseInt(pick.getAttribute('data-maxmb'), 10) || 12) * 1048576;
    var picked = [], urls = [];
    var sync = function () {
      var dt = new DataTransfer();
      picked.forEach(function (f) { dt.items.add(f); });
      inFiles.files = dt.files;
      urls.forEach(function (u) { URL.revokeObjectURL(u); }); urls = [];
      thumbs.innerHTML = '';
      picked.forEach(function (f, i) {
        var u = URL.createObjectURL(f); urls.push(u);
        var fig = document.createElement('figure');
        var img = document.createElement('img'); img.src = u; img.alt = 'Neues Foto ' + (i + 1);
        var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-outline-danger btn-sm mt-1 w-100';
        b.innerHTML = '<i class="bi bi-x-lg"></i> Entfernen';
        b.addEventListener('click', function () { picked.splice(i, 1); sync(); });
        fig.appendChild(img); fig.appendChild(b); thumbs.appendChild(fig);
      });
    };
    var add = function (list) {
      var msg = '';
      Array.prototype.forEach.call(list, function (f) {
        if (f.type && f.type.indexOf('image/') !== 0) { msg = 'Nur Bilddateien sind erlaubt.'; return; }
        if (f.size > maxBytes) { msg = '„' + f.name + '“ ist zu groß.'; return; }
        if (picked.length >= maxFiles) { msg = 'Maximal ' + maxFiles + ' Fotos je Speichervorgang.'; return; }
        picked.push(f);
      });
      sync();
      if (msg) { hint.textContent = msg; hint.classList.add('text-danger'); } else { hint.classList.remove('text-danger'); }
    };
    /* Kamera direkt im Browser (getUserMedia); fällt auf die native Kamera-Auswahl zurück */
    var layer = $('#camlayer'), video = $('#camvideo'), stream = null;
    var stopCam = function () {
      if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
      video.srcObject = null; layer.hidden = true; document.body.classList.remove('overflow-hidden');
    };
    $('#btn-camera').addEventListener('click', function () {
      if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext)) { inCam.click(); return; }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 2560 }, height: { ideal: 1920 } }, audio: false })
        .then(function (s) {
          stream = s; video.srcObject = s; layer.hidden = false; document.body.classList.add('overflow-hidden');
          var pl = video.play(); if (pl && pl.catch) { pl.catch(function () {}); }
        })
        .catch(function () { inCam.click(); });
    });
    $('#cam-cancel').addEventListener('click', stopCam);
    $('#cam-shot').addEventListener('click', function () {
      var w = video.videoWidth, h = video.videoHeight;
      if (!w || !h) { return; }
      var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
      cv.getContext('2d').drawImage(video, 0, 0, w, h);
      cv.toBlob(function (blob) {
        if (blob) { add([new File([blob], 'zaehler-' + Date.now() + '.jpg', { type: 'image/jpeg' })]); }
        stopCam();
      }, 'image/jpeg', 0.92);
    });
    $('#btn-gallery').addEventListener('click', function () { inFiles.click(); });
    inCam.addEventListener('change', function () { add(inCam.files); inCam.value = ''; });
    inFiles.addEventListener('change', function () {
      // native Auswahl ersetzt die Liste -> vorherige Fotos behalten und neue ergänzen
      var chosen = Array.prototype.slice.call(inFiles.files);
      var known = picked.slice();
      var fresh = chosen.filter(function (f) { return known.indexOf(f) < 0; });
      add(fresh);
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

  /* ---------- Profil: Tag-Feld nur bei „Fester Tag im Monat“ ---------- */
  (function () {
    var day = document.getElementById('remday');
    if (!day) { return; }
    $$('#remmodes input[name="reminder_mode"]').forEach(function (r) {
      r.addEventListener('change', function () { day.hidden = !(r.checked && r.value === 'monthly'); });
    });
  })();

  /* ---------- Info-Popover (Hinweise hinter Feldbezeichnungen) ---------- */
  if (window.bootstrap) { $$('[data-bs-toggle="popover"]').forEach(function (el) { new bootstrap.Popover(el); }); }

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
      if (hint) {
        var ht = (multi ? 'Zähler mit HT/NT: Hoch- und Niedertarifpreis erforderlich.' : 'Zähler mit Eintarif: nur ein Arbeitspreis erforderlich.') + ' Preise mit bis zu 6 Nachkommastellen möglich.';
        hint.setAttribute('data-bs-content', ht); hint.setAttribute('aria-label', 'Hinweis: ' + ht);
        var pi = window.bootstrap && bootstrap.Popover.getInstance(hint);
        if (pi) { pi.setContent({ '.popover-body': ht }); }
      }
    };
    cMeter.addEventListener('change', syncPrices);
    syncPrices();
  }

  // Vertrag: Preisänderungen (Zeilen aus Vorlage)
  (function () {
    var add = document.getElementById('addpc'), box = document.getElementById('pcrows'), tpl = document.getElementById('pc-tpl');
    if (!add || !box || !tpl) { return; }
    var empty = document.getElementById('pc-empty');
    var counter = box.querySelectorAll('.pcrow').length;
    var sync = function () {
      if (empty) { empty.hidden = box.querySelectorAll('.pcrow').length > 0; }
      var m = document.getElementById('contract_meter');
      if (m) { m.dispatchEvent(new Event('change')); }   // Preisfelder passend zum Zähler ein-/ausblenden
    };
    add.addEventListener('click', function () {
      var wrap = document.createElement('div');
      wrap.innerHTML = tpl.innerHTML.replace(/__I__/g, String(counter++));
      var row = wrap.firstElementChild;
      box.appendChild(row);
      sync();
      var f = row.querySelector('input[type=date]'); if (f) { f.focus(); }
    });
    box.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-pc-remove]');
      if (!btn) { return; }
      var row = btn.closest('.pcrow'); if (row) { row.remove(); sync(); }
    });
  })();

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

  /* Vertragsansicht: Abrechnungszeitraum wählen (Reiter bleibt erhalten) und Reiter über #hash merken */
  (function () {
    var sel = $('#period-sel');
    if (sel) {
      sel.addEventListener('change', function () {
        location.href = sel.form.getAttribute('action') + '?period=' + encodeURIComponent(sel.value) + (location.hash || '');
      });
    }
    $$('.period-nav').forEach(function (a) { a.addEventListener('click', function () { if (a.href && location.hash) { a.href = a.href.split('#')[0] + location.hash; } }); });
    var tabs = $$('#contract-tabs [data-bs-toggle="tab"]');
    if (!tabs.length) { return; }
    var map = { '#zahlungen': 'tab-pay', '#abrechnung': 'tab-settle' };
    var want = map[location.hash];
    if (want && window.bootstrap) { var el = document.getElementById(want); if (el) { new bootstrap.Tab(el).show(); } }
    tabs.forEach(function (t) {
      t.addEventListener('shown.bs.tab', function () {
        if (history.replaceState) { history.replaceState(null, '', t.id === 'tab-settle' ? '#abrechnung' : '#zahlungen'); }
      });
    });
  })();

  /* ---------- Zähler-Statistik ---------- */
  (function () {
    var cy = $('#statYears'), cm = $('#statMonths');
    if (!cy && !cm) { return; }
    var COL = { ET: '#2563eb', HT: '#ea580c', NT: '#0891b2' };
    var YC = ['#7c8794', '#0891b2', '#ea580c', '#7c3aed', '#16a34a', '#2563eb'];
    var charts = [];
    var nf = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });
    var txt = function () { return getComputedStyle(document.body).color; };
    var draw = function () {
      charts.forEach(function (c) { c.destroy(); }); charts = [];
      var opts = function (extra) {
        return Object.assign({ responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
          plugins: { legend: { labels: { color: txt() } }, tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + nf.format(c.parsed.y) + ' kWh'; } } } },
          scales: { x: { ticks: { color: txt() }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: txt() }, grid: { color: 'rgba(128,128,128,.25)' }, title: { display: true, text: 'kWh', color: txt() } } } }, extra || {});
      };
      if (cy) {
        var years = JSON.parse(cy.getAttribute('data-years') || '[]'), tariffs = JSON.parse(cy.getAttribute('data-tariffs') || '[]');
        var ds = tariffs.map(function (t) {
          return { label: t === 'ET' ? 'Verbrauch' : 'Verbrauch ' + t, backgroundColor: COL[t] || '#7c8794', stack: 's', data: years.map(function (y) { return Math.round(y.by[t] || 0); }) };
        });
        ds.push({ label: 'Hochrechnung bis Jahresende', backgroundColor: 'rgba(37,99,235,.18)', borderColor: '#2563eb', borderWidth: 1, borderDash: [4, 3], stack: 's',
          data: years.map(function (y) { return y.proj !== null ? Math.max(0, Math.round(y.proj - y.kwh)) : 0; }) });
        if (years.some(function (y) { return y.export > 0; })) {
          ds.push({ label: 'Einspeisung', backgroundColor: '#16a34a', stack: 'e', data: years.map(function (y) { return Math.round(y.export); }) });
        }
        charts.push(new Chart(cy, { type: 'bar', data: { labels: years.map(function (y) { return String(y.y); }), datasets: ds }, options: opts({ scales: { x: { stacked: true, ticks: { color: txt() }, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { color: txt() }, grid: { color: 'rgba(128,128,128,.25)' }, title: { display: true, text: 'kWh', color: txt() } } } }) }));
      }
      if (cm) {
        var months = JSON.parse(cm.getAttribute('data-months') || '{}'), keys = Object.keys(months).sort();
        charts.push(new Chart(cm, { type: 'line', data: { labels: ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'],
          datasets: keys.map(function (k, i) { var c = YC[(YC.length - keys.length + i + YC.length) % YC.length]; return { label: k, data: months[k], borderColor: c, backgroundColor: c, tension: .25, pointRadius: 3, spanGaps: false }; }) }, options: opts() }));
      }
    };
    var wait = function () { if (window.Chart) { draw(); } else { setTimeout(wait, 100); } };
    wait();
    document.addEventListener('mb-theme', function () { if (window.Chart) { draw(); } });
  })();

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
    return new Intl.NumberFormat('de-DE', { minimumFractionDigits: unit === '€' ? 2 : 0, maximumFractionDigits: unit === '€' ? 2 : 0 }).format(v) + ' ' + unit;
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
    var years = JSON.parse(ctl.getAttribute('data-years') || '[]');
    var state = { range: ctl.querySelector('[data-range="contract"]') && years.length ? 'contract' : 'year' };
    var refYear = $('#refyear'), refCy = $('#refcy');
    years.forEach(function (y) { refCy.insertAdjacentHTML('beforeend', '<option value="' + y.index + '">' + y.label + '</option>'); });
    if (years.length) { refCy.value = years[years.length - 1].index; }
    var nfmt = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 });
    var TARIFF_NAME = { ET: 'Verbrauch', HT: 'Verbrauch HT', NT: 'Verbrauch NT', EXPORT: 'Einspeisung' };

    /* Summenwerte über den Balken (ohne Zusatz-Bibliothek) */
    var totalsPlugin = {
      id: 'mbTotals',
      afterDatasetsDraw: function (chart) {
        var n = chart.data.labels.length, w = chart.chartArea.right - chart.chartArea.left;
        if (w / n < 30) { return; }
        var ctx = chart.ctx, y = chart.scales.y, meta = chart.getDatasetMeta(0);
        ctx.save();
        ctx.font = '600 ' + (w / n < 40 ? 10 : 11) + 'px system-ui, sans-serif';
        ctx.fillStyle = textColor(); ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
        for (var i = 0; i < n; i++) {
          var sum = 0, has = false;
          chart.data.datasets.forEach(function (ds, di) {
            if (ds.type === 'line' || ds.tariff === 'EXPORT' || !chart.isDatasetVisible(di)) { return; }
            if (ds.data[i] != null) { sum += ds.data[i]; has = true; }
          });
          if (has && sum > 0 && meta.data[i]) { ctx.fillText(nfmt.format(sum), meta.data[i].x, y.getPixelForValue(sum) - 4); }
        }
        ctx.restore();
      }
    };

    var loadUsage = function () {
      var p = { type: 'usage', meter: ctl.getAttribute('data-meter'), range: state.range, contract: ctl.getAttribute('data-contract') };
      if (state.range === 'year') { p.ref = refYear.value; }
      if (state.range === 'contract') { p.cy = refCy.value; }
      get(ctl.getAttribute('data-api'), p).then(function (d) {
        $('#usagetitle').textContent = d.title || '';
        var counters = {};
        var single = d.datasets.length === 1;
        var sets = d.datasets.map(function (s) {
          var n = counters[s.tariff] = (counters[s.tariff] || 0) + 1;
          var c = COLORS[s.tariff] || COLORS.OTHER;
          var a = SHADES[Math.min(n - 1, 2)];
          var cur = d.current;
          return {
            label: single ? 'Verbrauch' : (TARIFF_NAME[s.tariff] || s.label) + (n > 1 ? ' (' + s.label + ')' : ''),
            data: s.data, tariff: s.tariff, _any: s.any,
            backgroundColor: function (ctx) { return withAlpha(c, ctx.dataIndex === cur ? a * 0.55 : a); },
            borderColor: function (ctx) { return ctx.dataIndex === cur ? c : 'transparent'; },
            borderWidth: function (ctx) { return ctx.dataIndex === cur ? 1.5 : 0; },
            borderDash: [4, 3],
            borderRadius: 6, borderSkipped: false, maxBarThickness: 46, categoryPercentage: 0.82, barPercentage: 0.9
          };
        });
        if (d.target) {
          sets.push({ type: 'line', label: d.target_label, data: d.labels.map(function () { return d.target; }), borderColor: '#f59e0b', backgroundColor: '#f59e0b',
            borderDash: [6, 4], borderWidth: 2, pointRadius: 0, pointHoverRadius: 0, fill: false, order: 0, stack: 'target', _any: false });
        }
        var any = sets.some(function (s) { return s._any; });
        $('#usageempty').hidden = any;

        /* Kennzahlen: Summe, Durchschnitt, Abweichung vom Soll */
        var total = 0, months = 0, sums = d.labels.map(function () { return null; });
        d.datasets.forEach(function (s) {
          if (s.tariff === 'EXPORT') { return; }
          s.data.forEach(function (v, i) { if (v != null) { sums[i] = (sums[i] || 0) + v; } });
        });
        sums.forEach(function (v) { if (v != null) { total += v; months++; } });
        var html = '';
        if (months) {
          html += '<span class="chip">Summe <b>' + nfmt.format(total) + ' kWh</b></span>';
          html += '<span class="chip">Ø pro Monat <b>' + nfmt.format(total / months) + ' kWh</b></span>';
          if (d.target) {
            var diff = total - d.target * months, pct = d.target * months ? diff / (d.target * months) * 100 : 0;
            html += '<span class="chip ' + (diff > 0 ? 'over' : 'under') + '">' + (diff > 0 ? 'über Soll' : 'unter Soll') + ' <b>' +
              (diff > 0 ? '+' : '−') + nfmt.format(Math.abs(diff)) + ' kWh (' + (diff > 0 ? '+' : '−') + nfmt.format(Math.abs(pct)) + ' %)</b></span>';
          }
        }
        $('#usagestats').innerHTML = html;

        if (usageChart) { usageChart.destroy(); }
        usageChart = new Chart(usageCanvas, {
          type: 'bar',
          data: { labels: d.labels, datasets: sets },
          plugins: [totalsPlugin],
          options: {
            responsive: true, maintainAspectRatio: false,
            layout: { padding: { top: 6 } },
            interaction: { mode: 'index', intersect: false },
            scales: {
              x: { stacked: true, ticks: { color: textColor(), maxRotation: 0, autoSkip: usageCanvas.clientWidth < 420, font: { size: usageCanvas.clientWidth < 520 ? 10 : 12 } }, grid: { display: false }, border: { display: false } },
              y: { stacked: true, beginAtZero: true, grace: '10%', title: { display: true, text: d.unit, color: textColor() },
                   ticks: { color: textColor(), maxTicksLimit: 6, callback: function (v) { return nfmt.format(v); } }, grid: { color: gridColor() }, border: { display: false } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: textColor(), usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16 } },
              tooltip: {
                callbacks: {
                  label: function (c) { return c.dataset.label + ': ' + fmt(c.parsed.y, d.unit); },
                  footer: function (items) {
                    var i = items[0].dataIndex, out = [];
                    if (sums[i] != null && d.datasets.length > 1) { out.push('Summe: ' + fmt(sums[i], d.unit)); }
                    if (d.target && sums[i] != null) {
                      var df = sums[i] - d.target;
                      out.push('Soll: ' + fmt(d.target, d.unit) + ' (' + (df > 0 ? '+' : '−') + nfmt.format(Math.abs(df)) + ')');
                    }
                    if (i === d.current) { out.push('laufender Monat – bis zur letzten Ablesung'); }
                    return out;
                  }
                }
              }
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
