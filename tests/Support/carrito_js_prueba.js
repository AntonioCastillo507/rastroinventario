// Prueba de assets/js/carrito.js con un DOM mínimo falso y un fetch simulado. Uso: node tests/Support/carrito_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; CarritoJsTest (PHP) lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "carrito.js"), "utf8");

class Nodo {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.className = ""; this.textContent = ""; this.parentNode = null; this.escuchas = {}; this.disabled = false; this.innerHTML = ""; this.value = ""; this.href = ""; this.type = ""; }
  setAttribute(k, v) { this.attrs[k] = String(v); }
  getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
  removeAttribute(k) { delete this.attrs[k]; }
  hasAttribute(k) { return k in this.attrs; }
  appendChild(n) { n.parentNode = this; this.children.push(n); return n; }
  removeChild(n) { this.children = this.children.filter((c) => c !== n); n.parentNode = null; return n; }
  get firstChild() { return this.children[0] || null; }
  addEventListener(t, f) { (this.escuchas[t] = this.escuchas[t] || []).push(f); }
  querySelector(sel) {
    if (sel === ".cart-count") return this.children.find((c) => c.className === "cart-count") || null;
    if (sel === "button[type=submit]") return this.boton || null;
    return null;
  }
}

function escenario(respuestas, opciones = {}) {
  const peticiones = [];
  let enviadoNormal = 0;
  const region = new Nodo("div");
  const enlace = new Nodo("a");
  const campos = [new Nodo("input"), new Nodo("input")];
  campos.forEach((c) => (c.value = "token-viejo"));
  const boton = new Nodo("button");
  boton.innerHTML = "ORIGINAL";
  const form = new Nodo("form");
  form.action = "/rastroinventario/?action=agregar_carrito&id=7";
  form.attrs["data-agregar-carrito"] = "";
  form.boton = boton;
  form.submit = () => { enviadoNormal++; };
  let previnido = 0;
  const manejadores = {};
  const documento = {
    addEventListener: (t, f) => (manejadores[t] = f),
    getElementById: (id) => (id === "avisos-carrito" ? region : null),
    querySelector: (sel) => (sel === "a.nav-carrito" ? enlace : null),
    querySelectorAll: (sel) => (sel === 'input[name="csrf_token"]' ? campos : []),
    createElement: (t) => new Nodo(t),
    createTextNode: (t) => ({ textContent: t }),
  };
  let n = 0;
  const contexto = {
    document: documento,
    window: { fetch: true, FormData: true },
    FormData: function () {},
    Promise, setTimeout: (f) => 0,
    fetch: (url, init) => {
      peticiones.push({ url, metodo: init.method, cabeceras: init.headers, mismaOrigen: init.credentials });
      const r = respuestas[Math.min(n++, respuestas.length - 1)];
      if (r === "red") return Promise.reject(new Error("sin red"));
      return Promise.resolve({ status: r.estado, json: () => Promise.resolve(r.datos) });
    },
  };
  contexto.window.fetch = contexto.fetch;
  vm.createContext(contexto);
  vm.runInContext(fuente, contexto);
  const evento = { target: form, preventDefault: () => previnido++ };
  const enviar = () => manejadores.submit(evento);
  return { enviar, peticiones, region, enlace, campos, boton, form, previnido: () => previnido, normal: () => enviadoNormal };
}

const esperar = () => new Promise((r) => setImmediate(r));
const textoAviso = (region) => (region.children[0] ? region.children[0].children.map((c) => c.textContent || c.tag) : null);

(async () => {
  const salida = {};

  // éxito: contador, aviso con "Ver carrito", token nuevo en todos los campos, foco/botón intactos
  let e = escenario([{ estado: 200, datos: { ok: true, msg: "Añadida al carrito: «Faro».", count: 3, carrito: "/c", csrf: "token-nuevo" } }]);
  e.enviar();
  await esperar(); await esperar(); await esperar();
  salida.exito = {
    previnido: e.previnido(), peticiones: e.peticiones.length, cabecera: e.peticiones[0].cabeceras["X-Requested-With"], metodo: e.peticiones[0].metodo,
    tokens: e.campos.map((c) => c.value), contador: e.enlace.children.map((c) => c.textContent), etiqueta: e.enlace.attrs["aria-label"],
    aviso: textoAviso(e.region), clase: e.region.children[0].className, boton: { disabled: e.boton.disabled, html: e.boton.innerHTML, ocupado: e.boton.attrs["aria-busy"] || null },
    normal: e.normal(),
  };

  // error del servidor: aviso de error sin "Ver carrito"; el contador no cambia
  e = escenario([{ estado: 403, datos: { ok: false, msg: "La página caducó.", csrf: "token-2" } }]);
  e.enviar();
  await esperar(); await esperar(); await esperar();
  salida.error = { aviso: textoAviso(e.region), clase: e.region.children[0].className, contador: e.enlace.children.length, tokens: e.campos.map((c) => c.value), normal: e.normal() };

  // sin red: se envía el formulario normal (vuelve a la página)
  e = escenario(["red"]);
  e.enviar();
  await esperar(); await esperar(); await esperar();
  salida.sinRed = { normal: e.normal(), boton: { disabled: e.boton.disabled, html: e.boton.innerHTML } };

  // dos envíos seguidos: van de uno en uno y el segundo usa el token que trajo el primero
  e = escenario([{ estado: 200, datos: { ok: true, msg: "uno", count: 1, csrf: "A" } }, { estado: 200, datos: { ok: true, msg: "dos", count: 2, csrf: "B" } }]);
  const valoresAlSalir = [];
  const original = e.peticiones.push.bind(e.peticiones);
  e.peticiones.push = (p) => { valoresAlSalir.push(e.campos[0].value); return original(p); };
  e.enviar(); e.enviar();
  for (let i = 0; i < 10; i++) await esperar();
  salida.cola = { peticiones: e.peticiones.length, tokensAlSalir: valoresAlSalir, final: e.campos.map((c) => c.value), contador: e.enlace.children.map((c) => c.textContent) };

  // un formulario sin data-agregar-carrito no se toca
  e = escenario([{ estado: 200, datos: { ok: true, msg: "x", count: 1, csrf: "Z" } }]);
  delete e.form.attrs["data-agregar-carrito"];
  e.enviar();
  await esperar(); await esperar();
  salida.otroFormulario = { previnido: e.previnido(), peticiones: e.peticiones.length };

  console.log(JSON.stringify(salida));
})();
