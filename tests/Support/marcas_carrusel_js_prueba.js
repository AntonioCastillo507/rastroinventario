// Prueba de assets/js/marcas_carrusel.js: las funciones puras del carrusel (repeticiones, cuándo se anima, salto de bloque).
// Uso: node tests/Support/marcas_carrusel_js_prueba.js  -> imprime un JSON con los resultados
"use strict";
const fs = require("fs");
const path = require("path");
const vm = require("vm");
const fuente = fs.readFileSync(path.join(__dirname, "..", "..", "assets", "js", "marcas_carrusel.js"), "utf8");

const ventana = {};
const contexto = { window: ventana, document: { querySelector: () => null } };
vm.createContext(contexto);
vm.runInContext(fuente, contexto);

const c = ventana.MarcasCarrusel;
const B = 1000;
console.log(JSON.stringify({
  velocidad: c.VELOCIDAD,
  minMarcas: c.MIN_MARCAS,
  rep: [c.repeticiones(1216, 912), c.repeticiones(390, 648), c.repeticiones(1216, 1216), c.repeticiones(1216, 1217), c.repeticiones(1216, 0), c.repeticiones(0, 912), c.repeticiones(2000, 100)],
  anima: {
    tres: c.debeAnimar(3, false), cuatro: c.debeAnimar(4, false), seis: c.debeAnimar(6, false),
    reducido: c.debeAnimar(6, true), cero: c.debeAnimar(0, false),
  },
  norm: {
    dentro: c.normalizar(1500, B), bordeAlto: c.normalizar(2000, B), pasaAlto: c.normalizar(2001, B), muyAlto: c.normalizar(2600, B),
    bordeBajo: c.normalizar(500, B), pasaBajo: c.normalizar(499, B), cero: c.normalizar(0, B), sinBloque: c.normalizar(123, 0),
  },
}));
