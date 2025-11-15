<?php
require_once __DIR__ . '/includes/session.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CuidApp - Iniciar Sesión / Registrarse</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .auth-tabs button.active { font-weight: 700; }
    input.invalid { border-color: #c0392b; }
    small.hint { display:block; margin-top:.25rem; opacity:.8; }
    .field-error { color:#b91c1c; font-size:.9rem; margin:.25rem 0 .5rem; }
    .shake { animation: shake .25s linear 1; }
    @keyframes shake { 0%{transform:translateX(0)} 25%{transform:translateX(-4px)} 50%{transform:translateX(4px)} 75%{transform:translateX(-3px)} 100%{transform:translateX(0)} }
  </style>
</head>
<body>
  <div class="auth-container">
    <div class="auth-tabs">
      <button id="login-tab" class="active" type="button">Iniciar Sesión</button>
      <button id="register-tab" type="button">Registrarse</button>
    </div>

    <div class="auth-logo">
      <img src="imagenes/logo.png" alt="CuidApp" width="80">
    </div>

    <!-- Formulario de Iniciar Sesión -->
    <form id="login-form" class="auth-form active" method="POST" action="actions/login_procesar.php" novalidate>
      <label>Correo Electrónico</label>
      <input
        type="email"
        name="correo"
        placeholder="Ingresa tu correo"
        required
        inputmode="email"
        autocomplete="email"
      >

      <label>Contraseña</label>
      <input
        type="password"
        name="contraseña"
        placeholder="********"
        required
        autocomplete="current-password"
      >

      <button type="submit" class="btn">INGRESAR</button>
    </form>

    <!-- Formulario de Registro -->
    <form id="register-form" class="auth-form" method="POST" action="actions/registro_procesar.php" novalidate>
      <label>Nombre Completo</label>
      <input
        type="text"
        name="nombre"
        placeholder="Ingresa tu nombre completo"
        required
        minlength="2"
        maxlength="80"
        pattern="^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$"
        title="Usa solo letras; puedes incluir espacios, apóstrofos o guiones."
        autocomplete="name"
        spellcheck="false"
      />
      <div id="err-nombre" class="field-error" aria-live="polite"></div>

      <label>Correo Electrónico</label>
      <input
        type="email"
        name="correo"
        placeholder="Ingresa tu correo electrónico"
        required
        inputmode="email"
        autocomplete="email"
        maxlength="120"
      />

      <label>Rol</label>
      <select name="rol" required>
        <option value="">Selecciona tu rol</option>
        <option value="paciente">Paciente</option>
        <option value="cuidador">Cuidador</option>
        <option value="admin">Admin</option>
      </select>

      <label>Contraseña</label>
      <input
        type="password"
        name="contraseña"
        placeholder="********"
        required
        autocomplete="new-password"
        minlength="8"
        maxlength="64"
        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s])(?!.*\s).{8,64}$"
      />
      <small class="hint">Debe tener 8–64 caracteres, mayúscula, minúscula, número y símbolo; sin espacios.</small>

      <label>Confirmar Contraseña</label>
      <input
        type="password"
        name="confirmar"
        placeholder="********"
        required
        autocomplete="new-password"
        oninput="this.value=this.value.replace(/\s/g,'')"
      />

      <button type="submit" class="btn">REGISTRARSE</button>
    </form>
  </div>

  <!-- Validaciones y UX -->
  <script>
    // --- Tabs ---
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    loginTab.addEventListener('click', () => {
      loginTab.classList.add('active');
      registerTab.classList.remove('active');
      loginForm.classList.add('active');
      registerForm.classList.remove('active');
    });
    registerTab.addEventListener('click', () => {
      registerTab.classList.add('active');
      loginTab.classList.remove('active');
      registerForm.classList.add('active');
      loginForm.classList.remove('active');
    });

    // ---------- VALIDACIÓN LIGERA ----------
    const REGEX = {
      nombre: /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/,
      email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
      pass: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s])(?!.*\s).{8,64}$/
    };

    function cleanSpaces(input) {
      input.value = input.value.replace(/\s+/g, ' ').trim();
    }
    function invalidate(input, msg) {
      input.classList.add('invalid');
      input.setCustomValidity(msg);
    }
    function validateOK(input) {
      input.classList.remove('invalid');
      input.setCustomValidity('');
    }

    // ----- LOGIN -----
    loginForm.addEventListener('submit', (e) => {
      const correo = loginForm.querySelector('input[name="correo"]');
      const pass   = loginForm.querySelector('input[name="contraseña"]');

      cleanSpaces(correo);
      let ok = true;

      if (!REGEX.email.test(correo.value)) { invalidate(correo, 'Ingresa un correo válido.'); ok = false; } else { validateOK(correo); }
      if (!pass.value) { invalidate(pass, 'Ingresa tu contraseña.'); ok = false; } else { validateOK(pass); }

      if (!ok) { e.preventDefault(); (correo.reportValidity(), pass.reportValidity()); }
    });

    // ----- REGISTRO -----
    const rNombre = registerForm.querySelector('input[name="nombre"]');
    const rCorreo = registerForm.querySelector('input[name="correo"]');
    const rRol    = registerForm.querySelector('select[name="rol"]');
    const rPass   = registerForm.querySelector('input[name="contraseña"]');
    const rConf   = registerForm.querySelector('input[name="confirmar"]');

    function vNombre(){
      cleanSpaces(rNombre);
      if (!REGEX.nombre.test(rNombre.value) || rNombre.value.length < 2) { invalidate(rNombre, 'Usa solo letras (puedes incluir espacios, apóstrofos o guiones).'); return false; }
      validateOK(rNombre); return true;
    }
    function vCorreo(){
      cleanSpaces(rCorreo);
      rCorreo.value = rCorreo.value.toLowerCase();
      if (!REGEX.email.test(rCorreo.value) || rCorreo.value.length > 120) { invalidate(rCorreo, 'Correo inválido.'); return false; }
      validateOK(rCorreo); return true;
    }
    function vRol(){
      if (!['paciente','cuidador','admin'].includes(rRol.value)) { invalidate(rRol, 'Selecciona un rol.'); return false; }
      validateOK(rRol); return true;
    }
    function vPass(){
      if (!REGEX.pass.test(rPass.value)) { invalidate(rPass, '8–64 caracteres, mayúscula, minúscula, número y símbolo; sin espacios.'); return false; }
      validateOK(rPass); return true;
    }
    function vConf(){
      if (rConf.value !== rPass.value || !rConf.value) { invalidate(rConf, 'Las contraseñas no coinciden.'); return false; }
      validateOK(rConf); return true;
    }

    [rNombre, rCorreo, rPass, rConf].forEach(i=>{
      i.addEventListener('input', ()=>{
        if (i===rNombre) vNombre();
        if (i===rCorreo) vCorreo();
        if (i===rPass)   { vPass(); vConf(); }
        if (i===rConf)   vConf();
      });
      i.addEventListener('blur', ()=>{
        if (i===rNombre) vNombre();
        if (i===rCorreo) vCorreo();
        if (i===rPass)   vPass();
        if (i===rConf)   vConf();
      });
    });
    rRol.addEventListener('change', vRol);

    registerForm.addEventListener('submit', (e) => {
      let ok = vNombre() & vCorreo() & vRol() & vPass() & vConf();
      if (!ok) { e.preventDefault(); registerForm.reportValidity(); }
    });
  </script>

  <!-- Nombre: bloqueo de números + alerta en rojo y limpieza -->
  <script>
    const nombreInput = document.querySelector('#register-form input[name="nombre"]');
    const nombreErr   = document.getElementById('err-nombre');

    function showNombreError(msg, clearField = false) {
      if (!nombreInput) return;
      if (clearField) nombreInput.value = '';
      nombreInput.classList.add('invalid','shake');
      if (nombreErr) nombreErr.textContent = msg;
      setTimeout(()=> nombreInput.classList.remove('shake'), 300);
    }
    function clearNombreError() {
      if (!nombreInput) return;
      nombreInput.classList.remove('invalid');
      if (nombreErr) nombreErr.textContent = '';
    }

    if (nombreInput) {
      // Bloquea números al teclear
      nombreInput.addEventListener('keydown', (e) => {
        if (/\d/.test(e.key)) {
          e.preventDefault();
          showNombreError('Este campo solo admite letras (se bloquean números).');
        }
      });
      // Evita pegar con números
      nombreInput.addEventListener('paste', (e) => {
        const txt = (e.clipboardData || window.clipboardData).getData('text');
        if (/\d/.test(txt)) {
          e.preventDefault();
          showNombreError('No pegues números en el nombre. Se limpió el campo.', true);
        }
      });
      // Sanitiza en vivo (tildes/ñ, espacios, ' y - permitidos)
      nombreInput.addEventListener('input', () => {
        const cleaned = nombreInput.value
          .replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ '\-]/g,'')
          .replace(/\s+/g,' ')
          .trimStart();
        if (nombreInput.value !== cleaned) {
          nombreInput.value = cleaned;
          showNombreError('Se han removido caracteres no permitidos.');
        } else {
          const ok = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/.test(cleaned) || cleaned === '';
          if (ok) clearNombreError();
        }
      });
    }
  </script>

  <!-- Si quieres centralizar validaciones y toasts, incluye tu JS global -->
  <!-- <script src="js/app.js" defer></script> -->
</body>
</html>
