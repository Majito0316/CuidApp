<?php
// modulos/soporte.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';
$toDash = dash_path();


require_role([1,2,3]); // todos los roles logueados

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

// --- ACCIONES ---
// Crear ticket de soporte
if ($view === 'ticket' && is_post()) {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $err = 'Solicitud inválida (CSRF).';
  } else {
    $asunto = trim((string)($_POST['asunto'] ?? ''));
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));

    if ($asunto === '' || mb_strlen($asunto) > 120 || $descripcion === '') {
      $err = 'Completa asunto (≤120) y descripción.';
    } else {
      // archivo opcional (imagen/pdf) máx 4MB
      $archivo_guardado = null;
      if (!empty($_FILES['adjunto']['name']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['adjunto'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $okExt = in_array($ext, ['png','jpg','jpeg','pdf'], true);
        $okSize = ($f['size'] <= 4 * 1024 * 1024);
        if ($okExt && $okSize) {
          $dir = __DIR__ . '/../uploads/soporte';
          if (!is_dir($dir)) @mkdir($dir, 0775, true);
          $safe = preg_replace('/[^a-z0-9\-_\.]/i','_', pathinfo($f['name'], PATHINFO_FILENAME));
          $final = $safe . '_' . time() . '.' . $ext;
          if (move_uploaded_file($f['tmp_name'], $dir . '/' . $final)) {
            $archivo_guardado = $final;
          }
        }
      }

      if ($stmt = $conexion->prepare("INSERT INTO soporte_tickets (user_id, asunto, categoria, descripcion, adjunto) VALUES (?,?,?,?,?)")) {
        $stmt->bind_param('issss', $uid, $asunto, $categoria, $descripcion, $archivo_guardado);
        if ($stmt->execute()) $msg = 'Ticket enviado correctamente.'; else $err = 'No se pudo guardar el ticket.';
        $stmt->close();
      } else { $err = 'Error de servidor.'; }
    }
  }
  header('Location: soporte.php?view=mis-tickets&' . ($err ? 'err=1' : 'ok=1')); exit;
}

// --- DATOS PARA VISTAS ---
$tickets = [];
$faqs    = [];

if ($stmt = $conexion->prepare("SELECT id, asunto, categoria, estado, creado_en FROM soporte_tickets WHERE user_id=? ORDER BY id DESC LIMIT 100")) {
  $stmt->bind_param('i', $uid);
  $stmt->execute(); $tickets = fetch_all($stmt->get_result());
  $stmt->close();
}

// FAQs (todas; luego se filtran en el front con búsqueda)
if ($res = $conexion->query("SELECT id, pregunta, respuesta FROM faqs ORDER BY id ASC")) {
  $faqs = fetch_all($res);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Soporte - CuidApp</title>
  <link rel="stylesheet" href="../css/soporte.css">
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
    .options{padding:16px}
    .option{display:flex;align-items:center;justify-content:space-between;padding:14px;border-radius:14px;background:var(--card);text-decoration:none;color:inherit;border:1px solid #e5e7eb;margin-bottom:12px}
    .icon-text{display:flex;align-items:center;gap:12px}
    .icon{width:52px;height:52px;object-fit:contain}
    .arrow{font-size:22px;color:#94a3b8}
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .section{padding:16px}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}
    input[type="text"], select, textarea, input[type="file"]{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    textarea{min-height:120px;resize:vertical}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:12px;border-radius:12px;cursor:pointer}
    .list{margin-top:12px}
    .item{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;margin-bottom:10px}
    .muted{color:var(--muted)}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;border:1px solid #e5e7eb;background:#f8fafc}
    .faq{background:#fff;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:8px;overflow:hidden}
    .faq summary{cursor:pointer;padding:12px;font-weight:700}
    .faq .ans{padding:0 12px 12px;color:#374151}
    .search{padding:0 16px 8px}
    .search input{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:var(--radius);border-top-right-radius:var(--radius);margin-top:auto}
    footer button{background:#fff;border:1px solid #e5e7eb;width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
  </style>
</head>
<body>
  <div class="shell">
    <div class="topbar">
      <div class="muted">Hola, <?=h($nombre)?></div>
      <a class="link" href="soporte.php">Soporte</a>
    </div>

    <header class="header">
      <h1>Soporte</h1>
      <p>Encuentra tutoriales y guías para manejar equipos médicos y actuar en emergencias.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>
    <?php if ($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if ($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <?php if ($view === 'home'): ?>
      <main class="options">
        <a class="option" href="soporte.php?view=ajustes">
          <div class="icon-text">
            <img class="icon" src="../imagenes/configuracion.png" alt="Ajustes">
            <div>
              <h2>Ajustes</h2>
              <p class="muted">Cambiar perfil, notificaciones y privacidad.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="soporte.php?view=ticket">
          <div class="icon-text">
            <img class="icon" src="../imagenes/soporte.png" alt="Asistente Técnico">
            <div>
              <h2>Asistente Técnico</h2>
              <p class="muted">Contactar a soporte y crear un ticket.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="soporte.php?view=faqs">
          <div class="icon-text">
            <img class="icon" src="../imagenes/ayudaE.png" alt="Preguntas Frecuentes">
            <div>
              <h2>Preguntas Frecuentes</h2>
              <p class="muted">Resuelve dudas rápidamente.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>

        <a class="option" href="soporte.php?view=mis-tickets">
          <div class="icon-text">
            <img class="icon" src="../imagenes/historial.png" alt="Mis tickets">
            <div>
              <h2>Mis tickets</h2>
              <p class="muted">Revisa el estado de tus solicitudes.</p>
            </div>
          </div>
          <span class="arrow">›</span>
        </a>
      </main>

    <?php elseif ($view === 'ajustes'): ?>
      <section class="section">
        <a class="link" href="soporte.php">← Volver</a>
        <h2>Ajustes</h2>
        <p class="muted">Esta opción redirige al módulo de ajustes general.</p>
        <button class="btn" onclick="location.href='../ajustes.php'">Ir a Ajustes</button>
      </section>

    <?php elseif ($view === 'ticket'): ?>
      <section class="section">
        <a class="link" href="soporte.php">← Volver</a>
        <h2>Crear ticket</h2>
        <form method="post" action="soporte.php?view=ticket" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <div class="row">
            <label>Asunto</label>
            <input type="text" name="asunto" maxlength="120" required placeholder="Ej. Problema con lector de glucosa">
          </div>
          <div class="row">
            <label>Categoría</label>
            <select name="categoria" required>
              <option value="">Selecciona una categoría</option>
              <option value="dispositivo">Dispositivo médico</option>
              <option value="medicamentos">Medicamentos</option>
              <option value="app">Aplicación / Cuenta</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div class="row">
            <label>Descripción</label>
            <textarea name="descripcion" required placeholder="Describe el problema, cuándo sucede y pasos para reproducir."></textarea>
          </div>
          <div class="row">
            <label>Adjunto (opcional: PNG/JPG/PDF máx. 4MB)</label>
            <input type="file" name="adjunto" accept=".png,.jpg,.jpeg,.pdf">
          </div>
          <button class="btn" type="submit">Enviar ticket</button>
        </form>
      </section>

    <?php elseif ($view === 'mis-tickets'): ?>
      <section class="section">
        <a class="link" href="soporte.php">← Volver</a>
        <h2>Mis tickets</h2>
        <div class="list">
          <?php if (!$tickets): ?>
            <div class="item muted">Aún no has creado tickets.</div>
          <?php else: foreach ($tickets as $t): ?>
            <div class="item">
              <div><strong>#<?= (int)$t['id'] ?></strong> — <?=h($t['asunto'])?></div>
              <div class="muted"><?=h($t['categoria'])?> · <?=h($t['creado_en'])?></div>
              <div style="margin-top:6px"><span class="badge"><?=h(ucfirst($t['estado']))?></span></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>

    <?php elseif ($view === 'faqs'): ?>
      <section class="section">
        <a class="link" href="soporte.php">← Volver</a>
        <h2>Preguntas Frecuentes</h2>

        <div class="search">
          <input id="q" type="text" placeholder="Buscar en preguntas..." oninput="filterFaqs()">
        </div>

        <div id="faqs">
          <?php if (!$faqs): ?>
            <div class="item muted">Aún no hay preguntas frecuentes cargadas.</div>
          <?php else: foreach ($faqs as $f): ?>
            <details class="faq">
              <summary><?=h($f['pregunta'])?></summary>
              <div class="ans"><?=nl2br(h($f['respuesta']))?></div>
            </details>
          <?php endforeach; endif; ?>
        </div>
      </section>

      <script>
        function filterFaqs(){
          const q = (document.getElementById('q').value || '').toLowerCase();
          document.querySelectorAll('#faqs .faq').forEach(el=>{
            const text = el.querySelector('summary').textContent.toLowerCase() + ' ' + el.querySelector('.ans').textContent.toLowerCase();
            el.style.display = text.includes(q) ? '' : 'none';
          });
        }
      </script>

    <?php else: ?>
      <main class="options"><div class="item">Vista no encontrada.</div></main>
    <?php endif; ?>

    <!-- Barra inferior -->
    <footer>
      <nav>
        <button title="Inicio" onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" alt="Inicio" width="40">
        </button>
        <button title="Mis tickets" onclick="location.href='soporte.php?view=mis-tickets'"><img src="../imagenes/historial.png" alt="Tickets" width="30"></button>
        <button title="Nuevo ticket" onclick="location.href='soporte.php?view=ticket'"><img src="../imagenes/agregar.png" alt="Nuevo" width="35"></button>
        <button title="Ajustes" onclick="location.href='../modulos/ajustes.php'"><img src="../imagenes/configuracion.png" alt="Configuración" width="35"></button>
      </nav>
    </footer>
  </div>
</body>
</html>
