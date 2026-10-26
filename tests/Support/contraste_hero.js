// Mide en el navegador el contraste del título y el subtítulo del hero con foto sobre los píxeles reales de la foto con la capa puesta.
// Se pega en la consola (o en javascript_tool) con la portada abierta; define window.medirHero(alfa?), que devuelve una promesa con el resumen:
// el peor contraste bajo cada línea de texto (más 8 px de margen) con la opacidad de la página (o la que se pase) y la opacidad mínima que da 4,5:1.
// Sin foto (hero tipográfico) devuelve { sinFoto: true }.
window.medirHero = async function (alfaForzado) {
  const L = c => { c = c.map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
  const hex = s => { s = s.trim(); if (s[0] === '#') return [1, 3, 5].map(i => parseInt(s.substr(i, 2), 16)); return s.match(/\d+/g).slice(0, 3).map(Number); };
  const hero = document.querySelector('.portada-hero');
  const foto = hero && hero.querySelector('.hero-fondo img');
  if (!foto) return { sinFoto: true };
  const cs = getComputedStyle(document.documentElement);
  const capa = hex(getComputedStyle(hero.querySelector('.hero-capa')).backgroundColor);
  const alfa = alfaForzado ?? parseFloat(getComputedStyle(hero.querySelector('.hero-capa')).opacity);
  const caja = hero.getBoundingClientRect();
  const W = caja.width, H = caja.height;
  const img = new Image(); img.src = foto.currentSrc || foto.src; await img.decode();
  const cv = document.createElement('canvas'); cv.width = img.naturalWidth; cv.height = img.naturalHeight;
  const cx = cv.getContext('2d', { willReadFrequently: true }); cx.drawImage(img, 0, 0);
  const py = parseFloat(getComputedStyle(foto).objectPosition.split(' ')[1]) / 100;
  const s = Math.max(W / img.naturalWidth, H / img.naturalHeight);
  const ox = (W - img.naturalWidth * s) * 0.5, oy = (H - img.naturalHeight * s) * py;
  const pad = 8;
  const zonas = ['.hero-titulo', '.hero-sub'].map(sel => {
    const e = hero.querySelector(sel);
    const rg = document.createRange(); rg.selectNodeContents(e);
    return { sel, color: hex(getComputedStyle(e).color), rects: [...rg.getClientRects()].map(r => [r.left - caja.left - pad, r.top - caja.top - pad, r.right - caja.left + pad, r.bottom - caja.top + pad]) };
  });
  const peor = a => {
    let min = 99, w = {};
    for (const z of zonas) {
      const lt = L(z.color);
      for (const r of z.rects) {
        const x0 = Math.max(0, Math.floor((r[0] - ox) / s)), x1 = Math.min(img.naturalWidth, Math.ceil((r[2] - ox) / s));
        const y0 = Math.max(0, Math.floor((r[1] - oy) / s)), y1 = Math.min(img.naturalHeight, Math.ceil((r[3] - oy) / s));
        if (x1 <= x0 || y1 <= y0) continue;
        const d = cx.getImageData(x0, y0, x1 - x0, y1 - y0).data;
        for (let i = 0; i < d.length; i += 4) {
          const lb = L([0, 1, 2].map(k => a * capa[k] + (1 - a) * d[i + k]));
          const c = (Math.max(lt, lb) + 0.05) / (Math.min(lt, lb) + 0.05);
          if (c < min) { min = c; w = { sel: z.sel, px: [d[i], d[i + 1], d[i + 2]] }; }
        }
      }
    }
    return { min, w };
  };
  const act = peor(alfa);
  let lo = 0, hi = 1; for (let i = 0; i < 30; i++) { const m = (lo + hi) / 2; if (peor(m).min >= 4.5) hi = m; else lo = m; }
  return { vw: innerWidth, hero: [Math.round(W), Math.round(H)], imagen: img.naturalWidth + 'x' + img.naturalHeight, alfa, minimo: +act.min.toFixed(2), peor: act.w, alfaMinimoParaAA: +hi.toFixed(3) };
};
