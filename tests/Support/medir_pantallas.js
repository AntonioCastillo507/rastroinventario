// Mediciones en el navegador (solo lectura: abre cada pantalla con GET en un iframe del ancho pedido).
// Uso, con una sesión abierta en el sitio: await medirPantallas([{ruta, menu}], [390, 1280], ["dark", "light"])
//   menu = texto de la opción que debe estar activa en el menú, o "" si ninguna.
// Por pantalla, ancho y tema mide: desborde horizontal, un <main> y un <h1>, menú activo, contraste de cada texto contra su
// fondo real (el texto sobre la foto de la portada se mide aparte con contraste_hero.js), fuentes, recursos externos,
// respuestas HTTP de error, zonas táctiles y campos a 16 px.
window.medirPantallas = async (pantallas, anchos, temas) => {
  const uno = document.createElement('canvas'); uno.width = uno.height = 1;
  const u = uno.getContext('2d', { willReadFrequently: true });
  const rgba = (css) => { u.clearRect(0, 0, 1, 1); u.fillStyle = '#000'; u.fillStyle = css; u.fillRect(0, 0, 1, 1); const d = u.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  const lum = (c) => 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2]);
  const mezcla = (t, f) => [t[0] * t[3] + f[0] * (1 - t[3]), t[1] * t[3] + f[1] * (1 - t[3]), t[2] * t[3] + f[2] * (1 - t[3]), 1];
  const razon = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

  // sinSesion: pide la pantalla sin cookies (como un visitante) y la abre con srcdoc, para medir el acceso aunque haya una sesión abierta
  const cargar = async (ruta, ancho, sinSesion) => {
    const html = sinSesion ? await (await fetch(ruta, { credentials: 'omit' })).text() : null;
    return new Promise((ok, ko) => {
      const f = document.createElement('iframe');
      f.style.cssText = `position:fixed;left:0;top:0;width:${ancho}px;height:900px;border:0;visibility:hidden`;
      f.onload = () => ok(f); f.onerror = ko;
      if (html !== null) f.srcdoc = html; else f.src = ruta;
      document.body.appendChild(f);
    });
  };

  const resultados = [];
  for (const { ruta, menu, sinSesion } of pantallas) for (const ancho of anchos) {
    const f = await cargar(ruta, ancho, sinSesion);
    const win = f.contentWindow, doc = f.contentDocument;
    await doc.fonts.ready;
    const sinTransiciones = doc.createElement('style'); sinTransiciones.textContent = '*,*::before,*::after{transition:none!important;animation:none!important}';
    doc.head.appendChild(sinTransiciones);   // si no, el color se mide a medio camino al cambiar de tema
    for (const tema of temas) {
      doc.documentElement.dataset.theme = tema; doc.documentElement.dataset.bsTheme = tema;
      await new Promise((r) => setTimeout(r, 150));
      const r = { ruta, ancho, tema, problemas: [] };
      const prob = (t) => r.problemas.push(t);
      r.titulo = doc.title;
      if (doc.documentElement.scrollWidth > ancho) prob(`desborde horizontal: ${doc.documentElement.scrollWidth}px > ${ancho}px`);
      if (doc.querySelectorAll('main').length !== 1) prob(`<main> = ${doc.querySelectorAll('main').length}`);
      if (doc.querySelectorAll('h1').length !== 1) prob(`<h1> = ${doc.querySelectorAll('h1').length}`);
      if (!doc.title.trim()) prob('sin <title>');
      const activas = [...doc.querySelectorAll('nav.public-nav [aria-current="page"], nav.admin-nav [aria-current="page"]')].map((a) => a.textContent.trim());
      r.menu = activas.join('|');
      if (menu !== null && menu !== undefined && !(menu === '' ? activas.length === 0 : activas.length >= 1 && activas.every((t) => t === menu || t === 'Más'))) prob(`menú activo "${activas.join('|')}" en vez de "${menu}"`);

      // contraste de cada texto contra su fondo real
      let medidos = 0, enFoto = 0;
      const fallas = [];
      const recorre = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
      for (let n; (n = recorre.nextNode());) {
        const t = n.textContent.replace(/\s+/g, ' ').trim();
        if (!t) continue;
        const el = n.parentElement;
        if (!el || el.closest('script,style,noscript,.visually-hidden,.visually-hidden-focusable')) continue;
        const cs = win.getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden') continue;
        const caja = el.getBoundingClientRect();
        if (caja.width === 0 || caja.height === 0) continue;
        if (el.closest('.hero-section')) { enFoto++; continue; }
        if (el.closest('.disabled,[disabled]')) continue;   // los controles deshabilitados están exentos
        // fondo: el primer ancestro opaco, componiendo los translúcidos que haya por el camino
        const capas = [];
        let base = null;
        for (let e = el; e; e = e.parentElement) {
          const c = rgba(win.getComputedStyle(e).backgroundColor);
          if (c[3] > 0) capas.push(c);
          if (c[3] >= 0.99) { base = c; break; }
        }
        if (!base) base = rgba(win.getComputedStyle(doc.body).backgroundColor);
        if (base[3] < 0.99) base = [255, 255, 255, 1];
        let fondo = base;
        for (let i = capas.length - 1; i >= 0; i--) if (capas[i] !== base) fondo = mezcla(capas[i], fondo);
        const fg = mezcla(rgba(cs.color), fondo);
        const tam = parseFloat(cs.fontSize), peso = parseInt(cs.fontWeight, 10) || 400;
        const necesario = (tam >= 24 || (tam >= 18.66 && peso >= 700)) ? 3 : 4.5;
        const c = razon(lum(fg), lum(fondo));
        medidos++;
        if (c < necesario) fallas.push(`${t.slice(0, 30)} ${c.toFixed(2)}<${necesario}`);
      }
      r.textosMedidos = medidos; r.textosEnFoto = enFoto;
      if (fallas.length) prob(`${fallas.length} texto(s) bajo el contraste: ${fallas.slice(0, 4).join(' ; ')}`);

      // fuentes: el texto en Manrope, las cifras (.num) en IBM Plex Sans
      const familia = (el) => win.getComputedStyle(el).fontFamily.split(',')[0].replace(/["']/g, '').trim();
      const malTexto = [...doc.querySelectorAll('body *')].filter((el) => el.childNodes.length && [...el.childNodes].some((x) => x.nodeType === 3 && x.textContent.trim()) && !el.closest('.num, i.bi, code, script, style, .visually-hidden') && win.getComputedStyle(el).display !== 'none' && familia(el) !== 'Manrope' && !(familia(el) === 'IBM Plex Sans' && (/^[\d\s.,$%+x×–-]*$/.test(el.textContent.trim()) || el.matches('.pub-card-badge, [class*="chip-"], .page-link, .stat-val, .stat-pill-num, .cart-count, .pub-price, .detail-price, .total-preview'))));
      if (malTexto.length) prob(`${malTexto.length} texto(s) sin Manrope (p. ej. ${familia(malTexto[0])} en <${malTexto[0].tagName.toLowerCase()}>)`);
      const nums = [...doc.querySelectorAll('.num')].filter((el) => win.getComputedStyle(el).display !== 'none');
      r.cifras = nums.length;
      const malNum = nums.filter((el) => familia(el) !== 'IBM Plex Sans');
      if (malNum.length) prob(`${malNum.length} cifra(s) .num sin IBM Plex Sans`);

      // recursos: ninguno externo, ninguna respuesta de error
      const externos = [...doc.querySelectorAll('link[href],script[src],img[src],iframe[src]')].map((e) => e.href || e.src).filter((h) => h && !h.startsWith(location.origin) && !h.startsWith('data:') && !h.startsWith('about:'));
      if (externos.length) prob(`recursos externos: ${externos.slice(0, 3).join(', ')}`);
      const malos = win.performance.getEntriesByType('resource').filter((e) => e.responseStatus >= 400).map((e) => `${e.responseStatus} ${e.name.split('/').slice(-2).join('/')}`);
      if (malos.length) prob(`respuestas con error: ${malos.join(', ')}`);

      // táctil: a 390 px ningún botón ni campo bajo 44 px de alto; los campos a 16 px
      if (ancho <= 480) {
        const bajos = [...doc.querySelectorAll('button, input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea, .btn')].filter((el) => {
          const c = win.getComputedStyle(el); if (c.display === 'none' || c.visibility === 'hidden' || el.closest('.visually-hidden,.collapse:not(.show)')) return false;
          const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && b.height < 43.5;
        });
        if (bajos.length) prob(`${bajos.length} control(es) bajo 44 px: ${bajos.slice(0, 3).map((e) => (e.textContent || e.getAttribute('aria-label') || e.name || e.tagName).trim().slice(0, 18) + ' ' + Math.round(e.getBoundingClientRect().height)).join(', ')}`);
      }
      const campos = [...doc.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea')].filter((el) => win.getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().width > 0 && parseFloat(win.getComputedStyle(el).fontSize) < 15.99);
      if (campos.length) prob(`${campos.length} campo(s) bajo 16 px`);
      resultados.push(r);
    }
    f.remove();
  }
  return resultados;
};
