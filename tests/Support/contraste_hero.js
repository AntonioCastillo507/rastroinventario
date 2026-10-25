// Mide el contraste del texto de la portada sobre los píxeles reales del hero (foto + scrim), en el navegador.
// Se pega en la consola (o en javascript_tool) con la portada abierta; define window.medirHero(), que devuelve una promesa con el resumen.
// Para cada texto: peor contraste dentro de su propio rectángulo y peor contraste contra TODA la foto
// (cualquier punto de la imagen puede quedar bajo el texto al cambiar el ancho de la ventana).
window.medirHero = async () => {
  const hero = document.querySelector('.hero-section');
  const ov = hero.querySelector('.hero-overlay');
  const caja = hero.getBoundingClientRect();
  const W = Math.round(caja.width), H = Math.round(caja.height);
  const url = getComputedStyle(hero).backgroundImage.match(/url\(["']?([^"')]+)["']?\)/)[1];
  const img = await new Promise((ok, ko) => { const i = new Image(); i.onload = () => ok(i); i.onerror = ko; i.src = url; });
  const lienzo = document.createElement('canvas'); lienzo.width = W; lienzo.height = H;
  const g = lienzo.getContext('2d', { willReadFrequently: true });
  const k = Math.max(W / img.naturalWidth, H / img.naturalHeight); // background-size: cover; position: center
  const dw = img.naturalWidth * k, dh = img.naturalHeight * k;
  g.drawImage(img, (W - dw) / 2, (H - dh) / 2, dw, dh);
  const colorScrim = getComputedStyle(ov).backgroundColor;
  g.fillStyle = colorScrim; g.fillRect(0, 0, W, H);
  const px = g.getImageData(0, 0, W, H).data;

  const uno = document.createElement('canvas'); uno.width = uno.height = 1;
  const u = uno.getContext('2d', { willReadFrequently: true });
  const rgba = (css) => { u.clearRect(0, 0, 1, 1); u.fillStyle = '#000'; u.fillStyle = css; u.fillRect(0, 0, 1, 1); const d = u.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  const lum = (r, gg, b) => 0.2126 * lin(r) + 0.7152 * lin(gg) + 0.0722 * lin(b);
  const razon = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
  // luminancia del texto (con su alfa) sobre un fondo opaco r,g,b
  const sobre = (t, r, gg, b) => lum(t[0] * t[3] + r * (1 - t[3]), t[1] * t[3] + gg * (1 - t[3]), t[2] * t[3] + b * (1 - t[3]));

  // fondo propio (sólido) más cercano a un elemento, o null si el texto queda directamente sobre la foto
  const fondoPropio = (el) => {
    for (let e = el; e && e !== hero; e = e.parentElement) {
      const c = rgba(getComputedStyle(e).backgroundColor);
      if (c[3] >= 0.99) return c;
    }
    return null;
  };

  const textos = [];
  const recorre = document.createTreeWalker(hero.querySelector('.hero-content'), NodeFilter.SHOW_TEXT);
  for (let n; (n = recorre.nextNode());) {
    const t = n.textContent.replace(/\s+/g, ' ').trim();
    if (!t) continue;
    const el = n.parentElement, cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const rg = document.createRange(); rg.selectNodeContents(n);
    const cajas = [...rg.getClientRects()].filter((r) => r.width > 0 && r.height > 0);
    textos.push({ texto: t, el, cs, cajas, color: rgba(cs.color), marcador: false });
  }
  for (const inp of hero.querySelectorAll('input[placeholder]')) {
    const cs = getComputedStyle(inp, '::placeholder'), r = inp.getBoundingClientRect();
    textos.push({ texto: 'placeholder: ' + inp.placeholder, el: inp, cs: getComputedStyle(inp), cajas: [r], color: rgba(cs.color), marcador: true });
  }

  const peorTodaLaFoto = new Map();
  const contraTodaLaFoto = (t) => {
    const clave = t.join(',');
    if (peorTodaLaFoto.has(clave)) return peorTodaLaFoto.get(clave);
    let peor = Infinity;
    for (let i = 0; i < px.length; i += 4) {
      const lp = lum(px[i], px[i + 1], px[i + 2]);
      const r = razon(sobre(t, px[i], px[i + 1], px[i + 2]), lp);
      if (r < peor) peor = r;
    }
    peorTodaLaFoto.set(clave, peor);
    return peor;
  };

  const filas = [];
  for (const x of textos) {
    const tam = parseFloat(x.cs.fontSize), peso = parseInt(x.cs.fontWeight, 10) || 400;
    const grande = tam >= 24 || (tam >= 18.66 && peso >= 700);
    const necesario = grande ? 3 : 4.5;
    const propio = fondoPropio(x.el);
    let peor = Infinity;
    if (propio) {
      peor = razon(sobre(x.color, propio[0], propio[1], propio[2]), lum(propio[0], propio[1], propio[2]));
    } else {
      for (const r of x.cajas) {
        const x0 = Math.max(0, Math.floor(r.left - caja.left)), x1 = Math.min(W, Math.ceil(r.right - caja.left));
        const y0 = Math.max(0, Math.floor(r.top - caja.top)), y1 = Math.min(H, Math.ceil(r.bottom - caja.top));
        for (let y = y0; y < y1; y++) for (let xx = x0; xx < x1; xx++) {
          const i = (y * W + xx) * 4;
          const c = razon(sobre(x.color, px[i], px[i + 1], px[i + 2]), lum(px[i], px[i + 1], px[i + 2]));
          if (c < peor) peor = c;
        }
      }
    }
    const foto = propio ? null : contraTodaLaFoto(x.color);
    filas.push({
      texto: x.texto.slice(0, 40), tam: Math.round(tam * 10) / 10, peso, necesario,
      fondo: propio ? 'propio' : 'foto', enSuRectangulo: Math.round(peor * 100) / 100,
      contraTodaLaFoto: foto === null ? null : Math.round(foto * 100) / 100,
      ok: peor >= necesario && (foto === null || foto >= necesario),
    });
  }
  let maxLum = 0, minLum = 1;
  for (let i = 0; i < px.length; i += 4) { const l = lum(px[i], px[i + 1], px[i + 2]); if (l > maxLum) maxLum = l; if (l < minLum) minLum = l; }
  return {
    tema: document.documentElement.dataset.theme, ancho: innerWidth, hero: W + 'x' + H, foto: url.split('/').pop(), scrim: colorScrim,
    puntoMasClaro: Math.round(maxLum * 1000) / 1000, puntoMasOscuro: Math.round(minLum * 1000) / 1000,
    textos: filas.length, fallan: filas.filter((f) => !f.ok).length, peorGeneral: Math.min(...filas.map((f) => Math.min(f.enSuRectangulo, f.contraTodaLaFoto ?? 99))),
    detalle: filas,
  };
};
