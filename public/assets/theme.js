/* Hell/Dunkel: Auto (Systemeinstellung) | Hell | Dunkel – Auswahl wird im Browser gemerkt */
(function () {
  var KEY = 'mb-theme', root = document.documentElement;
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var read = function () { try { var v = localStorage.getItem(KEY); return v === 'light' || v === 'dark' ? v : 'auto'; } catch (e) { return 'auto'; } };
  var write = function (v) { try { if (v === 'auto') { localStorage.removeItem(KEY); } else { localStorage.setItem(KEY, v); } } catch (e) {} };
  var apply = function (notify) {
    var mode = read();
    var dark = mode === 'dark' || (mode === 'auto' && mq && mq.matches);
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    root.setAttribute('data-mb-theme', mode);
    if (notify) { document.dispatchEvent(new CustomEvent('mb-theme')); }
  };
  apply(false);
  if (mq && mq.addEventListener) { mq.addEventListener('change', function () { if (read() === 'auto') { apply(true); } }); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest ? e.target.closest('[data-theme-set]') : null;
    if (!b) { return; }
    e.preventDefault();
    write(b.getAttribute('data-theme-set'));
    apply(true);
  });
})();
