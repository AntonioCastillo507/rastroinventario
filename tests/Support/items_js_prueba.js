// Prueba de la aparición escalonada de tarjetas, filas y piezas de assets/js/main.js con un DOM mínimo falso.
// Uso: node tests/Support/items_js_prueba.js. Imprime un JSON con cada escenario; ItemsMovimientoJsTest lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "main.js"), "utf8");

function escenario({ reducido = false, conObservador = true, items = 10 } = {}) {
  const lista = Array.from({ length: items }, () => {
    const c = new Set(), estilo = {};
    return { c, estilo, classList: { add: (x) => c.add(x), has: (x) => c.has(x) }, style: { setProperty: (k, v) => { estilo[k] = v; } } };
  });
  const obs = { observados: [], dejados: [] };
  let callback = null, opciones = null, selector = null;
  class Vigia {
    constructor(cb, op) { callback = cb; opciones = op; }
    observe(el) { obs.observados.push(el); }
    unobserve(el) { obs.dejados.push(el); }
  }
  const doc = {
    querySelectorAll: (sel) => { if (sel.includes(".lista-tabla")) { selector = sel; return lista; } return []; },
    querySelector: () => null,
    getElementById: () => null,
    addEventListener: (t, f) => { if (t === "DOMContentLoaded") f(); },
  };
  const win = { location: { search: "" }, matchMedia: (q) => ({ matches: reducido && q.includes("prefers-reduced-motion: reduce") }) };
  if (conObservador) win.IntersectionObserver = Vigia;
  const bootstrap = { Alert: { getOrCreateInstance: () => ({ close() {} }) }, Collapse: { getOrCreateInstance: () => ({ hide() {} }) } };
  new Function("document", "window", "bootstrap", "confirm", "IntersectionObserver", fuente)(doc, win, bootstrap, () => true, conObservador ? Vigia : undefined);
  return { lista, obs, selector: () => selector, umbral: () => opciones && opciones.threshold, entrar: (indices, visibles) => callback(indices.map((i) => ({ target: lista[i], isIntersecting: visibles ? visibles.includes(i) : true }))) };
}

const r = {};
let e = escenario();
r.selector = e.selector();
r.marcaTodos = e.lista.every((x) => x.c.has("rv-item")) && e.obs.observados.length === 10;
r.umbral = e.umbral();
r.antesDeEntrarNadaVisto = e.lista.every((x) => !x.c.has("rv-visto"));
e.entrar([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);
r.retrasos = e.lista.map((x) => x.estilo["--rv-retraso"]);
r.todosVistos = e.lista.every((x) => x.c.has("rv-visto"));
r.dejaDeVigilar = e.obs.dejados.length === 10;

e = escenario();
e.entrar([3, 4, 5], [4, 5]); // el 3 no es visible: no cuenta en el escalón
r.conInvisible = { retrasos: [3, 4, 5].map((i) => e.lista[i].estilo["--rv-retraso"] || null), tresVisto: e.lista[3].c.has("rv-visto"), dejados: e.obs.dejados.length };
e.entrar([0]);
r.otroLote = e.lista[0].estilo["--rv-retraso"]; // cada lote vuelve a empezar en 0

e = escenario({ reducido: true });
r.reducidoNoToca = e.lista.every((x) => x.c.size === 0) && e.obs.observados.length === 0;
e = escenario({ conObservador: false });
r.sinObservadorNoToca = e.lista.every((x) => x.c.size === 0);
e = escenario({ items: 0 });
r.sinItemsNoCrea = e.obs.observados.length === 0;
console.log(JSON.stringify(r));
