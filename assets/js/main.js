
"use strict";
document.addEventListener("DOMContentLoaded", function () {

  // Auto-cerrar alertas de éxito/info
  document.querySelectorAll(".alert-success,.alert-info").forEach(function(el){
    setTimeout(function(){ var a=bootstrap.Alert.getOrCreateInstance(el); if(a)a.close(); },4500);
  });

  // Toggle contraseña
  document.querySelectorAll(".toggle-pass").forEach(function(btn){
    btn.addEventListener("click",function(){
      var t=document.getElementById(btn.dataset.target);
      if(!t) return;
      t.type=t.type==="password"?"text":"password";
      var i=btn.querySelector("i");
      if(i){ i.classList.toggle("bi-eye",t.type==="text"); i.classList.toggle("bi-eye-slash",t.type==="password"); }
    });
  });

  // Textarea auto-expandible
  document.querySelectorAll("textarea.auto-exp").forEach(function(ta){
    ta.addEventListener("input", function(){
      this.style.height = "auto";
      this.style.height = (this.scrollHeight + 4) + "px";
    });
  });

  // Preview total en compra individual
  var cantInput = document.querySelector("input[name=\"cantidad\"]");
  var precioEl  = document.querySelector("[data-precio]");
  var totalEl   = document.getElementById("totalEstimado");
  if (cantInput && precioEl && totalEl) {
    function recalc(){ totalEl.textContent = "$"+(parseFloat(precioEl.dataset.precio||0)*parseInt(cantInput.value||0)).toFixed(2); }
    cantInput.addEventListener("input", recalc); recalc();
  }

  // Los filtros del panel se aplican al cambiar el select; los del catálogo de la tienda llevan botón "Aplicar" (WCAG 3.2.2)
  document.querySelectorAll("#fAdm select").forEach(function(sel){
    sel.addEventListener("change", function(){ this.closest("form").submit(); });
  });

  // Las secciones de la portada aparecen con suavidad al entrar en pantalla (sin librerías); sin movimiento si el usuario lo pidió
  var sinMovimiento = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var seccionesPortada = document.querySelectorAll(".portada-seccion");
  if (!sinMovimiento && "IntersectionObserver" in window && seccionesPortada.length) {
    var vigia = new IntersectionObserver(function(entradas){
      entradas.forEach(function(e){ if (e.isIntersecting) { e.target.classList.add("rv-visto"); vigia.unobserve(e.target); } });
    }, { threshold: 0.1 });
    seccionesPortada.forEach(function(s){ s.classList.add("rv"); vigia.observe(s); });
  }

  // Tarjetas de pieza, filas de las listas y tarjetas de formulario entran escalonadas al aparecer (0 a 300 ms, de 50 en 50); sin movimiento reducido
  var itemsRv = document.querySelectorAll(".tarjeta-pieza:not(.portada-seccion .tarjeta-pieza), .lista-tabla tbody tr:not(.lista-vacia), .form-card");
  if (!sinMovimiento && "IntersectionObserver" in window && itemsRv.length) {
    var vigiaItems = new IntersectionObserver(function(entradas){
      var n = 0;
      entradas.forEach(function(e){
        if (!e.isIntersecting) return;
        e.target.style.setProperty("--rv-retraso", Math.min(n * 50, 300) + "ms");
        e.target.classList.add("rv-visto");
        vigiaItems.unobserve(e.target);
        n++;
      });
    }, { threshold: 0.05 });
    itemsRv.forEach(function(i){ i.classList.add("rv-item"); vigiaItems.observe(i); });
  }

  // El menú plegable se cierra al elegir una opción (las anclas de la portada no recargan la página)
  document.querySelectorAll(".navbar-collapse").forEach(function(menu){
    menu.addEventListener("click", function(e){
      var enlace = e.target.closest ? e.target.closest("a.nav-link, a.dropdown-item") : null;
      if (!enlace || !menu.classList.contains("show")) return;
      bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
    });
  });

});
