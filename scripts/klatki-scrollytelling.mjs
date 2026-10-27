// klatki-scrollytelling.mjs — klatki sceny A strony /efekty/animacja-przewijaniem/ (przepis A działu
// potwor-ux-ui/odniesienia/scrollytelling-animacja-przewijaniem.md). Grafika własna: szafa narysowana
// w SVG w tym pliku (bez zdjęć stockowych i bez AI). Każda klatka = ta sama funkcja SVG z innym
// postępem t (0 = zamknięta, 1 = otwarta, światło włączone, szuflada wysunięta), zrzucona przez
// headless Chrome (CDP, Page.captureScreenshot format webp) — jeden proces Chrome na całą serię.
//
// Użycie (z katalogu strony, Node ≥ 22 — globalny WebSocket):
//   node scripts/klatki-scrollytelling.mjs [katalog=public/efekty/animacja-przewijaniem] [liczba=96] [jakosc=72] [skala=1.25]
// Rysunek ma 960×640 jednostek; skala 1.25 daje pliki 1200×800 px (ostrzej na ekranach o gęstości > 1).
// Wynik: <katalog>/klatki/0001.webp … NNNN.webp oraz <katalog>/plakat.svg (stan końcowy = ostatnia klatka).
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const [katalog = 'public/efekty/animacja-przewijaniem', liczbaArg = '96', jakoscArg = '72', skalaArg = '1.25'] = process.argv.slice(2);
const SKALA = Number(skalaArg);
const LICZBA = Number(liczbaArg);
const JAKOSC = Number(jakoscArg);
const SZER = 960;
const WYS = 640;
const PORT = 9364;
const sen = (ms) => new Promise((r) => setTimeout(r, ms));

// Kolory = tokeny motywu (src/styles/motyw.css) + dwa odcienie drewna ilustracji.
const K = {
  tlo: '#f4f5f1', linia: '#c7ccc0', tekst: '#15201a', cichy: '#4b5a4f', akcent: '#166653',
  korpus: '#dcdfd3', front: '#cfd3c5', frontWew: '#b9bfae', wnetrzeCiemne: '#26332c', wnetrzeJasne: '#efeee6',
  drewno: '#b08a5e', drewnoCien: '#8c6b45',
};

const ogranicz = (x) => Math.min(1, Math.max(0, x));
const odcinek = (t, a, b) => ogranicz((t - a) / (b - a));
const gladko = (x) => x * x * (3 - 2 * x);
const miesz = (a, b, x) => {
  const h = (c) => [1, 3, 5].map((i) => parseInt(c.slice(i, i + 2), 16));
  const [p, q] = [h(a), h(b)];
  return '#' + p.map((v, i) => Math.round(v + (q[i] - v) * x).toString(16).padStart(2, '0')).join('');
};

// Szafa: korpus x 300–660, y 80–560. Rzut perspektywiczny drzwi wokół środka (480, 320), odległość oka 900 px.
const X0 = 300, X1 = 660, Y0 = 80, Y1 = 560, SRODEK_X = 480, SRODEK_Y = 320, OKO = 900;
const rzut = (x, y, z) => {
  const s = OKO / (OKO - z);
  return [SRODEK_X + (x - SRODEK_X) * s, SRODEK_Y + (y - SRODEK_Y) * s];
};
const pkt = (p) => p.map((v) => v.toFixed(1)).join(',');

function drzwi(zawiasX, kierunek, kat) {
  // kierunek: +1 = drzwi leżą na prawo od zawiasu (lewe drzwi), −1 = na lewo (prawe drzwi)
  const szer = (X1 - X0) / 2;
  const x = zawiasX + kierunek * szer * Math.cos(kat);
  const z = szer * Math.sin(kat);
  const [gx, gy] = rzut(x, Y0, z);
  const [dx, dy] = rzut(x, Y1, z);
  const zewn = Math.cos(kat) >= 0; // widać lico; po przekroczeniu 90° widać wewnętrzną stronę
  const kolor = zewn ? K.front : K.frontWew;
  // uchwyt: pionowa listwa 30 px od krawędzi otwierającej, wysokość 120 px
  const ux = zawiasX + kierunek * (szer - 30) * Math.cos(kat);
  const uz = (szer - 30) * Math.sin(kat);
  const [u1x, u1y] = rzut(ux, 260, uz);
  const [u2x, u2y] = rzut(ux, 380, uz);
  return `<polygon points="${pkt([zawiasX, Y0])} ${pkt([gx, gy])} ${pkt([dx, dy])} ${pkt([zawiasX, Y1])}" fill="${kolor}" stroke="${K.cichy}" stroke-width="2" stroke-linejoin="round"/>` +
    (zewn ? `<line x1="${u1x.toFixed(1)}" y1="${u1y.toFixed(1)}" x2="${u2x.toFixed(1)}" y2="${u2y.toFixed(1)}" stroke="${K.akcent}" stroke-width="${(6 * OKO / (OKO - uz)).toFixed(1)}" stroke-linecap="round"/>` : '');
}

export function szafaSvg(t) {
  const otwarcie = gladko(odcinek(t, 0, 0.6));
  const swiatlo = gladko(odcinek(t, 0.45, 0.75));
  const szuflada = gladko(odcinek(t, 0.7, 1));
  const kat = otwarcie * (110 * Math.PI) / 180;
  const wnetrze = miesz(K.wnetrzeCiemne, K.wnetrzeJasne, swiatlo);
  const polki = miesz('#3a4840', K.drewno, swiatlo);
  const ubrania = [K.akcent, K.cichy, K.drewnoCien, '#6f8a7a', K.tekst, '#9aa596'].map((c) => miesz('#2f3d35', c, 0.25 + 0.75 * swiatlo));
  const wieszaki = ubrania.map((c, i) => {
    const x = 322 + i * 25;
    const dl = [190, 160, 210, 170, 200, 150][i];
    return `<path d="M${x + 10} 146 l0 8 l-10 10 l20 0 z" fill="none" stroke="${miesz('#55625a', K.cichy, swiatlo)}" stroke-width="2"/>` +
      `<rect x="${x}" y="164" width="21" height="${dl}" rx="5" fill="${c}"/>`;
  }).join('');
  const kostki = [0, 1, 2].map((r) => [0, 1].map((k) => `<rect x="${500 + k * 76}" y="${166 + r * 92 - 34}" width="62" height="34" rx="4" fill="${ubrania[(r + k * 2) % 6]}"/>`).join('')).join('');
  // szuflada: lico rośnie w dół i w przód (perspektywa), z cieniem pod spodem
  const wysuw = szuflada * 70;
  const [s1x, s1y] = rzut(492, 470, wysuw), [s2x, s2y] = rzut(648, 470, wysuw);
  const [s3x, s3y] = rzut(648, 540, wysuw), [s4x, s4y] = rzut(492, 540, wysuw);
  const blask = (0.55 * swiatlo).toFixed(3);
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${SZER}" height="${WYS}" viewBox="0 0 ${SZER} ${WYS}">` +
    `<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffbe6" stop-opacity="${blask}"/><stop offset="1" stop-color="#fffbe6" stop-opacity="0"/></linearGradient>` +
    `<radialGradient id="p"><stop offset="0" stop-color="${K.linia}"/><stop offset="1" stop-color="${K.tlo}" stop-opacity="0"/></radialGradient></defs>` +
    `<rect width="${SZER}" height="${WYS}" fill="${K.tlo}"/>` +
    // podłoga bez krawędzi: plama zanikająca do koloru tła, żeby kadr nie odcinał się prostokątem od strony
    `<ellipse cx="480" cy="572" rx="440" ry="46" fill="url(#p)"/>` +
    `<ellipse cx="480" cy="566" rx="${230 + 40 * otwarcie}" ry="10" fill="${K.linia}" opacity=".7"/>` +
    // korpus i wnętrze
    `<rect x="${X0 - 10}" y="${Y0 - 14}" width="${X1 - X0 + 20}" height="${Y1 - Y0 + 24}" rx="4" fill="${K.korpus}" stroke="${K.cichy}" stroke-width="2"/>` +
    `<rect x="${X0}" y="${Y0}" width="${X1 - X0}" height="${Y1 - Y0}" fill="${wnetrze}"/>` +
    `<rect x="${X0}" y="${Y0}" width="${X1 - X0}" height="200" fill="url(#g)"/>` +
    `<line x1="${X0 + 12}" y1="${Y0 + 8}" x2="${X1 - 12}" y2="${Y0 + 8}" stroke="${miesz('#3d4b43', '#fff6c8', swiatlo)}" stroke-width="5" stroke-linecap="round"/>` +
    `<rect x="486" y="${Y0}" width="8" height="${Y1 - Y0}" fill="${polki}"/>` +
    `<line x1="312" y1="150" x2="480" y2="150" stroke="${miesz('#55625a', K.cichy, swiatlo)}" stroke-width="5" stroke-linecap="round"/>` +
    wieszaki +
    [166, 258, 350, 442].map((y) => `<rect x="494" y="${y}" width="166" height="8" fill="${polki}"/>`).join('') + kostki +
    `<rect x="494" y="462" width="166" height="98" fill="${miesz('#2f3d35', K.korpus, swiatlo)}"/>` +
    `<polygon points="${pkt([s1x, s1y])} ${pkt([s2x, s2y])} ${pkt([s3x, s3y])} ${pkt([s4x, s4y])}" fill="${miesz('#3a4840', K.front, swiatlo)}" stroke="${K.cichy}" stroke-width="2"/>` +
    `<line x1="${((s1x + s2x) / 2 - 24).toFixed(1)}" y1="${(s1y + 16).toFixed(1)}" x2="${((s1x + s2x) / 2 + 24).toFixed(1)}" y2="${(s1y + 16).toFixed(1)}" stroke="${K.akcent}" stroke-width="5" stroke-linecap="round"/>` +
    // drzwi: lewe z zawiasem na X0, prawe na X1
    drzwi(X0, +1, kat) + drzwi(X1, -1, kat) +
    `<rect x="${X0 - 10}" y="${Y1}" width="${X1 - X0 + 20}" height="10" fill="${K.cichy}"/>` +
    `</svg>`;
}

async function main() {
  const wyjscie = join(katalog, 'klatki');
  rmSync(wyjscie, { recursive: true, force: true });
  mkdirSync(wyjscie, { recursive: true });
  writeFileSync(join(katalog, 'plakat.svg'), szafaSvg(1) + '\n');

  const profil = mkdtempSync(join(process.env.KLATKI_TMP || tmpdir(), 'klatki-profil-'));
  const ch = spawn('google-chrome', ['--headless=new', `--remote-debugging-port=${PORT}`, '--disable-gpu', '--hide-scrollbars',
    `--user-data-dir=${profil}`, '--no-first-run', '--no-default-browser-check', 'about:blank'], { stdio: 'ignore' });
  const start = Date.now();
  try {
    let lista = [];
    for (let i = 0; i < 80 && !lista.some((t) => t.type === 'page'); i++) {
      try { lista = await (await fetch(`http://127.0.0.1:${PORT}/json`)).json(); } catch { /* Chrome jeszcze wstaje */ }
      await sen(100);
    }
    const ws = new WebSocket(lista.find((t) => t.type === 'page').webSocketDebuggerUrl);
    await new Promise((r, j) => { ws.onopen = r; ws.onerror = j; });
    let id = 0;
    const czeka = new Map();
    ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && czeka.has(d.id)) { czeka.get(d.id)(d); czeka.delete(d.id); } };
    const cdp = (method, params = {}) => new Promise((r) => { const i = ++id; czeka.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });

    await cdp('Emulation.setDeviceMetricsOverride', { width: SZER, height: WYS, deviceScaleFactor: SKALA, mobile: false });
    await cdp('Page.navigate', { url: `data:text/html,<style>html,body{margin:0;background:${encodeURIComponent(K.tlo)}}svg{display:block}</style><body></body>` });
    await sen(500);
    for (let i = 0; i < LICZBA; i++) {
      const t = i / (LICZBA - 1);
      await cdp('Runtime.evaluate', { expression: `document.body.innerHTML = ${JSON.stringify(szafaSvg(t))}` });
      const w = await cdp('Page.captureScreenshot', { format: 'webp', quality: JAKOSC, clip: { x: 0, y: 0, width: SZER, height: WYS, scale: 1 } });
      writeFileSync(join(wyjscie, `${String(i + 1).padStart(4, '0')}.webp`), Buffer.from(w.result.data, 'base64'));
    }
    ws.close();
  } finally {
    ch.kill('SIGTERM');
    await new Promise((r) => ch.once('exit', r));
    rmSync(profil, { recursive: true, force: true });
  }
  const pliki = readdirSync(wyjscie);
  const bajty = pliki.reduce((s, p) => s + statSync(join(wyjscie, p)).size, 0);
  console.log(`klatek ${pliki.length}, ${Math.round(SZER * SKALA)}×${Math.round(WYS * SKALA)}, webp q${JAKOSC}: ${(bajty / 1024).toFixed(0)} KiB, średnio ${(bajty / pliki.length / 1024).toFixed(1)} KiB, czas ${((Date.now() - start) / 1000).toFixed(1)} s`);
}

main();
