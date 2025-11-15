<?php
// modulos/usuarios_admin.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';

require_role([3]); // Solo Administrador

$uidAdmin = uid();

// ---- CSRF ----
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD']==='POST'; }
function rol_name($n){ return [1=>'Paciente',2=>'Cuidador',3=>'Administrador'][$n] ?? '—'; }

// Asegura columna "activo" (borrado lógico)
@$conexion->query("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS activo TINYINT(1) NOT NULL DEFAULT 1");

// --------- Router + filtros ---------
$view   = $_GET['view'] ?? 'list';
$q      = trim((string)($_GET['q'] ?? ''));
$frol   = (int)($_GET['rol'] ?? 0);         // 0=todos, 1/2/3
$fact   = ($_GET['estado'] ?? '')==='inactivos' ? 'inactivos' : (($_GET['estado'] ?? '')==='activos'?'activos':'todos');

$msg = $err = '';

/* ===================  ACCIONES POST  =================== */

// Crear
if ($view==='new' && is_post()){
  if (!hash_equals($csrf, $_POST['csrf']??'')) { $err='Solicitud inválida (CSRF).'; }
  else{
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $correo = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
    $rol    = (int)($_POST['rol'] ?? 1);
    $pass   = (string)($_POST['contrasena'] ?? '');

    if ($nombre==='' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || !in_array($rol,[1,2,3],true) || strlen($pass)<8){
      $err = 'Revisa los campos (email válido y contraseña ≥ 8).';
    } else {
      // Unicidad de email
      $s = $conexion->prepare("SELECT idusuarios FROM usuarios WHERE correo=?");
      $s->bind_param('s',$correo); $s->execute();
      if ($s->get_result()->num_rows>0){ $err='El correo ya existe.'; }
      $s->close();

      if (!$err){
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $s = $conexion->prepare("INSERT INTO usuarios (nombre,correo,`contraseña`,idroles,activo) VALUES (?,?,?,?,1)");
        $s->bind_param('sssi',$nombre,$correo,$hash,$rol);
        if ($s->execute()) $msg='Usuario creado.'; else $err='No se pudo crear.';
        $s->close();
      }
    }
  }
  header('Location: usuarios_admin.php?'.($err?'err=1':'ok=1')); exit;
}

// Editar
if ($view==='edit' && is_post()){
  if (!hash_equals($csrf, $_POST['csrf']??'')) { $err='Solicitud inválida (CSRF).'; }
  else{
    $id     = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $correo = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
    $rol    = (int)($_POST['rol'] ?? 1);
    $act    = isset($_POST['activo']) ? 1 : 0;

    if ($id<=0 || $nombre==='' || !filter_var($correo,FILTER_VALIDATE_EMAIL) || !in_array($rol,[1,2,3],true)){
      $err='Datos inválidos.';
    } else {
      // Email único (excluyéndome)
      $s = $conexion->prepare("SELECT idusuarios FROM usuarios WHERE correo=? AND idusuarios<>?");
      $s->bind_param('si',$correo,$id); $s->execute();
      if ($s->get_result()->num_rows>0){ $err='Ese correo ya pertenece a otro usuario.'; }
      $s->close();

      if (!$err){
        $s = $conexion->prepare("UPDATE usuarios SET nombre=?, correo=?, idroles=?, activo=? WHERE idusuarios=?");
        $s->bind_param('ssiii',$nombre,$correo,$rol,$act,$id);
        if ($s->execute()) $msg='Usuario actualizado.'; else $err='No se pudo actualizar.';
        $s->close();
      }
    }
  }
  header('Location: usuarios_admin.php?'.($err?'err=1':'ok=1')); exit;
}

// Reset password
if ($view==='reset' && is_post()){
  if (!hash_equals($csrf, $_POST['csrf']??'')) { $err='CSRF inválido.'; }
  else{
    $id = (int)($_POST['id'] ?? 0);
    $np = (string)($_POST['nueva'] ?? '');
    if ($id<=0 || strlen($np)<8){ $err='Contraseña ≥ 8.'; }
    else{
      $hash = password_hash($np, PASSWORD_DEFAULT);
      $s = $conexion->prepare("UPDATE usuarios SET `contraseña`=? WHERE idusuarios=?");
      $s->bind_param('si',$hash,$id);
      if ($s->execute()) $msg='Contraseña restablecida.'; else $err='No se pudo restablecer.';
      $s->close();
    }
  }
  header('Location: usuarios_admin.php?'.($err?'err=1':'ok=1')); exit;
}

// Cambiar activo (toggle)
if ($view==='toggle' && isset($_GET['id'])){
  $id = (int)$_GET['id'];
  if ($id>0){
    $conexion->query("UPDATE usuarios SET activo = IF(activo=1,0,1) WHERE idusuarios={$id}");
  }
  header('Location: usuarios_admin.php'); exit;
}

/* ===================  LISTADO  =================== */
$where = [];
$params = []; $types = '';

if ($q !== ''){
  $where[] = "(u.nombre LIKE CONCAT('%', ?, '%') OR u.correo LIKE CONCAT('%', ?, '%'))";
  $params[] = $q; $params[] = $q; $types .= 'ss';
}
if (in_array($frol,[1,2,3],true)){
  $where[] = "u.idroles=?";
  $params[] = $frol; $types .= 'i';
}
if ($fact==='activos'){ $where[] = "u.activo=1"; }
if ($fact==='inactivos'){ $where[] = "u.activo=0"; }

$sql = "SELECT u.idusuarios, u.nombre, u.correo, u.idroles, u.activo, u.fecha_creacion
        FROM usuarios u".
        ( $where ? (' WHERE '.implode(' AND ',$where)) : '' ).
        " ORDER BY u.nombre ASC LIMIT 200";
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
  <title>Gestión de Usuarios - Admin</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --border:#e5e7eb; --radius:16px; }
    body{margin:0;font-family:system-ui, -apple-system, Segoe UI, Roboto, Inter, Arial;background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:20px;border-bottom-right-radius:20px}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .top{display:flex;gap:8px;align-items:center;padding:10px 12px}
    .btn-link{display:inline-block;background:#fff;border:1px solid var(--border);padding:8px 12px;border-radius:10px;text-decoration:none;color:inherit}
    form.filters{padding:0 12px 12px;display:grid;grid-template-columns:1fr auto auto;gap:8px}
    .input{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:999px;background:#fff}
    select,input[type="time"]{padding:8px 10px;border:1px solid var(--border);border-radius:10px;background:#fff}
    .list{padding:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:10px;display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center}
    .dot{width:14px;height:14px;border-radius:50%}
    .dot.on{background:#22c55e;border:1px solid #16a34a}
    .dot.off{background:#ef4444;border:1px solid #b91c1c}
    .name{font-weight:700}
    .muted{color:var(--muted);font-size:13px}
    .actions a{margin-left:8px;text-decoration:none}
    .msg{margin:12px 18px 0;padding:10px 12px;border-radius:10px;font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .section{padding:12px}
    .block{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px}
    .row{display:flex;flex-direction:column;gap:6px;margin-bottom:10px}
    input[type="text"],input[type="email"],input[type="password"]{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:12px;border-radius:12px;cursor:pointer;width:100%}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:20px;border-top-right-radius:20px;margin-top:auto}
    footer button{background:#fff;border:1px solid var(--border);width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
  </style>
</head>
<body>
  <div class="shell">
    <header class="header">
      <h1>Gestión de Usuarios</h1>
      <p>Administra usuarios, roles y acceso.</p>
    </header>

    <?php if (!empty($_GET['ok'])): ?><div class="msg ok">Acción realizada correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['err'])): ?><div class="msg err">No se pudo completar la acción.</div><?php endif; ?>

<?php if ($view === 'list'): ?>

  <!-- LISTA -->
  <div class="top">
    <a class="btn-link" href="<?= dash_path() ?>">← Volver</a>
    <a class="btn-link" href="usuarios_admin.php?view=new">+ Nuevo</a>
  </div>

  <form class="filters" method="get" action="usuarios_admin.php">
    <input type="hidden" name="view" value="list">
    <input class="input" name="q" placeholder="Buscar usuario..." value="<?=h($q)?>">
    <select name="rol" title="Filtrar rol">
      <option value="0" <?= $frol===0?'selected':'' ?>>Todos los roles</option>
      <option value="1" <?= $frol===1?'selected':'' ?>>Paciente</option>
      <option value="2" <?= $frol===2?'selected':'' ?>>Cuidador</option>
      <option value="3" <?= $frol===3?'selected':'' ?>>Administrador</option>
    </select>
    <select name="estado" title="Estado">
      <option value="todos"     <?= $fact==='todos'?'selected':'' ?>>Todos</option>
      <option value="activos"   <?= $fact==='activos'?'selected':'' ?>>Activos</option>
      <option value="inactivos" <?= $fact==='inactivos'?'selected':'' ?>>Inactivos</option>
    </select>
  </form>

  <main class="list">
    <?php if ($rows->num_rows === 0): ?>
      <div class="block">Sin resultados.</div>
    <?php else: ?>
      <?php while($u = $rows->fetch_assoc()): ?>
        <div class="card">
          <div class="dot <?= $u['activo']?'on':'off' ?>"></div>
          <div>
            <div class="name"><?=h($u['nombre'])?></div>
            <div class="muted">Rol: <?=h(rol_name((int)$u['idroles']))?> · <?=h($u['correo'])?></div>
          </div>
          <div class="actions">
            <a title="Editar" href="usuarios_admin.php?view=edit&id=<?=$u['idusuarios']?>">✎</a>
            <a title="<?= $u['activo']?'Desactivar':'Reactivar' ?>" href="usuarios_admin.php?view=toggle&id=<?=$u['idusuarios']?>" onclick="return confirm('¿Seguro?')"><?= $u['activo']?'🗑️':'↺' ?></a>
            <a title="Reset clave" href="#" onclick="openReset(<?=$u['idusuarios']?>);return false;">🔑</a>
          </div>
        </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </main>

  <dialog id="resetDlg">
    <form method="post" action="usuarios_admin.php?view=reset" class="section block">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="id" id="resetId">
      <h3>Restablecer contraseña</h3>
      <div class="row"><input type="password" name="nueva" placeholder="Nueva contraseña (≥ 8)" required minlength="8"></div>
      <button class="btn" type="submit">Guardar</button>
      <div style="margin-top:8px;text-align:center"><a href="#" onclick="resetDlg.close();return false;">Cancelar</a></div>
    </form>
  </dialog>
  <script>
    const resetDlg = document.getElementById('resetDlg');
    function openReset(id){ document.getElementById('resetId').value=id; resetDlg.showModal(); }
  </script>

<?php elseif ($view === 'new'): ?>

  <!-- NUEVO -->
  <section class="section">
    <a class="btn-link" href="usuarios_admin.php">← Volver</a>
    <div class="block">
      <h3>Nuevo usuario</h3>
      <form method="post" action="usuarios_admin.php?view=new" novalidate>
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <div class="row"><label>Nombre</label><input name="nombre" maxlength="80" required></div>
        <div class="row"><label>Correo</label><input type="email" name="correo" maxlength="120" required></div>
        <div class="row">
          <label>Rol</label>
          <select name="rol" required>
            <option value="1">Paciente</option>
            <option value="2">Cuidador</option>
            <option value="3">Administrador</option>
          </select>
        </div>
        <div class="row"><label>Contraseña</label><input type="password" name="contrasena" minlength="8" required></div>
        <button class="btn" type="submit">Crear</button>
      </form>
    </div>
  </section>

<?php elseif ($view === 'edit' && isset($_GET['id'])): ?>

  <!-- EDITAR -->
  <?php
    $id = (int)($_GET['id'] ?? 0);
    $s = $conexion->prepare("SELECT idusuarios,nombre,correo,idroles,activo FROM usuarios WHERE idusuarios=?");
    $s->bind_param('i', $id);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    $s->close();
  ?>
  <?php if (!$u): ?>
    <main class="section"><div class="block">Usuario no encontrado.</div></main>
  <?php else: ?>
    <section class="section">
      <a class="btn-link" href="usuarios_admin.php">← Volver</a>
      <div class="block">
        <h3>Editar usuario</h3>
        <form method="post" action="usuarios_admin.php?view=edit" novalidate>
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <input type="hidden" name="id" value="<?=$u['idusuarios']?>">
          <div class="row"><label>Nombre</label><input name="nombre" maxlength="80" required value="<?=h($u['nombre'])?>"></div>
          <div class="row"><label>Correo</label><input type="email" name="correo" maxlength="120" required value="<?=h($u['correo'])?>"></div>
          <div class="row">
            <label>Rol</label>
            <select name="rol" required>
              <option value="1" <?=$u['idroles']==1?'selected':''?>>Paciente</option>
              <option value="2" <?=$u['idroles']==2?'selected':''?>>Cuidador</option>
              <option value="3" <?=$u['idroles']==3?'selected':''?>>Administrador</option>
            </select>
          </div>
          <div class="row"><label><input type="checkbox" name="activo" <?=$u['activo']?'checked':''?>> Activo</label></div>
          <button class="btn" type="submit">Guardar cambios</button>
        </form>
      </div>
    </section>
  <?php endif; ?>

<?php else: ?>

  <!-- VISTA DESCONOCIDA -->
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
