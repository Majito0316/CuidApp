<?php
// modulos/salud.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();

// Solo usuarios logueados (cualquier rol)
require_role([1,2,3]);

$userId = uid();
$nombre = user();

// CSRF (este módulo hace POST)
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

// Router
$view = $_GET['view'] ?? 'home';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=''){ return $_POST[$k] ?? $d; }
function is_post(){ return $_SERVER['REQUEST_METHOD'] === 'POST'; }

// --------- Acciones POST ---------
$msg = '';
$err = '';

if ($view === 'nuevo' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida. Intenta de nuevo.';
  } else {
    $tipo       = trim((string)post('tipo'));
    $intensidad = (int)post('intensidad', 0);
    $notas      = trim((string)post('notas'));

    if ($tipo === '' || $intensidad < 1 || $intensidad > 10) {
      $err = 'Revisa los campos: tipo y una intensidad entre 1 y 10.';
    } else {
      $sql = "INSERT INTO sintomas (user_id, tipo, intensidad, notas) VALUES (?,?,?,?)";
      if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param('isis', $userId, $tipo, $intensidad, $notas);
        if ($stmt->execute()) $msg = 'Síntoma registrado correctamente.';
        else $err = 'No se pudo guardar el registro.';
        $stmt->close();
      } else {
        $err = 'Error del servidor.';
      }
    }
  }
  if (!$err) { header('Location: salud.php?view=historial&ok=1'); exit; }
}

if ($view === 'export' && isset($_GET['from'], $_GET['to'])) {
  // Exportar CSV del historial del usuario (rango)
  $from = $_GET['from']; $to = $_GET['to'];
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="historial_sintomas.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Fecha','Tipo','Intensidad','Notas']);
  $sql = "SELECT fecha,tipo,intensidad,notas FROM sintomas
          WHERE user_id=? AND DATE(fecha) BETWEEN ? AND ?
          ORDER BY fecha DESC";
  $stmt = $conexion->prepare($sql);
  $stmt->bind_param('iss', $userId, $from, $to);
  $stmt->execute();
  $res = $stmt->get_result();
  while($r=$res->fetch_assoc()){
    fputcsv($out, [$r['fecha'],$r['tipo'],$r['intensidad'],$r['notas']]);
  }
  fclose($out); exit;
}

// --------- Datos para vistas / utilidades ---------
function get_umbral_intensidad(mysqli $cx, int $uid): int {
  $umbral = 7; // por defecto
  if ($stmt = $cx->prepare("SELECT umbral_intensidad FROM umbrales_usuario WHERE user_id=?")) {
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $umbral = (int)$row['umbral_intensidad'];
    $stmt->close();
  }
  return $umbral;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Salud - CuidApp</title>
  <link rel="stylesheet" href="../css/salud.css" />
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --bg:#eef7f7; --card:#fff; --radius:18px; }
    *{box-sizing:border-box}
    body{margin:0; font-family:system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial; background:#e8f2f2}
    .shell{max-width:420px; margin:0 auto; min-height:100svh; background:#f5f7f8; display:flex; flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1); padding:24px 18px; text-align:center; border-bottom-left-radius:var(--radius); border-bottom-right-radius:var(--radius)}
    .header h1{margin:8px 0 4px; font-size:26px}
    .header p{margin:0; color:var(--muted); font-size:14px}
    .options{padding:16px}
    .option{display:flex; align-items:center; justify-content:space-between; padding:14px; border-radius:14px; background:var(--card); text-decoration:none; color:inherit; border:1px solid #e5e7eb; margin-bottom:12px}
    .option:hover{box-shadow:0 6px 18px rgba(0,0,0,.06)}
    .icon-text{display:flex; align-items:center; gap:12px}
    .icon{width:52px; height:52px; object-fit:contain}
    .arrow{font-size:22px; color:#94a3b8}
    .msg{margin:12px 18px 0; padding:10px 12px; border-radius:10px; font-size:14px}
    .ok{background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0}
    .err{background:#fef2f2; color:#991b1b; border:1px solid #fecaca}
    form .row{display:flex; flex-direction:column; gap:6px; margin-bottom:12px}
    input[type="text"], input[type="number"], textarea, input[type="date"] {
      width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px; background:#fff
    }
    textarea{min-height:90px; resize:vertical}
    .btn{appearance:none; border:0; background:var(--primary); color:#fff; font-weight:700; padding:12px 14px; border-radius:12px; cursor:pointer; width:100%}
    .btn:hover{filter:brightness(.95)}
    .list{padding:16px}
    .item{background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin-bottom:10px}
    .muted{color:var(--muted)}
    footer nav{display:flex; justify-content:space-around; padding:10px 0 14px; background:linear-gradient(0deg, #dbe7e7, #eaf3f3); border-top-left-radius:var(--radius); border-top-right-radius:var(--radius); margin-top:auto}
    footer button{background:#fff; border:1px solid #e5e7eb; width:56px; height:56px; border-radius:50%; display:grid; place-items:center; box-shadow:0 4px 12px rgba(0,0,0,.05); cursor:pointer}
    .filters{display:grid; grid-template-columns:1fr 1fr; gap:10px; margin: 0 16px 10px}
    .export{margin:0 16px 10px}
    .topbar{display:flex; align-items:center; justify-content:space-between; padding:12px 16px}
    .link{color:var(--primary); text-decoration:none; font-weight:600}
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a href="salud.php" class="link">Salud</a>
    </div>

    <!-- Encabezado -->
    <header class="header">
      <h1>Salud</h1>
      <p>Gestiona tus síntomas y consulta tus datos médicos.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?>
      <div class="msg ok">Registro de síntoma guardado.</div>
    <?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <!-- Lista de opciones -->
      <main class="options">
        <a href="salud.php?view=nuevo" class="option">
          <div class="icon-text">
            <img src="../imagenes/sintomas.png" alt="Registro de Síntomas" class="icon">
            <div>
              <h2>Registro de Síntomas</h2>
              <p class="muted">Ingresar nuevos síntomas.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="salud.php?view=alertas" class="option">
          <div class="icon-text">
            <img src="../imagenes/alertas.png" alt="Alertas Médicas" class="icon">
            <div>
              <h2>Alertas Médicas</h2>
              <p class="muted">Consultar alertas fuera de rango.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a href="salud.php?view=historial" class="option">
          <div class="icon-text">
            <img src="../imagenes/historiarlm.png" alt="Historial Médico" class="icon">
            <div>
              <h2>Historial Médico</h2>
              <p class="muted">Ingresar o consultar historial médico.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>
      </main>

    <?php elseif ($view === 'nuevo'): ?>
      <!-- Formulario de nuevo síntoma -->
      <main class="list">
        <form method="post" action="salud.php?view=nuevo" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <div class="row">
            <label>Tipo de síntoma</label>
            <input type="text" name="tipo" placeholder="Ej. Dolor de cabeza" required maxlength="60"
                   oninput="this.value=this.value.replace(/^\s+/, '')">
          </div>
          <div class="row">
            <label>Intensidad (1–10)</label>
            <input type="number" name="intensidad" min="1" max="10" step="1" required>
          </div>
          <div class="row">
            <label>Notas (opcional)</label>
            <textarea name="notas" maxlength="500" placeholder="Descripción, detonantes, hora, medicación…"></textarea>
          </div>
          <button class="btn" type="submit">Guardar síntoma</button>
          <div style="margin-top:10px"><a class="link" href="salud.php">← Volver</a></div>
        </form>
      </main>

    <?php elseif ($view === 'alertas'): ?>
      <!-- Alertas según umbral -->
      <?php
        $umbral = get_umbral_intensidad($conexion, $userId);
        $q = $conexion->prepare("SELECT fecha,tipo,intensidad,notas FROM sintomas WHERE user_id=? AND intensidad >= ? ORDER BY fecha DESC LIMIT 100");
        $q->bind_param('ii', $userId, $umbral);
        $q->execute(); $alerts = $q->get_result(); $q->close();
      ?>
      <main class="list">
        <div class="muted" style="margin:0 16px 10px">Umbral de alerta: ≥ <?=$umbral?></div>
        <?php if ($alerts->num_rows === 0): ?>
          <div class="item">No hay alertas por ahora.</div>
        <?php else: while($a = $alerts->fetch_assoc()): ?>
          <div class="item">
            <div><strong><?=h($a['tipo'])?></strong> · <span class="muted"><?=h($a['fecha'])?></span></div>
            <div>Intensidad: <strong><?= (int)$a['intensidad'] ?></strong></div>
            <?php if (!empty($a['notas'])): ?><div class="muted"><?=h($a['notas'])?></div><?php endif; ?>
          </div>
        <?php endwhile; endif; ?>
        <div><a class="link" href="salud.php">← Volver</a></div>
      </main>

    <?php elseif ($view === 'historial'): ?>
      <!-- Historial + filtros + export -->
      <?php
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to   = $_GET['to']   ?? date('Y-m-d');
        $stmt = $conexion->prepare("SELECT id,fecha,tipo,intensidad,notas FROM sintomas WHERE user_id=? AND DATE(fecha) BETWEEN ? AND ? ORDER BY fecha DESC");
        $stmt->bind_param('iss', $userId, $from, $to);
        $stmt->execute(); $rows = $stmt->get_result(); $stmt->close();
      ?>
      <form class="filters" method="get" action="salud.php">
        <input type="hidden" name="view" value="historial">
        <div><label>Desde</label><input type="date" name="from" value="<?=h($from)?>"></div>
        <div><label>Hasta</label><input type="date" name="to" value="<?=h($to)?>"></div>
        <div style="grid-column:1/-1"><button class="btn" type="submit">Filtrar</button></div>
      </form>
      <div class="export">
        <a class="link" href="salud.php?view=export&from=<?=h($from)?>&to=<?=h($to)?>">⬇ Exportar CSV</a>
      </div>
      <main class="list">
        <?php if ($rows->num_rows === 0): ?>
          <div class="item">No hay registros para el rango seleccionado.</div>
        <?php else: while($r=$rows->fetch_assoc()): ?>
          <div class="item">
            <div><strong><?=h($r['tipo'])?></strong> · <span class="muted"><?=h($r['fecha'])?></span></div>
            <div>Intensidad: <strong><?= (int)$r['intensidad'] ?></strong></div>
            <?php if (!empty($r['notas'])): ?><div class="muted"><?=h($r['notas'])?></div><?php endif; ?>
          </div>
        <?php endwhile; endif; ?>
        <div><a class="link" href="salud.php">← Volver</a></div>
      </main>

    <?php else: ?>
      <main class="options"><div class="item">Vista no encontrada.</div></main>
    <?php endif; ?>

    <!-- Barra inferior -->
    <footer>
      <nav>
        <button title="Historial" onclick="location.href='salud.php?view=historial'"><img src="../imagenes/historial.png" alt="Historial" width="30"></button>
        <button title="Inicio" onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" alt="Inicio" width="40">
        </button>
        <button title="Nuevo" onclick="location.href='salud.php?view=nuevo'"><img src="../imagenes/agregar.png" alt="Agregar" width="35"></button>
        <button title="Ajustes" onclick="location.href='../modulos/ajustes.php'"><img src="../imagenes/configuracion.png" alt="Configuración" width="35"></button>
      </nav>
    </footer>
  </div>

  <!-- JS global -->
  <script src="../js/app.js" defer></script>
</body>
</html>
