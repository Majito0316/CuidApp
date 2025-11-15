<?php
// modulos/medicamentos.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();

// Solo usuarios logueados (cualquier rol)
require_role([1,2,3]);

$uid    = uid();
$nombre = user();

// CSRF (este módulo hace POST)
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Router
$view = $_GET['view'] ?? 'home';
$msg = $err = '';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD'] === 'POST'; }
function fetch_all_assoc($res){ $out=[]; if($res){ while($r=$res->fetch_assoc()){ $out[]=$r; } } return $out; }

// ---------- ACCIONES ----------

// Crear pedido (ficha virtual)
if ($view === 'ficha' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida (CSRF).';
  } else {
    $medicamento = trim((string)($_POST['medicamento'] ?? ''));
    $cantidad    = (int)($_POST['cantidad'] ?? 0);
    $observ      = trim((string)($_POST['observaciones'] ?? ''));
    if ($medicamento === '' || $cantidad < 1 || $cantidad > 999) {
      $err = 'Indica el nombre del medicamento y una cantidad válida.';
    } else {
      $desc = $medicamento . " × " . $cantidad . ($observ ? " — " . $observ : "");
      if ($stmt = $conexion->prepare("INSERT INTO pedidos (user_id, descripcion, estado) VALUES (?,?, 'pendiente')")) {
        $stmt->bind_param('is', $uid, $desc);
        if ($stmt->execute()) $msg = 'Solicitud creada. Puedes verla en "Estado del Pedido".';
        else $err = 'No se pudo guardar la solicitud.';
        $stmt->close();
      } else { $err = 'Error de servidor.'; }
    }
  }
  header('Location: medicamentos.php?view=estado&'.($err ? 'err=1' : 'ok=1')); exit;
}

// Cancelar pedido (si es del usuario y está pendiente/en_proceso)
if ($view === 'estado' && isset($_GET['cancel'])) {
  $pid = (int)$_GET['cancel'];
  if ($stmt = $conexion->prepare("UPDATE pedidos SET estado='cancelado' WHERE id=? AND user_id=? AND estado IN ('pendiente','en_proceso')")) {
    $stmt->bind_param('ii', $pid, $uid);
    $stmt->execute();
    $stmt->close();
  }
  header('Location: medicamentos.php?view=estado&ok=1'); exit;
}

// Subir fórmula digital
if ($view === 'formula' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida (CSRF).';
  } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $err = 'Adjunta un archivo válido.';
  } else {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $f = $_FILES['archivo'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $okExt  = in_array($ext, ['pdf','png','jpg','jpeg'], true);
    $okSize = ($f['size'] <= 5 * 1024 * 1024);
    if ($titulo === '' || !$okExt || !$okSize) {
      $err = 'Título requerido y archivo PDF/PNG/JPG máx. 5MB.';
    } else {
      $dir = __DIR__ . '/../uploads/formulas';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $safe  = preg_replace('/[^a-z0-9\-_\.]/i', '_', pathinfo($f['name'], PATHINFO_FILENAME));
      $final = $safe . '_' . time() . '.' . $ext;
      $dest  = $dir . '/' . $final;
      if (move_uploaded_file($f['tmp_name'], $dest)) {
        if ($stmt = $conexion->prepare("INSERT INTO formulas (user_id,titulo,nombre_archivo) VALUES (?,?,?)")) {
          $stmt->bind_param('iss', $uid, $titulo, $final);
          if ($stmt->execute()) $msg = 'Fórmula cargada correctamente.'; else $err = 'No se pudo guardar en BD.';
          $stmt->close();
        } else { $err = 'Error de servidor.'; }
      } else { $err = 'Error moviendo el archivo.'; }
    }
  }
  header('Location: medicamentos.php?view=formula&'.($err ? 'err=1' : 'ok=1')); exit;
}

// ---------- CARGAS PARA VISTAS ----------
$activos = $historial = $formulas = [];

// pedidos activos
if ($stmt = $conexion->prepare("SELECT id, descripcion, estado, creado_en FROM pedidos WHERE user_id=? AND estado IN ('pendiente','en_proceso') ORDER BY id DESC")) {
  $stmt->bind_param('i', $uid); $stmt->execute();
  $activos = fetch_all_assoc($stmt->get_result()); $stmt->close();
}
// historial
if ($stmt = $conexion->prepare("SELECT id, descripcion, estado, creado_en FROM pedidos WHERE user_id=? ORDER BY id DESC")) {
  $stmt->bind_param('i', $uid); $stmt->execute();
  $historial = fetch_all_assoc($stmt->get_result()); $stmt->close();
}
// formulas
if ($stmt = $conexion->prepare("SELECT id, titulo, nombre_archivo, creado_en FROM formulas WHERE user_id=? ORDER BY id DESC")) {
  $stmt->bind_param('i', $uid); $stmt->execute();
  $formulas = fetch_all_assoc($stmt->get_result()); $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Medicamentos - CuidApp</title>
  <link rel="stylesheet" href="../css/medicamentos.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --bg:#eef7f7; --card:#fff; --radius:18px; }
    *{box-sizing:border-box}
    body{margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial; background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:var(--radius);border-bottom-right-radius:var(--radius)}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .options{padding:16px}
    .option{display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:14px;background:var(--card);text-decoration:none;color:inherit;border:1px solid #e5e7eb;margin-bottom:12px}
    .option:hover{box-shadow:0 6px 18px rgba(0,0,0,.06)}
    .icon-text{display:flex;align-items:center;gap:12px}
    .icon{width:52px;height:52px;object-fit:contain}
    .arrow{font-size:22px;color:#94a3b8}
    .section{padding:16px}
    .list{margin-top:12px}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px;margin-bottom:8px}
    .muted{color:var(--muted)}
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    input[type="text"], input[type="number"], textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    textarea{min-height:90px;resize:vertical}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:12px;border-radius:12px;cursor:pointer}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);margin-top:auto}
    footer button{background:#fff;border:1px solid #e5e7eb;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px}
    .link{color:var(--primary);text-decoration:none;font-weight:600}
    .badg{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px}
    .b-pend{background:#fffbeb;border:1px solid #fde68a;color:#92400e}
    .b-proc{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af}
    .b-ok{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
    .b-canc{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    .actions{display:flex;gap:8px;margin-top:8px}
    .btn-outline{background:#fff;color:#0f172a;border:1px solid #e5e7eb}
    .btn-danger{background:#ef4444}
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a class="link" href="medicamentos.php">Medicamentos</a>
    </div>

    <header class="header">
      <h1>Medicamentos</h1>
      <p>Gestiona tus solicitudes y pedidos en un solo lugar</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <main class="options">
        <a href="medicamentos.php?view=ficha" class="option">
          <div class="icon-text">
            <img src="../imagenes/fichavirtual.png" alt="Ficha Virtual" class="icon">
            <div>
              <h2>Ficha Virtual</h2>
              <p class="muted">Crear nueva solicitud de medicamentos.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="medicamentos.php?view=estado" class="option">
          <div class="icon-text">
            <img src="../imagenes/pedido.png" alt="Estado del Pedido" class="icon">
            <div>
              <h2>Estado del Pedido</h2>
              <p class="muted">Ver progreso de entrega y hora estimada.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="medicamentos.php?view=formula" class="option">
          <div class="icon-text">
            <img src="../imagenes/formula.png" alt="Fórmula Digital" class="icon">
            <div>
              <h2>Fórmula Digital</h2>
              <p class="muted">Subir/consultar fórmulas médicas.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="medicamentos.php?view=historial" class="option">
          <div class="icon-text">
            <img src="../imagenes/historialp.png" alt="Historial de Solicitudes" class="icon">
            <div>
              <h2>Historial de Solicitudes</h2>
              <p class="muted">Consultar pedidos pasados.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>
      </main>

    <?php elseif ($view === 'ficha'): ?>
      <section class="section">
        <a class="link" href="medicamentos.php">← Volver</a>
        <h2>Nueva solicitud</h2>
        <form method="post" action="medicamentos.php?view=ficha" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <div class="row">
            <label>Medicamento</label>
            <input type="text" name="medicamento" required maxlength="120" placeholder="Ej. Acetaminofén 500 mg">
          </div>
          <div class="row">
            <label>Cantidad</label>
            <input type="number" name="cantidad" required min="1" max="999" step="1" value="1">
          </div>
          <div class="row">
            <label>Observaciones (opcional)</label>
            <textarea name="observaciones" maxlength="200" placeholder="Presentación, instrucciones, alergias, etc."></textarea>
          </div>
          <button class="btn" type="submit">Crear solicitud</button>
        </form>
      </section>

    <?php elseif ($view === 'estado'): ?>
      <section class="section">
        <a class="link" href="medicamentos.php">← Volver</a>
        <h2>Estado del Pedido</h2>
        <div class="list">
          <?php if (!$activos): ?>
            <div class="item muted">No tienes pedidos activos.</div>
          <?php else: foreach ($activos as $p): ?>
            <div class="item">
              <div><strong>#<?= (int)$p['id'] ?></strong> — <?=h($p['descripcion'])?></div>
              <div class="muted"><?=h($p['creado_en'])?></div>
              <?php
                $estado = $p['estado'];
                $badgeClass = $estado==='pendiente' ? 'b-pend' : ($estado==='en_proceso' ? 'b-proc' : ($estado==='entregado'?'b-ok':'b-canc'));
              ?>
              <div style="margin-top:6px"><span class="badg <?=$badgeClass?>"><?=h(ucwords(str_replace('_',' ', $estado)))?></span></div>
              <div class="actions">
                <?php if (in_array($p['estado'], ['pendiente','en_proceso'], true)): ?>
                  <a class="btn btn-outline" href="medicamentos.php?view=historial">Ver historial</a>
                  <a class="btn btn-danger" href="medicamentos.php?view=estado&cancel=<?= (int)$p['id'] ?>" onclick="return confirm('¿Cancelar este pedido?')">Cancelar</a>
                <?php else: ?>
                  <a class="btn btn-outline" href="medicamentos.php?view=historial">Ver historial</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'formula'): ?>
      <section class="section">
        <a class="link" href="medicamentos.php">← Volver</a>
        <h2>Fórmula Digital</h2>
        <form method="post" action="medicamentos.php?view=formula" enctype="multipart/form-data" class="section" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <div class="row">
            <label>Título</label>
            <input type="text" name="titulo" required maxlength="120" placeholder="Ej. Fórmula Dr. Pérez 2025-03">
          </div>
          <div class="row">
            <label>Archivo (PDF/PNG/JPG máx. 5MB)</label>
            <input type="file" name="archivo" accept=".pdf,.png,.jpg,.jpeg" required>
          </div>
          <button class="btn" type="submit">Subir fórmula</button>
        </form>

        <h3>Mis fórmulas</h3>
        <div class="list">
          <?php if (!$formulas): ?>
            <div class="item muted">Aún no has subido fórmulas.</div>
          <?php else: foreach ($formulas as $f): ?>
            <div class="item">
              <strong><?=h($f['titulo'])?></strong><br>
              <span class="muted"><?=h($f['creado_en'])?></span><br>
              <a class="link" href="<?= '../uploads/formulas/'.rawurlencode($f['nombre_archivo']) ?>" target="_blank">Abrir/descargar</a>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'historial'): ?>
      <section class="section">
        <a class="link" href="medicamentos.php">← Volver</a>
        <h2>Historial de Solicitudes</h2>
        <div class="list">
          <?php if (!$historial): ?>
            <div class="item muted">No hay pedidos aún.</div>
          <?php else: foreach ($historial as $p): ?>
            <div class="item">
              <div><strong>#<?= (int)$p['id'] ?></strong> — <?=h($p['descripcion'])?></div>
              <div class="muted"><?=h($p['creado_en'])?></div>
              <?php
                $estado = $p['estado'];
                $badgeClass = $estado==='pendiente' ? 'b-pend' : ($estado==='en_proceso' ? 'b-proc' : ($estado==='entregado'?'b-ok':'b-canc'));
              ?>
              <div style="margin-top:6px"><span class="badg <?=$badgeClass?>"><?=h(ucwords(str_replace('_',' ', $estado)))?></span></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php else: ?>
      <main class="options"><div class="item">Vista no encontrada.</div></main>
    <?php endif; ?>

    <!-- Barra inferior -->
    <footer>
      <nav>
        <button title="Historial" onclick="location.href='medicamentos.php?view=historial'"><img src="../imagenes/historial.png" width="30" alt="Historial"></button>
        <button onclick="location.href='<?= htmlspecialchars(dash_path(), ENT_QUOTES, 'UTF-8') ?>'"><img src="/cuidapp/imagenes/logo.png" alt="Inicio" width="35"></button>

        <button title="Nueva solicitud" onclick="location.href='medicamentos.php?view=ficha'"><img src="../imagenes/agregar.png" width="35" alt="Agregar"></button>
        <button title="Ajustes" onclick="location.href='../modulos/ajustes.php'"><img src="../imagenes/configuracion.png" width="35" alt="Configuración"></button>
      </nav>
    </footer>
  </div>

  <!-- JS global (opcional) -->
  <script src="../js/app.js" defer></script>
</body>
</html>

