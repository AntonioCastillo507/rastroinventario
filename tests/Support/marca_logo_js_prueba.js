// Prueba de assets/js/marca_logo.js: el aviso del archivo elegido (tipo y tamaño).
// Uso: node tests/Support/marca_logo_js_prueba.js  -> imprime un JSON con los resultados
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "marca_logo.js"), "utf8");

const ventana = {};
const contexto = { window: ventana, document: { querySelector: () => null, getElementById: () => null } };
vm.createContext(contexto);
vm.runInContext(fuente, contexto);

const a = ventana.MarcaLogo.aviso;
const R = 524288, S = 102400;
console.log(JSON.stringify({
  png:       a("image/png", 1000, R, S),
  webp:      a("image/webp", 1000, R, S),
  svg:       a("image/svg+xml", 1000, R, S),
  pngJusto:  a("image/png", R, R, S),
  pngPesado: a("image/png", R + 1, R, S),
  svgJusto:  a("image/svg+xml", S, R, S),
  svgPesado: a("image/svg+xml", S + 1, R, S),
  jpeg:      a("image/jpeg", 1000, R, S),
  gif:       a("image/gif", 1000, R, S),
  vacio:     a("", 1000, R, S),
}));
