// Ż1: Core Web Vitals tej strony u odwiedzającego (web-vitals 5.3.0, plik lokalny). Nic nie wysyła i nic nie zapisuje.
(() => {
  const el = document.querySelector('[data-zegar]');
  if (!el) return;
  const pole = (n) => el.querySelector(`[data-wv="${n}"]`);
  const przecinek = (x, m) => x.toFixed(m).replace('.', ',');
  const pokaz = (n, tekst, ocena) => {
    const p = pole(n);
    if (!p) return;
    p.textContent = tekst;
    p.dataset.ocena = ocena;
    el.dataset.zmierzono = '';
  };
  const start = () => {
    const s = document.createElement('script');
    s.src = '/zywe/web-vitals.iife.js';
    s.async = true;
    s.onload = () => {
      const w = window.webVitals;
      if (!w) return;
      const o = { reportAllChanges: true };
      w.onLCP((m) => pokaz('lcp', przecinek(m.value / 1000, 1) + ' s', m.rating), o);
      w.onCLS((m) => pokaz('cls', przecinek(m.value, 2), m.rating), o);
      w.onINP((m) => pokaz('inp', Math.round(m.value) + ' ms', m.rating), o);
    };
    document.head.append(s);
  };
  const poZaladowaniu = () => ('requestIdleCallback' in window ? requestIdleCallback(start, { timeout: 3000 }) : setTimeout(start, 1));
  document.readyState === 'complete' ? poZaladowaniu() : addEventListener('load', poZaladowaniu, { once: true });
})();
