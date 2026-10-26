// Prueba de la aparición de secciones de assets/js/main.js con un DOM mínimo falso. Uso: node tests/Support/revelar_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; la prueba de PHP (PortadaMovimientoTest) lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "main.js"), "utf8");

function escenario({ reducido = false, conObservador = true, secciones = 3 } = {}) {
  const lista = Array.from({ length: secciones }, () => { const c = new Set(); return { classList: { add: (x) => c.add(x), has: (x) => c.has(x) }, c }; });
  const obs = { observados: [], dejados: [] };
  let callback = null, opciones = null;
  class Vigia {
    constructor(cb, op) { callback = cb; opciones = op; }
    observe(el) { obs.observados.push(el); }
    unobserve(el) { obs.dejados.push(el); }
  }
  const doc = {
    querySelectorAll: (sel) => (sel === ".portada-seccion" ? lista : []),
    querySelector: () => null,
    getElementById: () => null,
    addEventListener: (t, f) => { if (t === "DOMContentLoaded") f(); },
  };
  const win = { location: { search: "" }, matchMedia: (q) => ({ matches: reducido && q.includes("prefers-reduced-motion: reduce") }) };
  if (conObservador) win.IntersectionObserver = Vigia;
  const bootstrap = { Alert: { getOrCreateInstance: () => ({ close() {} }) }, Collapse: { getOrCreateInstance: () => ({ hide() {} }) } };
  new Function("document", "window", "bootstrap", "confirm", "IntersectionObserver", fuente)(doc, win, bootstrap, () => true, conObservador ? Vigia : undefined);
  return { lista, obs, entrar: (i, visible) => callback([{ target: lista[i], isIntersecting: visible }]), umbral: () => opciones && opciones.threshold };
}

const r = {};
let e = escenario();
r.conMovimientoMarcaTodas = e.lista.every((s) => s.c.has("rv")) && e.obs.observados.length === 3;
r.umbral = e.umbral();
e.entrar(1, false); r.noVisibleNoRevela = !e.lista[1].c.has("rv-visto");
e.entrar(1, true); r.visibleRevela = e.lista[1].c.has("rv-visto");
r.dejaDeVigilar = e.obs.dejados.length === 1 && e.obs.dejados[0] === e.lista[1];
r.lasOtrasSiguenOcultas = !e.lista[0].c.has("rv-visto") && !e.lista[2].c.has("rv-visto");
e = escenario({ reducido: true });
r.reducidoNoToca = e.lista.every((s) => s.c.size === 0) && e.obs.observados.length === 0;
e = escenario({ conObservador: false });
r.sinObservadorNoToca = e.lista.every((s) => s.c.size === 0);
e = escenario({ secciones: 0 });
r.sinSeccionesNoCrea = e.obs.observados.length === 0;
console.log(JSON.stringify(r));
