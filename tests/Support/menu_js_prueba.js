// Prueba del menú plegable de assets/js/main.js con un DOM mínimo falso. Uso: node tests/Support/menu_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; la prueba de PHP (MenuPlegableTest) lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "main.js"), "utf8");

function escenario(abierto) {
  const ocultados = [];
  const clases = new Set(abierto ? ["collapse", "navbar-collapse", "show"] : ["collapse", "navbar-collapse"]);
  const menu = { classList: { contains: (c) => clases.has(c) }, escuchas: {}, addEventListener(t, f) { this.escuchas[t] = f; } };
  const doc = {
    querySelectorAll: (sel) => (sel === ".navbar-collapse" ? [menu] : []),
    querySelector: () => null,
    getElementById: () => null,
    addEventListener: (t, f) => { if (t === "DOMContentLoaded") f(); },
    body: {},
  };
  const bootstrap = {
    Alert: { getOrCreateInstance: () => ({ close() {} }) },
    Collapse: { getOrCreateInstance: (el, op) => ({ hide() { ocultados.push({ mismoMenu: el === menu, toggle: op && op.toggle }); } }) },
  };
  new Function("document", "window", "bootstrap", "confirm", fuente)(doc, { location: { search: "" } }, bootstrap, () => true);
  const objetivo = (selectorQueCoincide) => ({ closest: (sel) => (sel.split(",").map((x) => x.trim()).includes(selectorQueCoincide) ? {} : null) });
  return { ocultados, clic: (t) => menu.escuchas["click"]({ target: t }), objetivo, registrado: typeof menu.escuchas["click"] === "function" };
}

const r = {};
let e = escenario(true);
r.registraElClic = e.registrado;
e.clic(e.objetivo("a.nav-link")); r.opcionDelMenu = e.ocultados.length;
e.clic(e.objetivo("a.dropdown-item")); r.opcionDeDesplegable = e.ocultados.length;
e.clic({ closest: () => null }); r.clicEnOtroLado = e.ocultados.length;
e.clic({}); r.clicSinClosest = e.ocultados.length;
r.detalle = e.ocultados[0];
e = escenario(false);
e.clic(e.objetivo("a.nav-link")); r.menuYaCerrado = e.ocultados.length;
console.log(JSON.stringify(r));
