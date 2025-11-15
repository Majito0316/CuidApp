// js/app.js
(() => {
  // ---------- Utils ----------
  const $ = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  // Toast simple
  function toast(msg, type = "error") {
    let el = document.createElement("div");
    el.className = `toast ${type}`;
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add("show"));
    setTimeout(() => {
      el.classList.remove("show");
      setTimeout(() => el.remove(), 300);
    }, 1800);
  }

  // Marcar input como inválido por un momento
  function flashInvalid(input, msg) {
    input.classList.add("invalid");
    input.setCustomValidity(msg || "Dato inválido");
    input.reportValidity();
    setTimeout(() => {
      input.classList.remove("invalid");
      input.setCustomValidity("");
    }, 1400);
  }

  // ---------- Navegación en tarjetas ----------
  function setupNavCards() {
    // Si usas <div class="card" data-href="..."> en algún sitio:
    $$(".card[data-href]").forEach(card => {
      card.style.cursor = "pointer";
      card.addEventListener("click", () => {
        const href = card.getAttribute("data-href");
        if (href) window.location.href = href;
      });
    });
  }

  // ---------- Tabs (login/registro) ----------
  function setupAuthTabs() {
    const loginTab = $("#login-tab");
    const registerTab = $("#register-tab");
    const loginForm = $("#login-form");
    const registerForm = $("#register-form");
    if (!loginTab || !registerTab || !loginForm || !registerForm) return;

    loginTab.type = "button";
    registerTab.type = "button";

    loginTab.addEventListener("click", () => {
      loginTab.classList.add("active");
      registerTab.classList.remove("active");
      loginForm.classList.add("active");
      registerForm.classList.remove("active");
    });
    registerTab.addEventListener("click", () => {
      registerTab.classList.add("active");
      loginTab.classList.remove("active");
      registerForm.classList.add("active");
      loginForm.classList.remove("active");
    });
  }

  // ---------- Validaciones reutilizables ----------
  const REGEX = {
    nombre: /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/,
    email:  /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    pass:   /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s])(?!.*\s).{8,64}$/
  };

  function cleanSpaces(input) {
    input.value = input.value.replace(/\s+/g, ' ').trim();
  }

  // Extras visuales pedidos: si escriben números en "nombre", limpiar y alertar
  function setupNombreFilter(formSel = "#register-form") {
    const form = $(formSel);
    if (!form) return;
    const nombre = form.querySelector('input[name="nombre"]');
    if (!nombre) return;

    nombre.addEventListener("input", () => {
      // Si detectamos caracteres no permitidos, limpiamos todo y avisamos
      if (/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]/.test(nombre.value)) {
        nombre.value = "";             // Limpia el campo
        flashInvalid(nombre, "Solo letras (puedes usar espacios, apóstrofos o guiones).");
        toast("El nombre solo puede tener letras.", "error");
      } else {
        // Validación normal
        cleanSpaces(nombre);
        if (nombre.value && !REGEX.nombre.test(nombre.value)) {
          nombre.classList.add("invalid");
        } else {
          nombre.classList.remove("invalid");
        }
      }
    });
  }

  function setupRegisterValidation() {
    const form = $("#register-form");
    if (!form) return;

    const nombre = form.querySelector('input[name="nombre"]');
    const correo = form.querySelector('input[name="correo"]');
    const rol    = form.querySelector('select[name="rol"]');
    const pass   = form.querySelector('input[name="contraseña"]');
    const conf   = form.querySelector('input[name="confirmar"]');

    form.addEventListener("submit", (e) => {
      let ok = true;

      if (nombre) {
        cleanSpaces(nombre);
        if (!REGEX.nombre.test(nombre.value)) {
          ok = false; flashInvalid(nombre, "Nombre inválido.");
          toast("Revisa el nombre.", "error");
        }
      }
      if (correo) {
        cleanSpaces(correo);
        correo.value = correo.value.toLowerCase();
        if (!REGEX.email.test(correo.value)) {
          ok = false; flashInvalid(correo, "Correo inválido.");
          toast("Correo inválido.", "error");
        }
      }
      if (rol && !["paciente","cuidador","admin"].includes(rol.value)) {
        ok = false; flashInvalid(rol, "Selecciona un rol.");
      }
      if (pass && !REGEX.pass.test(pass.value)) {
        ok = false; flashInvalid(pass, "8–64 caracteres, mayúscula, minúscula, número y símbolo; sin espacios.");
        toast("Contraseña poco segura.", "error");
      }
      if (conf && conf.value !== pass.value) {
        ok = false; flashInvalid(conf, "Las contraseñas no coinciden.");
      }

      if (!ok) e.preventDefault();
    });
  }

  function setupLoginValidation() {
    const form = $("#login-form");
    if (!form) return;
    const correo = form.querySelector('input[name="correo"]');
    const pass   = form.querySelector('input[name="contraseña"]');

    form.addEventListener("submit", (e) => {
      let ok = true;
      if (correo) {
        cleanSpaces(correo);
        if (!REGEX.email.test(correo.value)) {
          ok = false; flashInvalid(correo, "Correo inválido."); toast("Correo inválido.", "error");
        }
      }
      if (pass && !pass.value) {
        ok = false; flashInvalid(pass, "Ingresa tu contraseña.");
      }
      if (!ok) e.preventDefault();
    });
  }

  // Logout con confirmación (si pones un botón .js-logout)
  function setupLogout() {
    $$(".js-logout").forEach(btn => {
      btn.addEventListener("click", (e) => {
        if (!confirm("¿Deseas cerrar sesión?")) e.preventDefault();
      });
    });
  }

  // ---------- Init ----------
  document.addEventListener("DOMContentLoaded", () => {
    setupNavCards();
    setupAuthTabs();
    setupNombreFilter();     // limpia + toast si escriben números en nombre
    setupRegisterValidation();
    setupLoginValidation();
    setupLogout();
  });
})();
