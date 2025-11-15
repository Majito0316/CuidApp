<?php
// dashboards/paciente.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';

// Solo pacientes (rol = 1)
require_role([1]);

$uid    = uid();
$nombre = user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CuidApp — Paciente</title>
  <link rel="stylesheet" href="../css/dashboardPaciente.css">
</head>
<body>
  <!-- ENCABEZADO -->
  <header>
    <div class="logo">
      <img src="../imagenes/logo.png" alt="CuidApp" class="fondo-logo" width="80">
      <h1>CuidApp</h1>
    </div>
    <div class="icons">
      <button type="button" title="Ayuda" onclick="location.href='../modulos/ayuda.php'">
        <img src="../imagenes/ayuda.png" alt="Ayuda" width="40">
      </button>
      <button type="button" title="Perfil" onclick="location.href='../perfil.php'">
        <img src="../imagenes/perfil.png" alt="Perfil" width="25">
      </button>
    </div>
  </header>
          <form method="post" action="<?= (strpos(__DIR__, 'dashboards') !== false) ? '../salir.php' : 'salir.php' ?>"
              onsubmit="return confirm('¿Cerrar sesión?')"
              style="margin:0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="logout">Salir</button>
        </form>

  <!-- CUERPO PRINCIPAL -->
  <main>
    <div class="grid">
      <a class="card" href="../modulos/medicamentos.php" aria-label="Ir a Medicamentos">
        <img src="../imagenes/medicamentos.png" width="80" alt="Medicamentos">
        <p>Medicamentos</p>
      </a>

      <!-- Si este ítem no aplica al paciente puedes ocultarlo o redirigir a una vista informativa -->
      <a class="card" href="../modulos/usuarios.php" aria-label="Ir a Usuarios">
        <img src="../imagenes/usuarios.png" width="80" alt="Usuarios">
        <p>Usuarios</p>
      </a>

      <a class="card" href="../modulos/salud.php" aria-label="Ir a Salud">
        <img src="../imagenes/salud.png" width="80" alt="Salud">
        <p>Salud</p>
      </a>

      <a class="card" href="../modulos/ubicacion.php" aria-label="Ir a Ubicación">
        <img src="../imagenes/ubicacion.png" width="80" alt="Ubicación">
        <p>Ubicación</p>
      </a>

      <a class="card" href="../modulos/ayuda.php" aria-label="Ir a Ayuda">
        <img src="../imagenes/ayudaE.png" width="80" alt="Ayuda">
        <p>Ayuda</p>
      </a>

      <a class="card" href="../modulos/soporte.php" aria-label="Ir a Soporte">
        <img src="../imagenes/soporte.png" width="80" alt="Soporte">
        <p>Soporte</p>
      </a>
    </div>
  </main>

  <!-- BARRA INFERIOR -->
  <footer>
    <nav>
      <button type="button" title="Historial" onclick="location.href='../modulos/medicamentos.php?view=historial'">
        <img src="../imagenes/historial.png" width="30" alt="Historial">
      </button>
      <button type="button" title="Inicio" onclick="location.href='dashboardPaciente.php'">
        <img src="../imagenes/logo.png" width="40" alt="Inicio">
      </button>
      <button type="button" title="Nuevo (Salud)" onclick="location.href='../modulos/salud.php?view=nuevo'">
        <img src="../imagenes/agregar.png" width="35" alt="Agregar">
      </button>
      <button type="button" title="Ajustes" onclick="location.href='../modulos/ajustes.php'">
        <img src="../imagenes/configuracion.png" width="35" alt="Configuración">
      </button>
    </nav>
  </footer>
</body>
</html>