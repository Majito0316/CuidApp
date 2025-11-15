<?php
// modulos/ubicacion.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();

// solo usuarios logueados (cualquier rol)
require_role([1,2,3]);

$uid    = uid();
$nombre = user();

// CSRF (este archivo hace POST para subir fórmulas)
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Router simple
$view = $_GET['view'] ?? 'home';
$msg = $err = '';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD'] === 'POST'; }

// ---------- ACCIÓN: subir fórmula digital ----------
if ($view === 'formula' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida (CSRF).';
  } elseif (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $err = 'Adjunta un archivo válido.';
  } else {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $f      = $_FILES['archivo'];
    $ext    = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $okExt  = in_array($ext, ['pdf','png','jpg','jpeg'], true);
    $okSize = ($f['size'] <= 5 * 1024 * 1024);

    if ($titulo === '' || !$okExt || !$okSize) {
      $err = 'Título requerido y archivo PDF/PNG/JPG máx. 5MB.';
    } else {
      // Guardar en /uploads/formulas (subir un nivel desde /modulos)
      $dir = __DIR__ . '/../uploads/formulas';
      if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
      $safe  = preg_replace('/[^a-z0-9\-_\.]/i', '_', pathinfo($f['name'], PATHINFO_FILENAME));
      $final = $safe . '_' . time() . '.' . $ext;
      $dest  = $dir . '/' . $final;

      if (move_uploaded_file($f['tmp_name'], $dest)) {
        $stmt = $conexion->prepare("INSERT INTO formulas (user_id,titulo,nombre_archivo) VALUES (?,?,?)");
        $stmt->bind_param('iss', $uid, $titulo, $final);
        if ($stmt->execute()) { $msg = 'Fórmula cargada correctamente.'; }
        else                  { $err = 'No se pudo guardar en BD.'; }
        $stmt->close();
      } else {
        $err = 'Error moviendo el archivo.';
      }
    }
  }
  // Evitar reenvío del formulario
  header('Location: ubicacion.php?view=formula&' . ($err ? 'err=1' : 'ok=1'));
  exit;
}

// ---------- CARGAS DE DATOS (requiere tablas creadas) ----------
function fetch_all_assoc($res){ $out=[]; if($res){ while($r=$res->fetch_assoc()){ $out[]=$r; } } return $out; }

$farmacias = $centros = $formulas = $pedidos = [];

// farmacias
if ($res = $conexion->query("SELECT id,nombre,direccion,lat,lng,horario FROM farmacias ORDER BY nombre")) {
  $farmacias = fetch_all_assoc($res);
}
// centros de salud
if ($res = $conexion->query("SELECT id,nombre,direccion,lat,lng,tipo FROM centros_salud ORDER BY nombre")) {
  $centros = fetch_all_assoc($res);
}
// fórmulas del usuario
if ($stmt = $conexion->prepare("SELECT id,titulo,nombre_archivo,creado_en FROM formulas WHERE user_id=? ORDER BY id DESC")) {
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $formulas = fetch_all_assoc($stmt->get_result());
  $stmt->close();
}
// pedidos (historial)
if ($stmt = $conexion->prepare("SELECT id,descripcion,estado,creado_en FROM pedidos WHERE user_id=? ORDER BY id DESC")) {
  $stmt->bind_param('i', $uid);
  $stmt->execute();
  $pedidos = fetch_all_assoc($stmt->get_result());
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ubicación - CuidApp</title>

  <!-- CSS del módulo -->
  <link rel="stylesheet" href="../css/ubicacion.css">

  <!-- Leaflet (mapas sin API key) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .section{padding:16px}
    .map{height:360px;border-radius:14px;border:1px solid #e5e7eb;overflow:hidden}
    .list{margin-top:12px}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px;margin-bottom:8px}
    .muted{color:var(--muted)}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:10px 12px;border-radius:12px;cursor:pointer}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);margin-top:auto}
    footer button{background:#fff;border:1px solid #e5e7eb;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px}
    .link{color:var(--primary);text-decoration:none;font-weight:600}
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a class="link" href="ubicacion.php">Ubicación</a>
    </div>

    <header class="header">
      <h1>Ubicación</h1>
      <p>Encuentra farmacias, centros de salud y sigue tus pedidos en tiempo real</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <main class="options">
        <a href="ubicacion.php?view=farmacias" class="option">
          <div class="icon-text">
            <img src="../imagenes/farmacia.png" alt="Farmacias Cercanas" class="icon">
            <div>
              <h2>Farmacias Cercanas</h2>
              <p class="muted">Mapa interactivo con farmacias y disponibilidad de medicamentos.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="ubicacion.php?view=centros" class="option">
          <div class="icon-text">
            <img src="../imagenes/centros.png" alt="Centros de Salud" class="icon">
            <div>
              <h2>Centros de Salud</h2>
              <p class="muted">Ubicación de hospitales, clínicas y servicios de urgencias.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="ubicacion.php?view=formula" class="option">
          <div class="icon-text">
            <img src="../imagenes/formula.png" alt="Fórmula Digital" class="icon">
            <div>
              <h2>Fórmula Digital</h2>
              <p class="muted">Subir/consultar fórmulas médicas.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="ubicacion.php?view=historial" class="option">
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

    <?php elseif ($view === 'farmacias'): ?>
      <section class="section">
        <a class="link" href="ubicacion.php">← Volver</a>
        <h2>Farmacias Cercanas</h2>
        <div id="mapFarm" class="map"></div>
        <div class="list">
          <?php if (!$farmacias): ?>
            <div class="item muted">No hay farmacias cargadas. Inserta datos en la tabla <code>farmacias</code>.</div>
          <?php else: foreach ($farmacias as $f): ?>
            <div class="item">
              <strong><?=h($f['nombre'])?></strong><br>
              <span class="muted"><?=h($f['direccion'])?></span><br>
              <?php if(!empty($f['horario'])): ?><span class="muted">Horario: <?=h($f['horario'])?></span><?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

      <script>
        (function(){
          const map = L.map('mapFarm');
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'© OpenStreetMap' }).addTo(map);
          const places = <?=json_encode($farmacias, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;

          function setUserPos(lat, lng){ map.setView([lat, lng], 14); L.circle([lat,lng], {radius:400, color:'#05a4a4'}).addTo(map).bindPopup('Estás aquí'); }
          if (navigator.geolocation) { navigator.geolocation.getCurrentPosition(p=>setUserPos(p.coords.latitude,p.coords.longitude), ()=>setUserPos(4.711,-74.0721)); }
          else setUserPos(4.711,-74.0721);

          places.forEach(p=> L.marker([p.lat, p.lng]).addTo(map).bindPopup(`<strong>${p.nombre}</strong><br>${p.direccion}${p.horario?'<br><em>'+p.horario+'</em>':''}`));
        })();
      </script>

    <?php elseif ($view === 'centros'): ?>
      <section class="section">
        <a class="link" href="ubicacion.php">← Volver</a>
        <h2>Centros de Salud</h2>
        <div id="mapCentros" class="map"></div>
        <div class="list">
          <?php if (!$centros): ?>
            <div class="item muted">No hay centros cargados. Inserta datos en <code>centros_salud</code>.</div>
          <?php else: foreach ($centros as $c): ?>
            <div class="item">
              <strong><?=h($c['nombre'])?></strong> · <span class="muted"><?=h($c['tipo']?:'')?></span><br>
              <span class="muted"><?=h($c['direccion'])?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>
      <script>
        (function(){
          const map = L.map('mapCentros');
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'© OpenStreetMap' }).addTo(map);
          const places = <?=json_encode($centros, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
          function setView(lat,lng){ map.setView([lat,lng], 13); }
          if (navigator.geolocation) { navigator.geolocation.getCurrentPosition(p=> setView(p.coords.latitude,p.coords.longitude), ()=> setView(4.711,-74.0721)); }
          else setView(4.711,-74.0721);
          places.forEach(p=> L.marker([p.lat, p.lng]).addTo(map).bindPopup(`<strong>${p.nombre}</strong><br>${p.direccion}${p.tipo?'<br><em>'+p.tipo+'</em>':''}`));
        })();
      </script>

    <?php elseif ($view === 'formula'): ?>
      <section class="section">
        <a class="link" href="ubicacion.php">← Volver</a>
        <h2>Fórmula Digital</h2>
        <form method="post" action="ubicacion.php?view=formula" enctype="multipart/form-data" class="section" novalidate>
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
        <a class="link" href="ubicacion.php">← Volver</a>
        <h2>Historial de Solicitudes</h2>
        <div class="list">
          <?php if (!$pedidos): ?>
            <div class="item muted">No hay pedidos aún.</div>
          <?php else: foreach ($pedidos as $p): ?>
            <div class="item">
              <div><strong><?=h($p['descripcion'])?></strong></div>
              <div class="muted">Estado: <?=h($p['estado'])?> · <?=h($p['creado_en'])?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php else: ?>
      <main class="options"><div class="item">Vista no encontrada.</div></main>
    <?php endif; ?>

    <footer>
      <nav>
        <button title="Historial" onclick="location.href='ubicacion.php?view=historial'"><img src="../imagenes/historial.png" alt="Historial" width="30"></button>
        <button title="Inicio" onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" alt="Inicio" width="40">
        </button>
        <button title="Fórmula" onclick="location.href='ubicacion.php?view=formula'"><img src="../imagenes/agregar.png" alt="Agregar" width="35"></button>
        <button title="Ajustes" onclick="location.href='../modulos/ajustes.php'"><img src="../imagenes/configuracion.png" alt="Configuración" width="35"></button>
      </nav>
    </footer>
  </div>

  <!-- JS global (opcional) -->
  <script src="../js/app.js" defer></script>
</body>
</html>

