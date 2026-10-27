// Prueba de assets/js/panel.js con un DOM mínimo falso. Uso: node tests/Support/panel_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; PanelMenuJsTest lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "panel.js"), "utf8");

function escenario(opciones) {
  const attrs = {};
  const raiz = { setAttribute: (k, v) => { attrs[k] = v; }, getAttribute: (k) => (k in attrs ? attrs[k] : null) };
  const almacen = Object.assign({}, opciones.almacen || {});
  const escritos = [];
  const boton = { attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }, querySelector: () => texto };
  const texto = { textContent: "Contraer menú" };
  const enlace = { attrs: {}, setAttribute(k, v) { this.attrs[k] = v; }, removeAttribute(k) { delete this.attrs[k]; }, querySelector: () => ({ textContent: "Partes" }) };
  const escuchas = {};
  const doc = {
    documentElement: raiz,
    addEventListener: (t, f) => { escuchas[t] = f; },
    querySelectorAll: (sel) => (sel === "[data-panel-menu-toggle]" ? [boton] : sel === ".admin-sidebar .admin-enlace" ? [enlace] : []),
  };
  const localStorage = opciones.sinAlmacen
    ? { getItem() { throw new Error("bloqueado"); }, setItem() { throw new Error("bloqueado"); } }
    : { getItem: (k) => (k in almacen ? almacen[k] : null), setItem: (k, v) => { almacen[k] = v; escritos.push([k, v]); } };
  vm.runInNewContext(fuente, { document: doc, localStorage, window: {} });
  const estado = () => ({ menu: attrs["data-panel-menu"], expandido: boton.attrs["aria-expanded"], texto: texto.textContent, titulo: enlace.attrs.title === undefined ? null : enlace.attrs.title });
  const inicial = estado();
  escuchas.DOMContentLoaded();
  const trasCarga = estado();
  const pulsar = () => escuchas.click({ target: { closest: (s) => (s === "[data-panel-menu-toggle]" ? boton : null) } });
  pulsar();
  const trasPulsar = estado();
  pulsar();
  const trasOtraVez = estado();
  escuchas.click({ target: { closest: () => null } });
  return { inicial, trasCarga, trasPulsar, trasOtraVez, escritos, ajeno: estado() };
}

console.log(JSON.stringify({
  sinGuardado: escenario({}),
  colapsado: escenario({ almacen: { "rastro-panel-menu": "colapsado" } }),
  basura: escenario({ almacen: { "rastro-panel-menu": "otra-cosa" } }),
  sinAlmacen: escenario({ sinAlmacen: true }),
}));
