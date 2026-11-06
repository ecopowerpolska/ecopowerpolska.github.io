// Ż5: "W tej chwili czyta N osób". Id losowe tylko w pamięci tej karty (bez ciasteczek i bez Web Storage),
// odświeżane co 60 s, gdy karta jest widoczna; przy zamknięciu karty serwer dostaje "wyjście".
(() => {
  const el = document.querySelector('[data-obecni]');
  if (!el || !window.crypto?.getRandomValues) return;
  const U = 'https://api.ecopowerpolska.pl/obecni.php';
  const id = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
  const t = el.querySelector('[data-obecni-tekst]');
  const pokaz = (n) => {
    if (!t || !(n > 0)) return;
    if (n === 1) t.textContent = 'W tej chwili czytasz tylko Ty';
    else {
      const k = n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20);
      t.textContent = `W tej chwili ${k ? 'czytają' : 'czyta'} ${n} ${k ? 'osoby' : 'osób'}`;
    }
  };
  const odnow = () => {
    if (document.visibilityState !== 'visible') return;
    fetch(U, { method: 'POST', body: new URLSearchParams({ id }), keepalive: true })
      .then((r) => (r.ok ? r.json() : Promise.reject()))
      .then((j) => pokaz(j.teraz))
      .catch(() => {});
  };
  odnow();
  setInterval(odnow, 60000);
  addEventListener('visibilitychange', odnow);
  addEventListener('pageshow', (e) => e.persisted && odnow());
  addEventListener('pagehide', () => navigator.sendBeacon?.(U, new URLSearchParams({ id, wyjscie: '1' })));
})();
