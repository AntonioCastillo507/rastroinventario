// Prueba de assets/js/confirmar.js con un DOM mínimo falso. Uso: node tests/Support/confirmar_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; ModalConfirmarJsTest lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "confirmar.js"), "utf8");

function montar(opciones = {}) {
  const foco = { actual: null };
  const escuchasDoc = {};
  const crear = (nombre) => ({ nombre, textContent: "", className: "", focus() { foco.actual = this; } });
  const partes = { titulo: crear("titulo"), texto: crear("texto"), confirmar: crear("confirmar"), cancelar: crear("cancelar") };
  const escuchasDialogo = {};
  const dialogo = {
    abierto: false, aperturas: 0,
    addEventListener(t, f) { (escuchasDialogo[t] = escuchasDialogo[t] || []).push(f); },
    querySelector(sel) { return partes[sel.replace("[data-mc-", "").replace("]", "")]; },
    showModal() { this.abierto = true; this.aperturas++; },
    close() { if (!this.abierto) return; this.abierto = false; (escuchasDialogo.close || []).forEach((f) => f()); },
  };
  if (opciones.sinDialogo) delete dialogo.showModal;
  // los botones reciben el clic con addEventListener: se simula con un registro propio
  for (const k of ["cancelar", "confirmar"]) { partes[k].escuchas = {}; partes[k].addEventListener = (t, f) => { partes[k].escuchas[t] = f; }; }
  const botonAbre = { nombre: "boton-abre", focus() { foco.actual = this; }, form: null };
  const doc = {
    activeElement: botonAbre,
    getElementById: (id) => (id === "modalConfirmar" ? dialogo : null),
    addEventListener: (t, f, cap) => { escuchasDoc[t] = escuchasDoc[t] || []; escuchasDoc[t].push({ f, cap: !!cap }); },
    contains: () => true,
  };
  Object.defineProperty(doc, "activeElement", { get: () => foco.actual || botonAbre });
  let confirmaciones = [];
  const win = { confirm: (m) => { confirmaciones.push(m); return opciones.confirmaDice === true; } };
  vm.runInNewContext(fuente, { document: doc, window: win, JSON, Object });
  (escuchasDoc.DOMContentLoaded || []).forEach((x) => x.f());
  return { doc, escuchasDoc, escuchasDialogo, dialogo, partes, foco, botonAbre, confirmaciones: () => confirmaciones };
}

function formulario(attrs, valores = {}, boton) {
  const f = {
    attrs, elements: valores, enviado: [], __confirmado: undefined,
    getAttribute(k) { return k in attrs ? attrs[k] : null; },
    requestSubmit(s) { this.enviado.push(["requestSubmit", s ? s.nombre : null]); },
    submit() { this.enviado.push(["submit"]); },
  };
  if (boton) boton.form = f;
  return f;
}

function enviar(m, form, boton) {
  const evento = { target: form, submitter: boton || null, impedido: false, preventDefault() { this.impedido = true; } };
  (m.escuchasDoc.submit || []).forEach((x) => x.f(evento));
  return evento;
}

const dato = (o) => JSON.stringify(Object.assign({ titulo: "Desactivar «Faro»", texto: "Deja de verse.", boton: "Desactivar", cancelar: "Conservar", peligro: true }, o || {}));
const out = {};

{ // 1. sin data-confirmar no pregunta
  const m = montar(); const f = formulario({}); const e = enviar(m, f);
  out.sinDatos = { impedido: e.impedido, aperturas: m.dialogo.aperturas };
}
{ // 2. con datos: abre, escribe, foco en cancelar; peligro o no cambia el botón
  const m = montar(); const b = m.botonAbre; const f = formulario({ "data-confirmar": dato() }, {}, b);
  const e = enviar(m, f, b);
  out.abre = { impedido: e.impedido, abierto: m.dialogo.abierto, titulo: m.partes.titulo.textContent, texto: m.partes.texto.textContent, boton: m.partes.confirmar.textContent, clase: m.partes.confirmar.className, cancelar: m.partes.cancelar.textContent, foco: m.foco.actual.nombre, enviado: f.enviado };
  const m2 = montar(); const f2 = formulario({ "data-confirmar": dato({ peligro: false, cancelar: undefined }) });
  enviar(m2, f2); out.noPeligro = { clase: m2.partes.confirmar.className, cancelar: m2.partes.cancelar.textContent };
}
{ // 3. cancelar: no envía y el foco vuelve a lo que abrió
  const m = montar(); const b = m.botonAbre; const f = formulario({ "data-confirmar": dato() }, {}, b);
  enviar(m, f, b); m.foco.actual = m.partes.cancelar; m.partes.cancelar.escuchas.click();
  out.cancelar = { abierto: m.dialogo.abierto, enviado: f.enviado, foco: m.foco.actual.nombre };
}
{ // 4. confirmar: reenvía el mismo formulario con el mismo botón, y el segundo submit pasa sin preguntar
  const m = montar(); const b = m.botonAbre; const f = formulario({ "data-confirmar": dato() }, {}, b);
  enviar(m, f, b); m.partes.confirmar.escuchas.click();
  const segundo = enviar(m, f, b);
  out.confirmar = { abierto: m.dialogo.abierto, enviado: f.enviado, segundoImpedido: segundo.impedido, aperturas: m.dialogo.aperturas };
  const tercero = enviar(m, f, b);
  out.despues = { impedido: tercero.impedido, aperturas: m.dialogo.aperturas };
}
{ // 5. Esc (el navegador cierra el <dialog>): equivale a cancelar
  const m = montar(); const b = m.botonAbre; const f = formulario({ "data-confirmar": dato() }, {}, b);
  enviar(m, f, b); m.foco.actual = m.partes.cancelar; m.dialogo.close();
  out.esc = { enviado: f.enviado, foco: m.foco.actual.nombre };
}
{ // 6. clic en el fondo (el propio <dialog>) cancela; un clic dentro de la caja no
  const m = montar(); const f = formulario({ "data-confirmar": dato() }); enviar(m, f);
  m.escuchasDialogo.click.forEach((fn) => fn({ target: m.partes.titulo }));
  const dentro = m.dialogo.abierto;
  m.escuchasDialogo.click.forEach((fn) => fn({ target: m.dialogo }));
  out.fondo = { abiertoTrasClicDentro: dentro, abiertoTrasClicFuera: m.dialogo.abierto, enviado: f.enviado };
}
{ // 7. por campo: solo pregunta con los valores del mapa
  const mapa = JSON.stringify({ entregado: { titulo: "Entregar", texto: "Definitivo", boton: "Entregar" }, cancelado: { titulo: "Cancelar pedido", texto: "Se devuelve el stock", boton: "Cancelar", peligro: true } });
  const a = { "data-confirmar-campo": "estado", "data-confirmar": mapa };
  const res = {};
  for (const v of ["procesando", "entregado", "cancelado", "", "constructor"]) {
    const m = montar(); const f = formulario(a, { estado: { value: v } }); const e = enviar(m, f);
    res[v || "(vacío)"] = { impedido: e.impedido, titulo: m.partes.titulo.textContent };
  }
  const m = montar(); const e = enviar(m, formulario(a, {})); res.sinCampo = { impedido: e.impedido };
  out.porCampo = res;
}
{ // 8. datos rotos o sin título: no pregunta y no rompe
  const res = {};
  for (const [k, v] of [["jsonRoto", "{no es json"], ["sinTitulo", JSON.stringify({ texto: "x" })], ["nulo", "null"]]) {
    const m = montar(); const e = enviar(m, formulario({ "data-confirmar": v })); res[k] = { impedido: e.impedido, aperturas: m.dialogo.aperturas };
  }
  out.rotos = res;
}
{ // 9. foco atrapado con Tab / Shift+Tab entre los dos botones; otras teclas no se tocan
  const m = montar(); enviar(m, formulario({ "data-confirmar": dato() }));
  const tecla = (key, shift) => { const e = { key, shiftKey: shift, impedido: false, preventDefault() { this.impedido = true; } }; m.escuchasDialogo.keydown.forEach((fn) => fn(e)); return e; };
  m.foco.actual = m.partes.confirmar; const e1 = tecla("Tab", false); const f1 = m.foco.actual.nombre;
  m.foco.actual = m.partes.cancelar; const e2 = tecla("Tab", true); const f2 = m.foco.actual.nombre;
  m.foco.actual = m.partes.cancelar; const e3 = tecla("Tab", false); const f3 = m.foco.actual.nombre;
  m.foco.actual = m.partes.confirmar; const e4 = tecla("Tab", true); const f4 = m.foco.actual.nombre;
  m.foco.actual = m.partes.confirmar; const e5 = tecla("a", false);
  out.tab = { desdeUltimo: [e1.impedido, f1], atrasDesdePrimero: [e2.impedido, f2], delPrimeroAlUltimoLoDejaAlNavegador: [e3.impedido, f3], atrasDesdeUltimoLoDejaAlNavegador: [e4.impedido, f4], otraTecla: e5.impedido };
}
{ // 10. sin <dialog>: el aviso nativo decide
  const m = montar({ sinDialogo: true, confirmaDice: false }); const f = formulario({ "data-confirmar": dato() }); const e = enviar(m, f);
  const m2 = montar({ sinDialogo: true, confirmaDice: true }); const f2 = formulario({ "data-confirmar": dato() }); const e2 = enviar(m2, f2);
  out.sinDialogo = { no: { impedido: e.impedido, enviado: f.enviado, avisos: m.confirmaciones() }, si: { impedido: e2.impedido, enviado: f2.enviado } };
}

console.log(JSON.stringify(out));
