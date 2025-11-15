<?php
// modulos/usuarios.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();


require_role([1,2,3]); // paciente, cuidador o admin

$uid    = uid();
$nombre = user();

// CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

// Router
$view = $_GET['view'] ?? 'home';
$msg = $err = '';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function fetch_all($res){ $a=[]; if($res){ while($r=$res->fetch_assoc()){ $a[]=$r; } } return $a; }

// ====== ACCIONES ======

// Crear contacto de la red de apoyo
if ($view === 'nuevo' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida (CSRF).';
  } else {
    $nombreC = trim((string)($_POST['nombre'] ?? ''));
    $rolText = trim((string)($_POST['rol_texto'] ?? ''));
    $tipo    = $_POST['tipo'] ?? '';
    $tel     = trim((string)($_POST['telefono'] ?? ''));
    $wa      = trim((string)($_POST['whatsapp'] ?? ''));
    $nota    = trim((string)($_POST['nota'] ?? ''));
    $notaURL = trim((string)($_POST['nota_url'] ?? ''));

    if ($nombreC === '' || !in_array($tipo, ['medico','enfermeria','familiar','cuidador'], true)) {
      $err = 'Completa nombre y tipo válido.';
    } else {
      $stmt = $conexion->prepare(
        "INSERT INTO red_apoyo (user_id,nombre,tipo,rol_texto,telefono,whatsapp,nota,nota_url)
         VALUES (?,?,?,?,?,?,?,?)"
      );
      $stmt->bind_param('isssssss', $uid, $nombreC, $tipo, $rolText, $tel, $wa, $nota, $notaURL);
      if ($stmt->execute()) $msg = 'Contacto agregado.'; else $err = 'No se pudo guardar.';
      $stmt->close();
    }
  }
  header('Location: usuarios.php?'.($err ? 'err=1' : 'ok=1')); exit;
}

// Confirmar “toma directa” (marca un evento rápido asociado al contacto)
if ($view === 'confirmar' && isset($_GET['id'])) {
  $cid = (int)$_GET['id'];
  if (!empty($_GET['csrf']) && hash_equals($csrf, $_GET['csrf'])) {
    $stmt = $conexion->prepare("INSERT INTO confirmaciones_medicacion (user_id, contact_id) VALUES (?,?)");
    $stmt->bind_param('ii', $uid, $cid);
    $stmt->execute(); $stmt->close();
    header('Location: usuarios.php?ok=1'); exit;
  } else {
    header('Location: usuarios.php?err=1'); exit;
  }
}

// Eliminar (opcional, muestra link solo si quieres)
if ($view === 'eliminar' && isset($_GET['id'])) {
  $cid = (int)$_GET['id'];
  if (!empty($_GET['csrf']) && hash_equals($csrf, $_GET['csrf'])) {
    $stmt = $conexion->prepare("DELETE FROM red_apoyo WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $cid, $uid);
    $stmt->execute(); $stmt->close();
    header('Location: usuarios.php?ok=1'); exit;
  } else {
    header('Location: usuarios.php?err=1'); exit;
  }
}

// ====== DATOS PARA VISTA ======
$contactos = [];
if ($stmt = $conexion->prepare(
  "SELECT id, nombre, tipo, rol_texto, telefono, whatsapp, nota, nota_url
   FROM red_apoyo WHERE user_id=? ORDER BY FIELD(tipo,'medico','enfermeria','cuidador','familiar'), id ASC"
)) {
  $stmt->bind_param('i', $uid);
  $stmt->execute(); $contactos = fetch_all($stmt->get_result());
  $stmt->close();
}

// Ultimas confirmaciones (para mostrar feedback)
$ultimas = [];
if ($stmt = $conexion->prepare(
  "SELECT c.id, r.nombre, c.creado_en
     FROM confirmaciones_medicacion c
     JOIN red_apoyo r ON r.id = c.contact_id
    WHERE c.user_id=? ORDER BY c.id DESC LIMIT 5"
)) {
  $stmt->bind_param('i', $uid);
  $stmt->execute(); $ultimas = fetch_all($stmt->get_result());
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Usuarios - CuidApp</title>
  <link rel="stylesheet" href="../css/usuarios.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --bg:#eef7f7; --card:#fff; --radius:18px; }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:var(--radius);border-bottom-right-radius:var(--radius)}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px}
    .link{color:var(--primary);text-decoration:none;font-weight:600}
    .section{padding:16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:12px;margin-bottom:12px}
    .muted{color:var(--muted)}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
    input,select,textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    textarea{min-height:80px;resize:vertical}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:10px 12px;border-radius:12px;cursor:pointer}
    .pill{display:inline-block;padding:2px 8px;border:1px solid #e5e7eb;border-radius:999px;font-size:12px;background:#f8fafc}
    .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
    .actions a,.actions button{border:1px solid #e5e7eb;background:#fff;padding:8px 10px;border-radius:10px;text-decoration:none;color:#111;cursor:pointer}
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);margin-top:auto}
    footer button{background:#fff;border:1px solid #e5e7eb;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
  </style>
  <script>
    // Compartir ubicación por WhatsApp: compone un enlace con geo actual
    async function shareLocation(waNumber) {
      if (!navigator.geolocation) { alert('Tu navegador no soporta geolocalización'); return; }
      navigator.geolocation.getCurrentPosition(pos => {
        const { latitude, longitude } = pos.coords;
        const gmaps = `https://maps.google.com/?q=${latitude},${longitude}`;
        const text  = encodeURIComponent(`Hola, comparto mi ubicación: ${gmaps}`);
        const num   = (waNumber || '').replace(/\D/g,'');
        const url   = num ? `https://wa.me/${num}?text=${text}` : `https://wa.me/?text=${text}`;
        window.open(url, '_blank');
      }, _ => alert('No fue posible obtener tu ubicación'));
    }
  </script>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a class="link" href="usuarios.php">Usuarios</a>
    </div>

    <header class="header">
      <h1>Usuarios</h1>
      <p>Visualiza tu red de apoyo o equipo de salud asignado.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <section class="section">
        <a class="link" href="usuarios.php?view=nuevo">+ Agregar contacto</a>
      </section>

      <section class="section">
        <?php if (!$contactos): ?>
          <div class="card muted">Aún no tienes contactos en tu red de apoyo.</div>
        <?php else: foreach ($contactos as $c): ?>
          <div class="card">
            <div style="display:flex;justify-content:space-between;gap:10px">
              <div>
                <strong><?=h($c['nombre'])?></strong><br>
                <span class="muted">Rol: <?=h($c['rol_texto'] ?: ucfirst($c['tipo']))?></span><br>
                <?php if (!empty($c['telefono'])): ?>
                  <span class="muted">Tel: <?=h($c['telefono'])?></span><br>
                <?php endif; ?>
              </div>
              <div><span class="pill"><?=h(ucfirst($c['tipo']))?></span></div>
            </div>

            <?php if (!empty($c['nota'])): ?>
              <div class="muted" style="margin-top:6px">“<?=h($c['nota'])?>”</div>
            <?php endif; ?>

            <div class="actions">
              <?php if (!empty($c['whatsapp'])): ?>
                <a target="_blank" rel="noopener" href="https://wa.me/<?=h(preg_replace('/\D/','',$c['whatsapp']))?>?text=Hola">
                  💬 Mensaje
                </a>
              <?php elseif(!empty($c['telefono'])): ?>
                <a href="sms:<?=h(preg_replace('/\D/','',$c['telefono']))?>">💬 Mensaje</a>
              <?php endif; ?>

              <?php if (!empty($c['nota_url'])): ?>
                <a target="_blank" rel="noopener" href="<?=h($c['nota_url'])?>">📄 Ver nota médica</a>
              <?php endif; ?>

              <a href="usuarios.php?view=confirmar&id=<?=$c['id']?>&csrf=<?=$csrf?>">✅ Confirmar toma directa</a>

              <button type="button" onclick="shareLocation('<?=h($c['whatsapp'])?>')">📍 Compartir ubicación</button>

              <!-- Si quisieras eliminar:
              <a href="usuarios.php?view=eliminar&id=<?=$c['id']?>&csrf=<?=$csrf?>" onclick="return confirm('¿Eliminar contacto?')">🗑 Eliminar</a>
              -->
            </div>
          </div>
        <?php endforeach; endif; ?>
      </section>

      <?php if ($ultimas): ?>
        <section class="section">
          <div class="muted">Últimas confirmaciones:</div>
          <?php foreach ($ultimas as $u): ?>
            <div class="card">
              <div>Con <strong><?=h($u['nombre'])?></strong></div>
              <div class="muted"><?=h($u['creado_en'])?></div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <section class="section">
        <div class="muted">Estas personas forman parte de tu red de cuidado. Puedes contactarlos directamente o reportar una incidencia rápida.</div>
      </section>

    <?php elseif ($view === 'nuevo'): ?>
      <section class="section">
        <a class="link" href="usuarios.php">← Volver</a>
        <h2>Agregar contacto</h2>
        <form method="post" action="usuarios.php?view=nuevo" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <div class="row">
            <label>Nombre completo</label>
            <input name="nombre" maxlength="120" required placeholder="Ej. Dra. Laura Gómez">
          </div>
          <div class="row">
            <label>Tipo</label>
            <select name="tipo" required>
              <option value="">Selecciona un tipo</option>
              <option value="medico">Médico(a)</option>
              <option value="enfermeria">Enfermería</option>
              <option value="cuidador">Cuidador(a)</option>
              <option value="familiar">Familiar</option>
            </select>
          </div>
          <div class="row">
            <label>Rol (descripción corta)</label>
            <input name="rol_texto" maxlength="120" placeholder="Ej. Médico responsable / Contacto de emergencia">
          </div>
          <div class="row">
            <label>Teléfono</label>
            <input name="telefono" maxlength="40" placeholder="Ej. 320 555 7890">
          </div>
          <div class="row">
            <label>WhatsApp</label>
            <input name="whatsapp" maxlength="40" placeholder="Ej. +57 300 987 6543">
          </div>
          <div class="row">
            <label>Nota</label>
            <textarea name="nota" maxlength="300" placeholder="Observaciones, indicaciones, etc."></textarea>
          </div>
          <div class="row">
            <label>URL de nota médica (opcional)</label>
            <input name="nota_url" maxlength="255" placeholder="https://...">
          </div>
          <button class="btn" type="submit">Guardar</button>
        </form>
      </section>

    <?php else: ?>
      <main class="section"><div class="card">Vista no encontrada.</div></main>
    <?php endif; ?>

    <footer>
      <nav>
        <button title="Inicio" onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" alt="Inicio" width="40">
        </button>
        <button title="Salud" onclick="location.href='salud.php'"><img src="../imagenes/salud.png" alt="Salud" width="35"></button>
        <button title="Añadir" onclick="location.href='usuarios.php?view=nuevo'"><img src="../imagenes/agregar.png" alt="Nuevo" width="35"></button>
        <button title="Ajustes" onclick="location.href='../modulos/ajustes.php'"><img src="../imagenes/configuracion.png" alt="Ajustes" width="35"></button>
      </nav>
    </footer>
  </div>
</body>
</html>
