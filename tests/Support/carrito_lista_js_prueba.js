// Prueba de assets/js/carrito_lista.js con un DOM mínimo falso y un fetch simulado.
// Uso: node tests/Support/carrito_lista_js_prueba.js [respuesta_real.json]
// Imprime un JSON con el resultado de cada escenario; CarritoListaJsTest (PHP) lo compara. Si recibe un archivo con la respuesta
// REAL del servidor (la guarda la prueba PHP), la usa en el escenario "contrato" para que el JS y el servidor no se desincronicen.
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "carrito_lista.js"), "utf8");

class Nodo {
  constructor(tag) { this.tag = tag; this.children = []; this.attrs = {}; this.className = ""; this.textContent = ""; this.parentNode = null; this.escuchas = {}; this.disabled = false; this.value = ""; this.name = ""; this.type = ""; this.id = ""; this.action = ""; }
  setAttribute(k, v) { this.attrs[k] = String(v); if (k === "class") this.className = String(v); if (k === "id") this.id = String(v); }
  getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
  removeAttribute(k) { delete this.attrs[k]; }
  hasAttribute(k) { return k in this.attrs; }
  appendChild(n) { n.parentNode = this; this.children.push(n); return n; }
  removeChild(n) { this.children = this.children.filter((c) => c !== n); n.parentNode = null; return n; }
  addEventListener(t, f) { (this.escuchas[t] = this.escuchas[t] || []).push(f); }
  closest(sel) { let n = this; while (n) { if (coincide(n, sel)) return n; n = n.parentNode; } return null; }
  querySelectorAll(sel) { const r = []; const rec = (n) => { n.children.forEach((c) => { if (coincide(c, sel)) r.push(c); rec(c); }); }; rec(this); return r; }
  querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
  submit() { this.enviadoNormal = (this.enviadoNormal || 0) + 1; }
  focus() { this.enfocado = true; }
}

// Selectores simples: etiqueta, #id, .clase, [attr], [attr="valor"] (sin combinadores)
function coincide(n, sel) {
  const m = sel.match(/^([a-z]+)?(#[\w-]+)?((?:\.[\w-]+)*)((?:\[[\w-]+(?:="[^"]*")?\])*)$/);
  if (!m) throw new Error("selector no soportado: " + sel);
  if (m[1] && n.tag !== m[1]) return false;
  if (m[2] && n.attrs.id !== m[2].slice(1) && n.id !== m[2].slice(1)) return false;
  for (const c of (m[3] || "").split(".").filter(Boolean)) if (!(n.className || "").split(/\s+/).includes(c)) return false;
  for (const a of (m[4] || "").match(/\[[^\]]+\]/g) || []) {
    const p = a.slice(1, -1).split("=");
    const nombre = p[0]; const valor = p[1] === undefined ? null : p[1].replace(/^"|"$/g, "");
    if (!(nombre in n.attrs)) return false;
    if (valor !== null && n.attrs[nombre] !== valor) return false;
  }
  return true;
}

function nodo(tag, attrs = {}, texto = "") {
  const n = new Nodo(tag);
  Object.keys(attrs).forEach((k) => n.setAttribute(k, attrs[k]));
  if (attrs.name) n.name = attrs.name;
  if (attrs.value !== undefined) n.value = attrs.value;
  n.textContent = texto;
  return n;
}

// Una página de paso 1 con dos líneas (id 1: 2 unidades de 5; id 2: 1 unidad de 3)
function pagina(respuestas) {
  const peticiones = [];
  const raiz = nodo("div", { "data-carrito": "", "data-url-restaurar": "/r/?action=restaurar_carrito" });
  const avisos = nodo("div", { id: "carrito-avisos" }); raiz.appendChild(avisos);
  const estado = nodo("p", { id: "carrito-estado" }); raiz.appendChild(estado);
  const conteo = nodo("p", { "data-carrito-conteo": "" }, "2 piezas"); raiz.appendChild(conteo);
  const tokens = [nodo("input", { name: "csrf_token", value: "t0" }), nodo("input", { name: "csrf_token", value: "t0" })];
  tokens.forEach((t) => raiz.appendChild(t));
  const lineas = {};
  [[1, 2, 5, "$20.00"], [2, 1, 3, "$5.50"]].forEach(([id, cant, max, sub]) => {
    const li = nodo("li", { "data-linea": id });
    const formCant = nodo("form", { "data-carrito-form": "" }); formCant.action = "/r/?action=actualizar_carrito&id=" + id;
    const menos = nodo("button", { "data-accion": "menos", name: "cantidad", value: String(Math.max(1, cant - 1)) });
    const valor = nodo("span", { "data-linea-cantidad": "" }, String(cant));
    const mas = nodo("button", { "data-accion": "mas", name: "cantidad", value: String(Math.min(max, cant + 1)) });
    [menos, valor, mas].forEach((x) => formCant.appendChild(x));
    li.appendChild(formCant);
    li.appendChild(nodo("p", { "data-linea-subtotal": "" }, sub));
    const formQuitar = nodo("form", { "data-carrito-form": "", "data-quitar": "" }); formQuitar.action = "/r/?action=quitar_carrito&id=" + id;
    const botonQuitar = nodo("button", { name: "", value: "" });
    formQuitar.appendChild(botonQuitar); li.appendChild(formQuitar);
    raiz.appendChild(li);
    lineas[id] = { li, formCant, menos, mas, valor, formQuitar, botonQuitar };
  });
  const resumen = {};
  ["subtotal", "itbms", "total"].forEach((k) => { resumen[k] = nodo("dd", { "data-resumen": k }, "$0"); raiz.appendChild(resumen[k]); });
  const enlace = nodo("a", { class: "nav-carrito" }); const cuenta = nodo("span", { class: "cart-count" }, "2"); enlace.appendChild(cuenta);
  const cuerpo = nodo("body"); cuerpo.appendChild(raiz); cuerpo.appendChild(enlace);
  const manejadores = {};
  let recargas = 0;
  let n = 0;
  const contexto = {
    document: {
      addEventListener: (t, f) => (manejadores[t] = f),
      querySelector: (s) => cuerpo.querySelector(s),
      querySelectorAll: (s) => cuerpo.querySelectorAll(s),
      createElement: (t) => new Nodo(t),
    },
    window: { fetch: true, FormData: true, location: { reload: () => recargas++ } },
    FormData: class { constructor(form) { this.campos = {}; (form && form.campos) ? Object.assign(this.campos, form.campos) : null; this.deForm = form; } set(k, v) { this.campos[k] = v; } append(k, v) { this.campos[k] = v; } },
    Promise,
    fetch: (url, init) => {
      peticiones.push({ url, metodo: init.method, cabecera: init.headers["X-Requested-With"], campos: init.body.campos, misma: init.credentials });
      const r = respuestas[Math.min(n++, respuestas.length - 1)];
      if (r === "red") return Promise.reject(new Error("sin red"));
      return Promise.resolve({ status: r.estado, json: () => Promise.resolve(r.datos) });
    },
  };
  contexto.window.fetch = contexto.fetch;
  contexto.window.FormData = contexto.FormData;
  vm.createContext(contexto);
  vm.runInContext(fuente, contexto);
  let previnido = 0;
  const enviar = (form, submitter) => manejadores.submit({ target: form, submitter, preventDefault: () => previnido++ });
  return { enviar, peticiones, raiz, avisos, estado, conteo, tokens, lineas, resumen, enlace, cuenta, cuerpo, recargas: () => recargas, previnido: () => previnido };
}

const esperar = async () => { for (let i = 0; i < 12; i++) await new Promise((r) => setImmediate(r)); };
const textos = (p) => p.children.map((c) => c.textContent || c.tag);
const estadoLinea = (l) => ({ cantidad: l.valor.textContent, menos: { v: l.menos.value, d: l.menos.disabled }, mas: { v: l.mas.value, d: l.mas.disabled }, subtotal: l.li.querySelector("[data-linea-subtotal]").textContent });

(async () => {
  const salida = {};
  const real = process.argv[2] ? JSON.parse(fs.readFileSync(process.argv[2], "utf8")) : null;

  // "+" en la línea 1: el servidor devuelve el estado y la pantalla solo lo escribe
  let p = pagina([{ estado: 200, datos: { ok: true, msg: "«Faro»: 3 unidades.", count: 2, lineas: [{ id: 1, cantidad: 3, max: 5, subtotal: "30.00" }, { id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "35.50", itbms: "2.49", total: "37.99" }, avisos: [], deshacer: null, csrf: "t1" } }]);
  p.enviar(p.lineas[1].formCant, p.lineas[1].mas);
  await esperar();
  salida.mas = { previnido: p.previnido(), peticiones: p.peticiones.length, url: p.peticiones[0].url, metodo: p.peticiones[0].metodo, cabecera: p.peticiones[0].cabecera, campos: p.peticiones[0].campos,
    linea1: estadoLinea(p.lineas[1]), linea2: estadoLinea(p.lineas[2]), resumen: ["subtotal", "itbms", "total"].map((k) => p.resumen[k].textContent), conteo: p.conteo.textContent,
    tokens: p.tokens.map((t) => t.value), estado: p.estado.textContent, ocupado: p.lineas[1].li.getAttribute("aria-busy"), recargas: p.recargas() };

  // la línea llega al tope del stock: "+" se desactiva y "−" baja a 4
  p = pagina([{ estado: 200, datos: { ok: true, msg: "x", count: 2, lineas: [{ id: 1, cantidad: 5, max: 5, subtotal: "50.00" }, { id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "55.50", itbms: "3.89", total: "59.39" }, avisos: [], deshacer: null, csrf: "t1" } }]);
  p.enviar(p.lineas[1].formCant, p.lineas[1].mas);
  await esperar();
  salida.tope = estadoLinea(p.lineas[1]);
  salida.tope.foco = { menos: !!p.lineas[1].menos.enfocado, mas: !!p.lineas[1].mas.enfocado };

  // quitar: la línea sale, el contador baja y aparece "Deshacer" con su botón
  p = pagina([{ estado: 200, datos: { ok: true, msg: "Quitaste «Faro» de tu carrito.", count: 1, lineas: [{ id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "5.50", itbms: "0.39", total: "5.89" }, avisos: [], deshacer: { id: 1, nombre: "Faro" }, csrf: "t2" } }]);
  p.enviar(p.lineas[1].formQuitar, p.lineas[1].botonQuitar);
  await esperar();
  const caja = p.avisos.children[0];
  salida.quitar = { lineas: p.raiz.querySelectorAll("[data-linea]").map((l) => l.getAttribute("data-linea")), conteo: p.conteo.textContent, contador: p.cuenta.textContent,
    foco: !!(caja && caja.children[1].enfocado), aviso: caja ? textos(caja) : null, claseAviso: caja ? caja.className : null, rol: caja ? caja.getAttribute("role") : null, resumenTotal: p.resumen.total.textContent };

  // deshacer: pide restaurar con el token vigente y recarga
  const boton = caja.children[1];
  p.peticiones.length = 0;
  boton.escuchas.click[0]();
  await esperar();
  salida.deshacer = { peticiones: p.peticiones.length };

  // quitar la última: recarga para que el servidor pinte el carrito vacío con su "Deshacer"
  p = pagina([{ estado: 200, datos: { ok: true, msg: "", count: 0, lineas: [], resumen: { subtotal: "0.00", itbms: "0.00", total: "0.00" }, avisos: [], deshacer: { id: 2, nombre: "Otra" }, csrf: "t3" } }]);
  p.enviar(p.lineas[2].formQuitar, p.lineas[2].botonQuitar);
  await esperar();
  salida.vacio = { recargas: p.recargas() };

  // restaurar desde el aviso pintado por el servidor: recarga al terminar bien
  p = pagina([{ estado: 200, datos: { ok: true, msg: "Volvió", count: 3, lineas: [], resumen: null, avisos: [], deshacer: null, csrf: "t4" } }]);
  const formRest = nodo("form", { "data-carrito-form": "", "data-restaurar": "" }); formRest.action = "/r/?action=restaurar_carrito&id=1"; p.raiz.appendChild(formRest);
  p.enviar(formRest, null);
  await esperar();
  salida.restaurar = { peticiones: p.peticiones.length, recargas: p.recargas() };

  // avisos del servidor (pieza desactivada) y error 403 sin estado: se muestran y la pantalla no se toca
  p = pagina([{ estado: 403, datos: { ok: false, msg: "La página caducó.", csrf: "t5" } }]);
  p.enviar(p.lineas[1].formCant, p.lineas[1].menos);
  await esperar();
  salida.caducado = { avisos: p.avisos.children.map((c) => c.textContent), clase: p.avisos.children[0] && p.avisos.children[0].className, tokens: p.tokens.map((t) => t.value), linea1: estadoLinea(p.lineas[1]), ocupado: p.lineas[1].li.getAttribute("aria-busy") };
  p = pagina([{ estado: 200, datos: { ok: true, msg: "", count: 1, lineas: [{ id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "5.50", itbms: "0.39", total: "5.89" }, avisos: ["«Pieza» ya no está disponible y se quitó de tu carrito."], deshacer: null, csrf: "t6" } }]);
  p.enviar(p.lineas[2].formCant, p.lineas[2].mas);
  await esperar();
  salida.avisosServidor = { avisos: p.avisos.children.map((c) => c.textContent), clase: p.avisos.children[0].className, rol: p.avisos.children[0].getAttribute("role"), lineas: p.raiz.querySelectorAll("[data-linea]").length };
  // un aviso viejo hecho por el JS se reemplaza, no se acumula
  p.enviar(p.lineas[2].formCant, p.lineas[2].mas);
  await esperar();
  salida.avisosServidor.trasOtraAccion = p.avisos.children.length;

  // sin red: se envía el formulario de la forma normal, con el botón que se pulsó
  p = pagina(["red"]);
  p.enviar(p.lineas[1].formCant, p.lineas[1].mas);
  await esperar();
  const oculto = p.lineas[1].formCant.children[p.lineas[1].formCant.children.length - 1];
  salida.sinRed = { normal: p.lineas[1].formCant.enviadoNormal || 0, oculto: oculto.tag === "input" ? { name: oculto.name, value: oculto.value, type: oculto.type } : null };

  // dos acciones seguidas van de una en una
  p = pagina([{ estado: 200, datos: { ok: true, msg: "uno", count: 2, lineas: [{ id: 1, cantidad: 3, max: 5, subtotal: "30.00" }, { id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "35.50", itbms: "2.49", total: "37.99" }, avisos: [], deshacer: null, csrf: "A" } },
    { estado: 200, datos: { ok: true, msg: "dos", count: 2, lineas: [{ id: 1, cantidad: 4, max: 5, subtotal: "40.00" }, { id: 2, cantidad: 1, max: 3, subtotal: "5.50" }], resumen: { subtotal: "45.50", itbms: "3.19", total: "48.69" }, avisos: [], deshacer: null, csrf: "B" } }]);
  p.enviar(p.lineas[1].formCant, p.lineas[1].mas); p.enviar(p.lineas[1].formCant, p.lineas[1].mas);
  await esperar();
  salida.cola = { peticiones: p.peticiones.length, cantidad: p.lineas[1].valor.textContent, tokens: p.tokens.map((t) => t.value) };

  // un formulario que no es del carrito no se toca
  p = pagina([{ estado: 200, datos: { ok: true, count: 0, lineas: [], csrf: "Z" } }]);
  const ajeno = nodo("form", {}); ajeno.action = "/otro";
  p.enviar(ajeno, null);
  await esperar();
  salida.ajeno = { previnido: p.previnido(), peticiones: p.peticiones.length };

  // contrato con el servidor: la respuesta REAL de actualizar_carrito (guardada por la prueba PHP) se aplica sin tropiezos
  if (real) {
    const id = real.lineas[0].id;
    p = pagina([{ estado: 200, datos: real }]);
    const l = p.lineas[1];
    l.li.attrs["data-linea"] = String(id); // la línea de la página falsa pasa a ser la de la respuesta real
    p.enviar(l.formCant, l.mas);
    await esperar();
    salida.contrato = { cantidad: l.valor.textContent, subtotal: l.li.querySelector("[data-linea-subtotal]").textContent, resumen: ["subtotal", "itbms", "total"].map((k) => p.resumen[k].textContent), tokens: p.tokens.map((t) => t.value), recargas: p.recargas() };
  }
  console.log(JSON.stringify(salida));
})();
