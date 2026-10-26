// Prueba de assets/js/parte_imagen.js: la función de avisos (los mismos umbrales que ImageHelper::avisos).
// Uso: node tests/Support/parte_imagen_js_prueba.js  -> imprime un JSON con los resultados
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "parte_imagen.js"), "utf8");

const ventana = {};
const contexto = { window: ventana, document: { querySelector: () => null, getElementById: () => null } };
vm.createContext(contexto);
vm.runInContext(fuente, contexto);

const a = ventana.ParteImagen.avisos;
const casos = {
  normal:    a(1600, 1200, 0.67, 1.5, 800),
  cuadrada:  a(1000, 1000, 0.67, 1.5, 800),
  alargada:  a(2000, 400, 0.67, 1.5, 800),
  alta:      a(500, 1600, 0.67, 1.5, 800),
  baja:      a(700, 600, 0.67, 1.5, 800),
  justo800:  a(800, 800, 0.67, 1.5, 800),
  ambos:     a(600, 100, 0.67, 1.5, 800),
  ancha21:   a(2000, 1000, 0.67, 1.5, 800),
  alta58:    a(1000, 1600, 0.67, 1.5, 800),
  alta57:    a(1000, 1400, 0.67, 1.5, 800),
  baja799:   a(799, 799, 0.67, 1.5, 800),
  ceroAlto:  a(100, 0, 0.67, 1.5, 800),
};
console.log(JSON.stringify(casos));
