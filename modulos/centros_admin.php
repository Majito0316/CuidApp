<?php
// modulos/centros_admin.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';

require_role([3]); // solo admin

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD']==='POST'; }

$view   = $_GET['view'] ?? 'list';
$q      = trim((string)($_GET['q'] ?? ''));
$fstate = $_GET['estado'] ?? 'todos'; // todos | Pendiente | Aprobado | Rechazado
$msg = $err = '';

// Asegura columna activo (por si en tu BD no estaba)
@$conexion->query("ALTER TABLE centros_salud ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1");

/* =================== ACCIONES =================== */

// Crear
if ($view==='new' && is_post()){
  if (!hash_equals($csrf, $_POST['csrf']??'')) { $err='CSRF inválido.'; }
  else{
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $tel    = trim((string)($_POST['telefono'] ?? ''));
    $dir    = trim((string)($_POST['direccion'] ?? ''));
    $estado = $_POST['estado'] ?? 'Pendiente';
    $lat    = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
    $lng    = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;

    if ($nombre===''){ $err='El nombre es obligatorio.'; }
    elseif (!in_array($estado, ['Pendiente','Aprobado','Rechazado'], true)){ $err='Estado inválido.'; }
    else{
      $stmt = $conexion->prepare("INSERT INTO centros_salud (nombre,telefono,direccion,estado,lat,lng,activo) VALUES (?,?,?,?,?,?,1)");
      $stmt->bind_param('ssssdd', $nombre,$tel,$dir,$estado,$lat,$lng);
      if ($stmt->execute()) $msg='Centro creado.'; else $err='No se pudo crear.';
      $stmt->close();
    }
  }
  header('Location: centros_admin.php?'.($err?'err=1':'ok=1')); exit;
}

// Editar
if ($view==='edit' && is_post()){
  if (!hash_equals($csrf, $_POST['csrf']??'')) { $err='CSRF inválido.'; }
  else{
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $tel    = trim((string)($_POST['telefono'] ?? ''));
    $dir    = trim((string)($_POST['direccion'] ?? ''));
    $estado = $_POST['estado'] ?? 'Pendiente';
    $lat    = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
    $lng    = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;
    $act    = isset($_POST['activo']) ? 1 : 0;

    if ($id<=0 || $nombre===''){ $err='Datos inválidos.'; }
    elseif (!in_array($estado, ['Pendiente','Aprobado','Rechazado'], true)){ $err='Estado inválido.'; }
    else{
      $stmt = $conexion->prepare("UPDATE centros_salud SET nombre=?, telefono=?, direccion=?, estado=?, lat=?, lng=?, activo=? WHERE id=?");
      $stmt->bind_param('ssssddii', $nombre,$tel,$dir,$estado,$lat,$lng,$act,$id);
      if ($stmt->execute()) $msg='Centro actualizado.'; else $err='No se pudo actualizar.';
      $stmt->close();
    }
  }
  header('Location: centros_admin.php?'.($err?'err=1':'ok=1')); exit;
}

// Toggle activo (borrado lógico)
if ($view==='toggle' && isset($_GET['id'])){
  $id = (int)$_GET['id'];
  if ($id>0){ $conexion->query("UPDATE centros_salud SET activo = IF(activo=1,0,1) WHERE id={$id}"); }
  header('Location: centros_admin.php'); exit;
}

/* =================== LISTADO =================== */

$where = [];
$params = []; $types='';

if ($q !== ''){
  $where[] = "(nombre LIKE CONCAT('%', ?, '%') OR telefono LIKE CONCAT('%', ?, '%'))";
  $params[] = $q; $params[] = $q; $types .= 'ss';
}
if (in_array($fstate, ['Pendiente','Aprobado','Rechazado'], true)){
  $where[] = "estado = ?";
  $params[] = $fstate; $types .= 's';
}
$where[] = "activo = 1"; // muestra activos por defecto

$sql = "SELECT id,nombre,telefono,estado FROM centros_salud".
       ( $where ? ' WHERE '.implode(' AND ',$where) : '' ).
       " ORDER BY FIELD(estado,'Pendiente','Rechazado','Aprobado'), nombre ASC";
$stmt = $conexion->prepare($sql);
if ($params){ $stmt->bind_param($types, ...$params); }
$stmt->execute();
$rows = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Centros de Salud</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --border:#e5e7eb; --radius:16px; }
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:20px;border-bottom-right-radius:20px}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .top{display:flex;gap:8px;align-items:center;padding:10px 12px}
    .btn-link{display:inline-block;background:#fff;border:1px solid var(--border);padding:8px 12px;border-radius:10px;text-decoration:none;color:inherit}
    form.filters{padding:0 12px 12px;display:grid;grid-template-columns:1fr auto;gap:8px}
    .input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:999px;background:#fff}
    select{padding:8px 10px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .list{padding:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:10px;display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center}
    .pill{display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;font-size:12px;border:1px solid var(--border)}
    .pill.pend{background:#fff7ed;border-color:#fed7aa}
    .pill.aprb{background:#ecfdf5;border-color:#a7f3d0}
    .pill.rech{background:#fef2f2;border-color:#fecaca}
    .dot{width:10px;height:10px;border-radius:50%}
    .dp{background:#f59e0b}.da{background:#22c55e}.dr{background:#ef4444}
    .name{font-weight:700}
    .muted{color:var(--muted);font-size:13px}
    .actions a{margin-left:10px;text-decoration:none}
    .section{padding:12px}
    .block{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
    input[type="text"],input[type="tel"],input[type="email"],textarea{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:12px;border-radius:12px;cursor:pointer;width:100%}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:20px;border-top-right-radius:20px;margin-top:auto}
    footer button{background:#fff;border:1px solid var(--border);width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
  </style>
</head>
<body>
  <div class="shell">
    <header class="header">
      <h1>Gestión de Centros</h1>
      <p>Control y aprobación de registros.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="block" style="margin:12px;color:#065f46;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;">Acción realizada.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="block" style="margin:12px;color:#991b1b;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;">No se pudo completar la acción.</div><?php endif; ?>

    <?php if ($view === 'list'): ?>
      <div class="top">
        <a class="btn-link" href="<?= dash_path() ?>">← Volver</a>
        <a class="btn-link" href="centros_admin.php?view=new">+ Nuevo</a>
      </div>

      <form class="filters" method="get" action="centros_admin.php">
        <input type="hidden" name="view" value="list">
        <input class="input" name="q" placeholder="Buscar Centro..." value="<?=h($q)?>">
        <select name="estado">
          <option value="todos" <?= $fstate==='todos'?'selected':'' ?>>Todos</option>
          <option value="Pendiente" <?= $fstate==='Pendiente'?'selected':'' ?>>Pendiente</option>
          <option value="Aprobado"  <?= $fstate==='Aprobado'?'selected':'' ?>>Aprobado</option>
          <option value="Rechazado" <?= $fstate==='Rechazado'?'selected':'' ?>>Rechazado</option>
        </select>
      </form>

      <main class="list">
        <?php if ($rows->num_rows===0): ?>
          <div class="block">Sin resultados.</div>
        <?php else: while($c=$rows->fetch_assoc()): 
              $pill = $c['estado']==='Pendiente'?'pend':($c['estado']==='Aprobado'?'aprb':'rech');
              $dot  = $c['estado']==='Pendiente'?'dp':($c['estado']==='Aprobado'?'da':'dr');
        ?>
          <div class="card">
            <div>
              <!-- icono hospital (simple) -->
              🏥
            </div>
            <div>
              <div class="name"><?=h($c['nombre'])?></div>
              <?php if(!empty($c['telefono'])): ?><div class="muted">Tel: <?=h($c['telefono'])?></div><?php endif; ?>
              <div class="pill <?=$pill?>"><span class="dot <?=$dot?>"></span> Estado: <?=h($c['estado'])?></div>
            </div>
            <div class="actions">
              <a title="Editar" href="centros_admin.php?view=edit&id=<?=$c['id']?>">✎</a>
              <a title="Aprobar" href="centros_admin.php?view=edit&id=<?=$c['id']?>#estado">✅</a>
              <a title="Des/activar" href="centros_admin.php?view=toggle&id=<?=$c['id']?>" onclick="return confirm('¿Cambiar estado activo?')">🗑️</a>
            </div>
          </div>
        <?php endwhile; endif; ?>
      </main>

    <?php elseif ($view === 'new'): ?>
      <section class="section">
        <a class="btn-link" href="centros_admin.php">← Volver</a>
        <div class="block">
          <h3>Nuevo Centro</h3>
          <form method="post" action="centros_admin.php?view=new" novalidate>
            <input type="hidden" name="csrf" value="<?=$csrf?>">
            <div class="row"><label>Nombre</label><input name="nombre" maxlength="120" required></div>
            <div class="row"><label>Teléfono</label><input type="tel" name="telefono" maxlength="30"></div>
            <div class="row"><label>Dirección</label><input name="direccion" maxlength="160"></div>
            <div class="row" id="estado"><label>Estado</label>
              <select name="estado">
                <option>Pendiente</option>
                <option>Aprobado</option>
                <option>Rechazado</option>
              </select>
            </div>
            <div class="row"><label>Latitud</label><input type="text" name="lat" placeholder="4.711"></div>
            <div class="row"><label>Longitud</label><input type="text" name="lng" placeholder="-74.0721"></div>
            <button class="btn" type="submit">Crear</button>
          </form>
        </div>
      </section>

    <?php elseif ($view === 'edit' && isset($_GET['id'])): ?>
      <?php
        $id = (int)($_GET['id'] ?? 0);
        $s = $conexion->prepare("SELECT * FROM centros_salud WHERE id=?");
        $s->bind_param('i',$id); $s->execute(); $c = $s->get_result()->fetch_assoc(); $s->close();
      ?>
      <?php if(!$c): ?>
        <main class="section"><div class="block">Centro no encontrado.</div></main>
      <?php else: ?>
        <section class="section">
          <a class="btn-link" href="centros_admin.php">← Volver</a>
          <div class="block">
            <h3>Editar Centro</h3>
            <form method="post" action="centros_admin.php?view=edit" novalidate>
              <input type="hidden" name="csrf" value="<?=$csrf?>">
              <input type="hidden" name="id" value="<?=$c['id']?>">
              <div class="row"><label>Nombre</label><input name="nombre" maxlength="120" required value="<?=h($c['nombre'])?>"></div>
              <div class="row"><label>Teléfono</label><input type="tel" name="telefono" maxlength="30" value="<?=h($c['telefono'])?>"></div>
              <div class="row"><label>Dirección</label><input name="direccion" maxlength="160" value="<?=h($c['direccion'])?>"></div>
              <div class="row" id="estado"><label>Estado</label>
                <select name="estado">
                  <option <?=$c['estado']==='Pendiente'?'selected':''?>>Pendiente</option>
                  <option <?=$c['estado']==='Aprobado'?'selected':''?>>Aprobado</option>
                  <option <?=$c['estado']==='Rechazado'?'selected':''?>>Rechazado</option>
                </select>
              </div>
              <div class="row"><label>Latitud</label><input type="text" name="lat" value="<?=h($c['lat'])?>"></div>
              <div class="row"><label>Longitud</label><input type="text" name="lng" value="<?=h($c['lng'])?>"></div>
              <div class="row"><label><input type="checkbox" name="activo" <?=$c['activo']?'checked':''?>> Activo</label></div>
              <button class="btn" type="submit">Guardar cambios</button>
            </form>
          </div>
        </section>
      <?php endif; ?>

    <?php else: ?>
      <main class="section"><div class="block">Vista no válida.</div></main>
    <?php endif; ?>

    <footer>
      <nav>
        <button title="Inicio" onclick="location.href='<?= dash_path() ?>'"><img src="../imagenes/logo.png" alt="Inicio" width="40"></button>
        <button title="Ajustes" onclick="location.href='ajustes.php'"><img src="../imagenes/configuracion.png" alt="Ajustes" width="35"></button>
      </nav>
    </footer>
  </div>
</body>
</html>
