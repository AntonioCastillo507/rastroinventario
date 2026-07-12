
"use strict";
document.addEventListener("DOMContentLoaded", function () {

  // Auto-cerrar alertas de éxito/info
  document.querySelectorAll(".alert-success,.alert-info").forEach(function(el){
    setTimeout(function(){ var a=bootstrap.Alert.getOrCreateInstance(el); if(a)a.close(); },4500);
  });

  // Confirmar antes de eliminar
  document.querySelectorAll("[data-confirm]").forEach(function(el){
    el.addEventListener("click",function(e){ if(!confirm(el.dataset.confirm))e.preventDefault(); });
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

  // Botón flotante "Volver" — aparece en páginas internas
  var search = window.location.search;
  var isInternal = search.includes("action=") || search.includes("ver=catalogo") || search.includes("seccion=");
  if (isInternal && !document.querySelector(".btn-float-back")) {
    var btn = document.createElement("button");
    btn.className = "btn-float-back";
    btn.title = "Volver atrás";
    btn.innerHTML = "<i class=\"bi bi-arrow-left\"></i>";
    btn.onclick = function(){ history.back(); };
    document.body.appendChild(btn);
  }

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

  // Auto-submit filtros al cambiar selects
  document.querySelectorAll("#frmFiltro select, #fAdm select").forEach(function(sel){
    sel.addEventListener("change", function(){ this.closest("form").submit(); });
  });

  // Highlight nav activo por URL
  var href = window.location.href;
  document.querySelectorAll(".public-nav .nav-link").forEach(function(link){
    var lh = link.getAttribute("href")||"";
    if (lh !== "#" && lh !== "" && href.startsWith(lh) && lh.length > 3) {
      link.classList.add("nav-active");
    }
  });

});
