<?php
// modulos/ayuda.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();


require_role([1,2,3]); // todos los roles logueados

$uid    = uid();
$nombre = user();

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

$view = $_GET['view'] ?? 'home';
$msg = $err = '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD']==='POST'; }
function fetch_all($res){ $a=[]; if($res){ while($r=$res->fetch_assoc()){ $a[]=$r; } } return $a; }

/* ---------- DATOS BASE ---------- */
// Recursos (guías y tutoriales)
$recursos = [];
if ($res = $conexion->query("SELECT id, titulo, tipo, descripcion, url FROM ayuda_recursos ORDER BY id DESC")) {
  $recursos = fetch_all($res);
}

// Glosario (se filtra por q)
$glosario = [];
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
  // Guardar historial de búsqueda
  if ($stmt = $conexion->prepare("INSERT INTO ayuda_busquedas (user_id, termino) VALUES (?,?)")) {
    $stmt->bind_param('is', $uid, $q); $stmt->execute(); $stmt->close();
  }
  $like = '%' . $q . '%';
  $stmt = $conexion->prepare("SELECT termino, definicion FROM glosario WHERE termino LIKE ? OR definicion LIKE ? ORDER BY termino ASC LIMIT 200");
  $stmt->bind_param('ss', $like, $like);
  $stmt->execute(); $glosario = fetch_all($stmt->get_result()); $stmt->close();
} else {
  if ($res = $conexion->query("SELECT termino, definicion FROM glosario ORDER BY termino ASC LIMIT 100")) {
    $glosario = fetch_all($res);
  }
}

// Historial de búsquedas del usuario
$hist = [];
if ($stmt = $conexion->prepare("SELECT id, termino, creado_en FROM ayuda_busquedas WHERE user_id=? ORDER BY id DESC LIMIT 50")) {
  $stmt->bind_param('i', $uid); $stmt->execute(); $hist = fetch_all($stmt->get_result()); $stmt->close();
}

// Limpiar historial
if ($view === 'historial' && isset($_GET['clear']) && $_GET['clear']==='1' && isset($_GET['csrf']) && hash_equals($csrf, $_GET['csrf'])) {
  $stmt = $conexion->prepare("DELETE FROM ayuda_busquedas WHERE user_id=?");
  $stmt->bind_param('i', $uid); $stmt->execute(); $stmt->close();
  header('Location: ayuda.php?view=historial&ok=1'); exit;
}

// Contactos de emergencia (usuario)
$contactos = [];
if ($stmt = $conexion->prepare("SELECT id, nombre, relacion, telefono, whatsapp FROM contactos_emergencia WHERE user_id=? ORDER BY id ASC")) {
  $stmt->bind_param('i', $uid); $stmt->execute(); $contactos = fetch_all($stmt->get_result()); $stmt->close();
}

// Centros de salud (si ya usas esta tabla)
// Centros de salud (elige columna de teléfono que exista y aliásala como tel_contacto)
$centros = [];
$telCol = 'tel_contacto';

// Detecta si existe 'tel_contacto'; si no, usa 'telefono'
if ($chk = $conexion->query("SHOW COLUMNS FROM centros_salud LIKE 'tel_contacto'")) {
  if ($chk->num_rows === 0) { $telCol = 'telefono'; }
  $chk->close();
}
// arma la expresión SELECT con alias fijo 'tel_contacto'
$colExpr = ($telCol === 'telefono') ? "telefono AS tel_contacto" : "tel_contacto";

$sql = "SELECT id, nombre, direccion, $colExpr
        FROM centros_salud
        ORDER BY nombre ASC
        LIMIT 50";

if ($res = $conexion->query($sql)) {
  $centros = fetch_all($res);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ayuda - CuidApp</title>
  <link rel="stylesheet" href="../css/ayuda.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --bg:#eef7f7; --card:#fff; --radius:18px; }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .topbar{display:flex;align-items:center;justify-content:space-between;padding:12px 16px}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:var(--radius);border-bottom-right-radius:var(--radius)}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .options{padding:16px}
    .option{display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:14px;background:var(--card);text-decoration:none;color:inherit;border:1px solid #e5e7eb;margin-bottom:12px}
    .icon-text{display:flex;align-items:center;gap:12px}
    .icon{width:52px;height:52px;object-fit:contain}
    .arrow{font-size:22px;color:#94a3b8}
    .link{color:var(--primary);text-decoration:none;font-weight:600}
    .section{padding:16px}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    input[type="text"],input[type="search"],textarea,select{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    .list{margin-top:12px}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:10px}
    .muted{color:var(--muted)}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;border:1px solid #e5e7eb;background:#f8fafc}
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);margin-top:auto}
    footer button{background:#fff;border:1px solid #e5e7eb;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a class="link" href="ayuda.php">Ayuda</a>
    </div>

    <header class="header">
      <h1>Ayuda</h1>
      <p>Encuentra tutoriales y guías para manejar equipos médicos y actuar en emergencias.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <main class="options">
        <a class="option" href="ayuda.php?view=guias">
          <div class="icon-text">
            <img src="../imagenes/fichavirtual.png" alt="Guías y tutoriales" class="icon">
            <div>
              <h2>Guías y tutoriales</h2>
              <p class="muted">Material paso a paso para resolver dudas.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="ayuda.php?view=glosario">
          <div class="icon-text">
            <img src="../imagenes/centros.png" alt="Glosario Médico" class="icon">
            <div>
              <h2>Glosario Médico</h2>
              <p class="muted">Consulta términos y significados.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="ayuda.php?view=emergencia">
          <div class="icon-text">
            <img src="../imagenes/ubicacion.png" alt="Centro Emergencia" class="icon">
            <div>
              <h2>Centro Emergencia</h2>
              <p class="muted">Contactar cuidador o centros de salud.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="ayuda.php?view=historial">
          <div class="icon-text">
            <img src="../imagenes/historial.png" alt="Historial de búsquedas" class="icon">
            <div>
              <h2>Historial de Búsquedas</h2>
              <p class="muted">Accede a tus consultas recientes.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>
      </main>

    <?php elseif ($view === 'guias'): ?>
      <section class="section">
        <a class="link" href="ayuda.php">← Volver</a>
        <h2>Guías y tutoriales</h2>
        <div class="list">
          <?php if (!$recursos): ?>
            <div class="item muted">Aún no hay recursos cargados.</div>
          <?php else: foreach ($recursos as $r): ?>
            <div class="item">
              <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <div>
                  <strong><?=h($r['titulo'])?></strong>
                  <span class="badge"><?=h(ucfirst($r['tipo']))?></span>
                </div>
                <?php if (!empty($r['url'])): ?>
                  <a class="link" href="<?=h($r['url'])?>" target="_blank" rel="noopener">Abrir</a>
                <?php endif; ?>
              </div>
              <?php if (!empty($r['descripcion'])): ?>
                <div class="muted" style="margin-top:6px"><?=h($r['descripcion'])?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'glosario'): ?>
      <section class="section">
        <a class="link" href="ayuda.php">← Volver</a>
        <h2>Glosario Médico</h2>
        <form method="get" action="ayuda.php" class="row" style="margin-top:8px">
          <input type="hidden" name="view" value="glosario">
          <input type="search" name="q" value="<?=h($q)?>" placeholder="Buscar término o definición…">
        </form>
        <div class="list" id="gloss">
          <?php if (!$glosario): ?>
            <div class="item muted">No se encontraron resultados.</div>
          <?php else: foreach ($glosario as $g): ?>
            <div class="item">
              <div><strong><?=h($g['termino'])?></strong></div>
              <div class="muted"><?=nl2br(h($g['definicion']))?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'historial'): ?>
      <section class="section">
        <a class="link" href="ayuda.php">← Volver</a>
        <h2>Historial de búsquedas</h2>
        <div style="margin:8px 0">
          <a class="link" href="ayuda.php?view=historial&clear=1&csrf=<?=$csrf?>">🗑 Limpiar historial</a>
        </div>
        <div class="list">
          <?php if (!$hist): ?>
            <div class="item muted">Aún no has buscado términos.</div>
          <?php else: foreach ($hist as $h1): ?>
            <div class="item">
              <div><a class="link" href="ayuda.php?view=glosario&q=<?=h($h1['termino'])?>"><?=h($h1['termino'])?></a></div>
              <div class="muted"><?=h($h1['creado_en'])?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'emergencia'): ?>
      <section class="section">
        <a class="link" href="ayuda.php">← Volver</a>
        <h2>Centro de Emergencia</h2>

        <h3>Tus contactos</h3>
        <div class="list">
          <?php if (!$contactos): ?>
            <div class="item muted">No tienes contactos guardados. Agrégalos en <a class="link" href="../ajustes.php">Ajustes</a>.</div>
          <?php else: foreach ($contactos as $c): ?>
            <div class="item">
              <div><strong><?=h($c['nombre'])?></strong> · <span class="muted"><?=h($c['relacion'])?></span></div>
              <?php if (!empty($c['telefono'])): ?>
                <div>Tel: <a class="link" href="tel:<?=h($c['telefono'])?>"><?=h($c['telefono'])?></a></div>
              <?php endif; ?>
              <?php if (!empty($c['whatsapp'])): ?>
                <div>WhatsApp: <a class="link" href="https://wa.me/<?=h(preg_replace('/\D/','',$c['whatsapp']))?>" target="_blank" rel="noopener">Abrir chat</a></div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <h3>Centros de salud</h3>
        <div class="list">
          <?php if (!$centros): ?>
            <div class="item muted">No hay centros cargados.</div>
          <?php else: foreach ($centros as $cs): ?>
            <div class="item">
              <div><strong><?=h($cs['nombre'])?></strong></div>
              <div class="muted"><?=h($cs['direccion'])?></div>
              <?php if (!empty($cs['tel_contacto'])): ?>
                <div><a class="link" href="tel:<?=h($cs['tel_contacto'])?>">Llamar</a></div>
              <?php endif; ?>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div style="margin-top:10px">
          <a class="link" href="../modulos/ubicacion.php?view=centros">Ver en mapa</a>
        </div>
      </section>

    <?php else: ?>
      <main class="options"><div class="item">Vista no encontrada.</div></main>
    <?php endif; ?>

    <footer>
      <nav>
        <button title="Inicio" onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" alt="Inicio" width="40">
        </button>
        <button title="Glosario" onclick="location.href='ayuda.php?view=glosario'"><img src="../imagenes/centros.png" alt="Glosario" width="30"></button>
        <button title="Emergencia" onclick="location.href='ayuda.php?view=emergencia'"><img src="../imagenes/agregar.png" alt="Emergencia" width="35"></button>
        <button title="Historial" onclick="location.href='ayuda.php?view=historial'"><img src="../imagenes/historial.png" alt="Historial" width="35"></button>
      </nav>
    </footer>
  </div>
</body>
</html>
