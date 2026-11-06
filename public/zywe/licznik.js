// Ż4: "Dziś sprawdzono N stron" z api.ecopowerpolska.pl/licznik.php. Przy błędzie zostaje tekst statyczny.
(() => {
  const el = document.querySelector('[data-licznik]');
  if (!el) return;
  const odm = (n, a, b, c) => (n === 1 ? a : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? b : c);
  fetch('https://api.ecopowerpolska.pl/licznik.php')
    .then((r) => (r.ok ? r.json() : Promise.reject()))
    .then(({ dzis, razem }) => {
      const t = el.querySelector('[data-licznik-tekst]');
      if (!t || !(dzis > 0)) return;
      t.textContent = `Dziś sprawdzono ${dzis} ${odm(dzis, 'stronę', 'strony', 'stron')}`;
      const r = el.querySelector('[data-licznik-razem]');
      if (r && razem > dzis) r.textContent = `, a od początku ${razem}`;
    })
    .catch(() => {});
})();
