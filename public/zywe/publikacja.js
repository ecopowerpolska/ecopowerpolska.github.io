// Ż3: "Ostatnia publikacja: N minut temu" z publicznego API GitHuba (bez klucza, 60 zapytań/h na IP).
// Wynik trzymamy 5 min w pamięci karty (zmienna), a przy błędzie zostaje data z builda.
(() => {
  const el = document.querySelector('[data-publikacja]');
  if (!el) return;
  const czas = el.querySelector('time');
  const API = 'https://api.github.com/repos/ecopowerpolska/ecopowerpolska.github.io/commits?per_page=1';
  const odm = (n, a, b, c) => (n === 1 ? a : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? b : c);
  const temu = (d) => {
    const m = Math.max(0, Math.round((Date.now() - d) / 60000));
    if (m < 1) return 'przed chwilą';
    if (m < 60) return m === 1 ? 'minutę temu' : `${m} ${odm(m, 'minutę', 'minuty', 'minut')} temu`;
    const g = Math.round(m / 60);
    if (g < 24) return g === 1 ? 'godzinę temu' : `${g} ${odm(g, 'godzinę', 'godziny', 'godzin')} temu`;
    const dn = Math.round(g / 24);
    return dn === 1 ? 'wczoraj' : `${dn} dni temu`;
  };
  const pokaz = (iso) => {
    const d = Date.parse(iso);
    if (!d) return;
    czas.dateTime = iso;
    czas.title = new Date(d).toLocaleString('pl-PL');
    czas.textContent = temu(d);
  };
  fetch(API, { headers: { Accept: 'application/vnd.github+json' } })
    .then((r) => (r.ok ? r.json() : Promise.reject()))
    .then((j) => {
      const iso = j?.[0]?.commit?.committer?.date;
      if (iso) {
        pokaz(iso);
        setInterval(() => pokaz(iso), 60000);
      }
    })
    .catch(() => {});
})();
