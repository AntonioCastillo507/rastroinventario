"use strict";
// Gráficas y contadores del dashboard. Los datos salen de #dash-datos (los pone el servidor);
// los colores, de los tokens de tema.css, así cambian al alternar el tema.
(function () {
  var nodo = document.getElementById("dash-datos");
  if (!nodo) return;
  var datos = {};
  try { datos = JSON.parse(nodo.textContent || "{}"); } catch (e) { return; }
  var sinMovimiento = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var raiz = document.documentElement;
  var graficas = [];

  function token(n) { return getComputedStyle(raiz).getPropertyValue(n).trim(); }
  function dinero(n) { return "$" + Number(n).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function recortar(t) { t = String(t); return t.length > 18 ? t.slice(0, 17) + "…" : t; }

  // Contadores de las tarjetas: solo al entrar; el valor final ya está escrito en el HTML
  function contadores() {
    if (sinMovimiento) return;
    document.querySelectorAll("[data-contar]").forEach(function (el) {
      var fin = parseFloat(el.getAttribute("data-contar")) || 0;
      var esDinero = el.getAttribute("data-formato") === "dinero";
      var t0 = performance.now();
      function paso(t) {
        var p = Math.min(1, (t - t0) / 600), v = fin * (1 - Math.pow(1 - p, 3));
        el.textContent = esDinero ? dinero(v) : String(Math.round(v));
        if (p < 1) requestAnimationFrame(paso);
      }
      requestAnimationFrame(paso);
    });
  }

  function lienzo(id) { var c = document.getElementById(id); return c ? c : null; }

  function barras(id, nombres, valores, unidad, c) {
    var el = lienzo(id);
    if (!el) return;
    graficas.push(new Chart(el, {
      type: "bar",
      data: { labels: nombres, datasets: [{ data: valores, backgroundColor: c.acento, borderRadius: 4, borderSkipped: false, barThickness: 14 }] },
      options: {
        indexAxis: "y", maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { title: function (it) { return nombres[it[0].dataIndex]; }, label: function (it) { return it.parsed.x + " " + unidad; } } }
        },
        scales: {
          x: { beginAtZero: true, grid: { color: c.rejilla }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 5 } },
          y: { grid: { display: false }, border: { color: c.rejilla }, ticks: { color: c.texto, autoSkip: false, callback: function (v) { return recortar(this.getLabelForValue(v)); } } }
        }
      }
    }));
  }

  function dibujar() {
    graficas.forEach(function (g) { g.destroy(); });
    graficas = [];
    if (typeof Chart === "undefined") return;
    var c = {
      acento: token("--chart-1"), texto: token("--text"), tenue: token("--text-muted"),
      rejilla: token("--hairline"), superficie: token("--surface"), elevada: token("--surface-elevated"), fuerte: token("--text-strong")
    };
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = c.tenue;
    Chart.defaults.animation = sinMovimiento ? false : { duration: 700, easing: "easeOutQuart" };

    var s = datos.serie, el = lienzo("dgLinea");
    if (s && el) {
      var sets = [{ label: "Periodo actual", data: s.actual, borderColor: c.acento, borderWidth: 2, pointRadius: s.labels.length <= 2 ? 4 : 0, pointHoverRadius: 5, pointHoverBackgroundColor: c.acento, pointHoverBorderColor: c.superficie, pointHoverBorderWidth: 2, tension: 0.3 }];
      if (s.anterior && s.anterior.length) sets.push({ label: "Periodo anterior", data: s.anterior, borderColor: c.tenue, borderDash: [5, 4], borderWidth: 2, pointRadius: 0, tension: 0.3 });
      graficas.push(new Chart(el, {
        type: "line", data: { labels: s.labels, datasets: sets },
        options: {
          maintainAspectRatio: false, interaction: { mode: "index", intersect: false },
          plugins: { legend: { display: false }, tooltip: { backgroundColor: c.elevada, titleColor: c.fuerte, bodyColor: c.texto, borderColor: c.rejilla, borderWidth: 1, padding: 10, callbacks: { label: function (it) { return it.dataset.label + ": " + dinero(it.parsed.y); } } } },
          scales: {
            x: { grid: { display: false }, border: { color: c.rejilla }, ticks: { maxTicksLimit: 6, maxRotation: 0 } },
            y: { beginAtZero: true, grid: { color: c.rejilla }, border: { display: false }, ticks: { maxTicksLimit: 5, callback: function (v) { return "$" + v; } } }
          }
        }
      }));
    }

    var cat = datos.categorias; el = lienzo("dgDona");
    if (cat && el) {
      var colores = cat.map(function (x) { return token(x.color ? "--chart-" + x.color : "--chart-otras"); });
      graficas.push(new Chart(el, {
        type: "doughnut",
        data: { labels: cat.map(function (x) { return x.nombre; }), datasets: [{ data: cat.map(function (x) { return x.total; }), backgroundColor: colores, borderColor: c.superficie, borderWidth: 2 }] },
        options: { maintainAspectRatio: false, cutout: "68%", plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (it) { return it.label + ": " + dinero(it.parsed); } } } } }
      }));
    }

    if (datos.partes) barras("dgPartes", datos.partes.map(function (x) { return x.nombre; }), datos.partes.map(function (x) { return x.unidades; }), "unidades", c);
    if (datos.marcas) barras("dgMarcas", datos.marcas.map(function (x) { return x.nombre; }), datos.marcas.map(function (x) { return x.unidades; }), "unidades", c);
    if (datos.estados) barras("dgEstados", datos.estados.map(function (x) { return x.nombre; }), datos.estados.map(function (x) { return x.n; }), "pedidos", c);
  }

  dibujar();
  contadores();
  // Al alternar el tema los colores cambian: se redibuja con los tokens nuevos, sin animación de entrada
  new MutationObserver(function () { dibujar(); }).observe(raiz, { attributes: true, attributeFilter: ["data-theme"] });
})();
