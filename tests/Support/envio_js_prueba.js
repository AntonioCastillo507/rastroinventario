// Prueba de assets/js/envio.js con un DOM mínimo falso. Uso: node tests/Support/envio_js_prueba.js
// Imprime un JSON con el resultado de cada escenario; la prueba de PHP (EnvioJsTest) lo compara.
"use strict";
const fs = require("fs");
const path = require("path");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "envio.js"), "utf8");

function boton(opciones = {}) {
  const attrs = Object.assign({}, opciones.attrs || {});
  const b = {
    disabled: false,
    innerHTML: opciones.html || "Enviar",
    dataset: {},
    form: null,
    setAttribute(k, v) { attrs[k] = v; },
    removeAttribute(k) { delete attrs[k]; },
    hasAttribute(k) { return k in attrs; },
    getAttribute(k) { return k in attrs ? attrs[k] : null; },
    attrs,
  };
  return b;
}

function escenario(opciones) {
  const botones = (opciones.botones || [boton()]);
  const form = {
    method: opciones.metodo || "post",
    attrs: opciones.formAttrs || {},
    hasAttribute(k) { return k in this.attrs; },
    querySelectorAll: () => botones,
  };
  botones.forEach((b) => { b.form = form; });
  const escuchas = { document: {}, window: {} };
  let temporizadores = [];
  const doc = {
    addEventListener: (t, f) => { escuchas.document[t] = f; },
    querySelectorAll: (sel) => (sel === "button[data-html-original]" ? botones.filter((b) => b.dataset.htmlOriginal !== undefined) : []),
  };
  const win = { addEventListener: (t, f) => { escuchas.window[t] = f; } };
  const setTimeoutFalso = (f) => { temporizadores.push(f); return temporizadores.length; };
  new Function("document", "window", "setTimeout", fuente)(doc, win, setTimeoutFalso);
  const enviar = (extra = {}) => {
    const evento = Object.assign({ target: form, submitter: null, defaultPrevented: false }, extra);
    escuchas.document.submit(evento);
    // el botón se desactiva DESPUÉS de que el envío ya se decidió, no durante el evento
    const antes = botones.map((b) => b.disabled);
    temporizadores.forEach((f) => f());
    temporizadores = [];
    return antes;
  };
  const estado = () => botones.map((b) => ({ disabled: b.disabled, html: b.innerHTML, busy: b.getAttribute("aria-busy"), guardado: b.dataset.htmlOriginal ?? null }));
  return { form, botones, enviar, estado, escuchas };
}

const r = {};

// 1. envío normal: el botón se desactiva y dice "Enviando…" (y no antes de que corra el evento)
{
  const e = escenario({ botones: [boton({ html: '<i class="bi bi-save"></i> Guardar' })] });
  const antes = e.enviar();
  r.normal = { antes, despues: e.estado()[0] };
}
// 2. un segundo envío no vuelve a guardar el "Enviando…" como original
{
  const e = escenario({ botones: [boton({ html: "Pagar" })] });
  e.enviar(); e.enviar();
  r.dobleEnvio = e.estado()[0];
}
// 3. un formulario GET (buscador) no se toca
{
  const e = escenario({ metodo: "get" });
  e.enviar();
  r.get = e.estado()[0];
}
// 4. formulario o botón con data-no-bloquear (Salir)
{
  const e = escenario({ formAttrs: { "data-no-bloquear": "" } });
  e.enviar();
  r.formularioExento = e.estado()[0];
  const e2 = escenario({ botones: [boton({ attrs: { "data-no-bloquear": "" } })] });
  e2.enviar();
  r.botonExento = e2.estado()[0];
}
// 5. un envío cancelado (confirm() dijo que no) no desactiva nada
{
  const e = escenario({});
  e.enviar({ defaultPrevented: true });
  r.cancelado = e.estado()[0];
}
// 6. con submitter solo se desactiva ese botón
{
  const a = boton({ html: "Actualizar" }), q = boton({ html: "Quitar" });
  const e = escenario({ botones: [a, q] });
  e.enviar({ submitter: q });
  r.conSubmitter = e.estado();
}
// 7. al volver con "Atrás" (pageshow persisted) el botón se restaura
{
  const e = escenario({ botones: [boton({ html: "<b>Confirmar</b>" })] });
  e.enviar();
  e.escuchas.window.pageshow({ persisted: true });
  r.restaurado = e.estado()[0];
  const e2 = escenario({ botones: [boton({ html: "Ok" })] });
  e2.enviar();
  e2.escuchas.window.pageshow({ persisted: false });
  r.noRestauradoSiNoEsBfcache = e2.estado()[0];
}
console.log(JSON.stringify(r));
