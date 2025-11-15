<?php
// dashboards/admin.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';

// Solo Admin (rol = 3)
require_role([3]);

$uid    = uid();
$nombre = user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel Administrador — CuidApp</title>

  <!-- Si tienes un CSS propio para admin, cámbialo aquí -->
  <link rel="stylesheet" href="../css/style.css">

  <style>
    :root{
      --primary:#05a4a4;
      --primary-700:#048585;
      --bg:#eaf7f7;
      --card:#ffffff;
      --muted:#6b7280;
      --text:#0f172a;
      --shadow: 0 6px 18px rgba(15,23,42,.08);
      --radius: 22px;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Ubuntu,"Helvetica Neue",Arial;background:var(--bg);color:var(--text)}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;display:flex;flex-direction:column;background:#f1f5f9;box-shadow:var(--shadow)}
    .hero{background:linear-gradient(180deg,#e6f6f6 0%,#ddeeee 100%);border-bottom-left-radius:var(--radius);border-bottom-right-radius:var(--radius);padding:28px 20px 24px;text-align:center;position:relative}
    .title{margin:10px 0 6px;font-weight:800;font-size:26px}
    .subtitle{margin:0;color:var(--muted);font-size:14px}
    .hello{font-size:13px;color:var(--muted);margin-top:6px}
    .grid{padding:18px;display:grid;gap:16px;grid-template-columns:repeat(2,minmax(0,1fr))}
    .card{background:var(--card);border:1px solid #e5e7eb;border-radius:16px;padding:16px;display:flex;flex-direction:column;align-items:center;gap:10px;text-decoration:none;color:inherit;box-shadow:0 1px 0 rgba(0,0,0,.02);transition:transform .12s,box-shadow .12s,border-color .12s}
    .card:hover{transform:translateY(-2px);box-shadow:var(--shadow);border-color:#d1d5db}
    .icon{width:64px;height:64px;border-radius:14px;display:grid;place-items:center;background:#f0fbfb;border:1px solid #e0f2f2}
    .label{font-weight:700;font-size:15px}
    .tabbar{margin-top:auto;background:linear-gradient(0deg,#dbe7e7 0%,#dfeeee 70%,rgba(223,238,238,0) 100%);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);padding:10px 18px 16px;display:flex;justify-content:center;gap:48px;position:sticky;bottom:0}
    .tabbtn{width:54px;height:54px;border-radius:50%;background:var(--card);display:grid;place-items:center;border:1px solid #e5e7eb;box-shadow:0 4px 12px rgba(0,0,0,.05)}
    .top-actions{position:absolute;right:14px;top:14px}
    .logout{appearance:none;border:0;background:#fff;color:#ef4444;font-weight:600;padding:8px 12px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);cursor:pointer}
    .logout:hover{background:#fee2e2}
    @media (min-width:480px){ .shell{border-radius:18px} }
  </style>
</head>
<body>
  <main class="shell">
    <section class="hero" aria-labelledby="heading">
      <div class="top-actions">
        <form method="post" action="<?= (strpos(__DIR__, 'dashboards') !== false) ? '../salir.php' : 'salir.php' ?>"
              onsubmit="return confirm('¿Cerrar sesión?')"
              style="margin:0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit" class="logout">Salir</button>
        </form>


      </div>

      <!-- Corazón / ECG -->
      <svg width="70" height="70" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12.1 21S4 14.6 4 9.5A4.5 4.5 0 0 1 8.5 5c1.7 0 3 .9 3.6 2.1C12.7 5.9 14 5 15.5 5 18.5 5 20 7.4 20 9.5 20 14.6 12.1 21 12.1 21z" fill="#07b6b6"/>
        <path d="M4 12h4l1.2-3 2.1 6 1.3-3H20" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>

      <h1 id="heading" class="title">Panel Administrador</h1>
      <p class="subtitle">Gestión general del sistema.</p>
      <p class="hello">Hola, <?=htmlspecialchars($nombre,ENT_QUOTES,'UTF-8')?>.</p>
    </section>

    <!-- Tarjetas -->
    <section class="grid" aria-label="Accesos rápidos">
      <!-- Usuarios -->
      <a class="card" href="../modulos/usuarios.php">
        <div class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="none">
            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" fill="#3990ff"/>
            <path d="M3.5 19a6.5 6.5 0 0 1 13 0" stroke="#3990ff" stroke-width="1.6" stroke-linecap="round"/>
            <circle cx="18" cy="8" r="2.2" fill="#3990ff" opacity=".3"/>
          </svg>
        </div>
        <div class="label">Usuarios</div>
      </a>

      <!-- Centros de Salud -->
      <a class="card" href="../modulos/centros_admin.php">
        <div class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="none">
            <rect x="3" y="8" width="18" height="10" rx="2" fill="#17a2b8" opacity=".2"/>
            <path d="M8 12h8M12 10v4" stroke="#17a2b8" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M6 18V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v11" stroke="#17a2b8" stroke-width="1.6"/>
          </svg>
        </div>
        <div class="label">Centros de Salud</div>
      </a>

      <!-- Datos del sistema -->
      <a class="card" href="../modulos/datos.php">
        <div class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="none">
            <rect x="5" y="11" width="3" height="7" rx="1" fill="#8b5cf6"/>
            <rect x="10.5" y="7" width="3" height="11" rx="1" fill="#8b5cf6" opacity=".8"/>
            <rect x="16" y="4" width="3" height="14" rx="1" fill="#8b5cf6"/>
          </svg>
        </div>
        <div class="label">Datos del sistema</div>
      </a>

      <!-- Medicamentos -->
      <a class="card" href="../modulos/medicamentos.php">
        <div class="icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="34" height="34" fill="none">
            <rect x="8" y="6" width="8" height="12" rx="2" fill="#0ea5e9" opacity=".25"/>
            <path d="M9 9h6M10 6h4" stroke="#0ea5e9" stroke-width="1.6" stroke-linecap="round"/>
            <circle cx="12" cy="13" r="2.2" fill="#0ea5e9"/>
            <path d="M12 11.6v2.8M10.6 13h2.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <div class="label">Medicamentos</div>
      </a>
    </section>

    <!-- Barra inferior -->
    <nav class="tabbar" aria-label="Navegación inferior">
      <div class="tabbtn" title="Inicio">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" aria-hidden="true">
          <path d="M12.1 21S4 14.6 4 9.5A4.5 4.5 0 0 1 8.5 5c1.7 0 3 .9 3.6 2.1C12.7 5.9 14 5 15.5 5 18.5 5 20 7.4 20 9.5 20 14.6 12.1 21 12.1 21z" fill="#07b6b6"/>
        </svg>
      </div>
      <a class="tabbtn" href="../modulos/ajustes.php" title="Ajustes">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" aria-hidden="true">
          <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="#0f172a" stroke-width="1.6"/>
          <path d="M19 12a7 7 0 0 0-.1-1l1.9-1.4-1.9-3.2-2.2.6a7 7 0 0 0-1.7-1l-.3-2.2h-3.7l-.3 2.2a7 7 0 0 0-1.7 1l-2.2-.6-1.9 3.2 1.9 1.4A7 7 0 0 0 5 12c0 .3 0 .7.1 1l-1.9 1.4 1.9 3.2 2.2-.6a7 7 0 0 0 1.7 1l.3 2.2h3.7l.3-2.2a7 7 0 0 0 1.7-1l2.2.6 1.9-3.2-1.9-1.4c.1-.3.1-.6.1-1Z" stroke="#0f172a" stroke-width="1.2" opacity=".6"/>
        </svg>
      </a>
    </nav>
  </main>
</body>
</html>