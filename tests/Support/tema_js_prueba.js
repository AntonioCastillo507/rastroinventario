// Prueba de assets/js/tema.js con un DOM mínimo falso. Uso: node tests/Support/tema_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; la prueba de PHP (TemaJsTest) lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "tema.js"), "utf8");

function escenario(opciones) {
  const attrs = {};
  const raiz = { setAttribute: (k, v) => { attrs[k] = v; }, getAttribute: (k) => (k in attrs ? attrs[k] : null) };
  const icono = { className: "bi bi-sun" };
  const boton = {
    attrs: {},
    setAttribute(k, v) { this.attrs[k] = v; },
    querySelector: () => icono,
    closest(sel) { return sel === "[data-tema-toggle]" ? this : null; },
  };
  const escuchas = { document: {}, window: {} };
  const doc = {
    documentElement: raiz,
    querySelectorAll: (sel) => (sel === "[data-tema-toggle]" ? [boton] : []),
    addEventListener: (t, f) => { escuchas.document[t] = f; },
  };
  const win = { addEventListener: (t, f) => { escuchas.window[t] = f; } };
  const almacen = Object.assign({}, opciones.guardado === undefined ? {} : { "rastro-tema": opciones.guardado });
  const ls = opciones.fallaAlmacen
    ? { getItem() { throw new Error("bloqueado"); }, setItem() { throw new Error("bloqueado"); } }
    : { getItem: (k) => (k in almacen ? almacen[k] : null), setItem: (k, v) => { almacen[k] = String(v); } };
  new Function("document", "window", "localStorage", fuente)(doc, win, ls);
  const estado = () => ({ tema: attrs["data-theme"], bs: attrs["data-bs-theme"], etiqueta: boton.attrs["aria-label"], titulo: boton.attrs["title"], icono: icono.className, guardado: almacen["rastro-tema"] ?? null });
  return {
    estado, escuchas, boton, icono,
    cargar: () => escuchas.document["DOMContentLoaded"] && escuchas.document["DOMContentLoaded"](),
    clic: (objetivo) => escuchas.document["click"]({ target: objetivo }),
  };
}

const r = {};
let e = escenario({});
r.antesDeCargar = e.estado();           // el tema se fija al ejecutar el script, antes de DOMContentLoaded
e.cargar(); r.sinGuardado = e.estado();
e.clic(e.boton); r.tras1Clic = e.estado();
e.clic(e.icono = { closest: (s) => (s === "[data-tema-toggle]" ? e.boton : null) }); r.tras2ClicEnIcono = e.estado();
e.clic({ closest: () => null }); r.clicAjeno = e.estado();
e.clic({}); r.clicSinClosest = e.estado();

e = escenario({ guardado: "light" }); r.guardadoClaro = e.estado(); e.cargar(); r.guardadoClaroTrasCargar = e.estado();
e = escenario({ guardado: "dark" }); e.cargar(); r.guardadoOscuro = e.estado();
e = escenario({ guardado: "azul" }); r.guardadoBasuraAntes = e.estado(); e.cargar(); r.guardadoBasura = e.estado();

e = escenario({ fallaAlmacen: true }); e.cargar(); r.almacenBloqueado = e.estado();
try { e.clic(e.boton); r.almacenBloqueadoClic = e.estado(); } catch (err) { r.almacenBloqueadoClic = { error: String(err) }; }

e = escenario({}); e.cargar();
e.escuchas.window["storage"]({ key: "rastro-tema", newValue: "light" }); r.otraPestanaClaro = e.estado();
e.escuchas.window["storage"]({ key: "rastro-tema", newValue: "nada" }); r.otraPestanaBasura = e.estado();
e.escuchas.window["storage"]({ key: "otra", newValue: "dark" }); r.otraClave = e.estado();

console.log(JSON.stringify(r));
