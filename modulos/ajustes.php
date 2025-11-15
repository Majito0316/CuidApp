<?php
// ajustes.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';

require_role([1,2,3]);            // cualquier usuario logueado
$uid    = uid();                   // helper de guards/session
$nombre = user();
$rol    = rol();

// utilidades
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD']==='POST'; }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

$toDash = [
  1 => '../dashboards/dashboardPaciente.php',
  2 => '../dashboards/dashboardCuidador.php',
  3 => '../dashboards/dashboardAdmin.php',
][$rol] ?? 'index.php';

// --------------------- CARGA DE DATOS ACTUALES ---------------------
$perfil = ['nombre'=>$nombre, 'correo'=>'', 'avatar'=>null];
if ($st = $conexion->prepare("SELECT nombre, correo FROM usuarios WHERE idusuarios=?")) {
  $st->bind_param('i',$uid); $st->execute(); $st->bind_result($perfil['nombre'],$perfil['correo']); $st->fetch(); $st->close();
}

// prefs por defecto si no existen
$prefs = [
  'idioma' => 'es',
  'tema' => 'sistema',
  'tam_fuente' => 'normal',
  'alto_contraste' => 0,
  'simplificado' => 0,
  'compartir_cuidador' => 1,
  'avatar' => null
];
// intentar leer user_prefs
if ($st = $conexion->prepare("SELECT idioma,tema,tam_fuente,alto_contraste,simplificado,compartir_cuidador,avatar FROM user_prefs WHERE user_id=?")) {
  $st->bind_param('i',$uid); $st->execute();
  if ($res = $st->get_result()) {
    if ($row = $res->fetch_assoc()) { $prefs = array_merge($prefs, $row); }
  }
  $st->close();
}

// --------------------- ACCIONES POST ---------------------
$msg = $err = '';

if (is_post() && (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf']))) {
  $err = 'Solicitud inválida (CSRF).';
}

if (!$err && is_post() && ($_POST['action'] ?? '') === 'perfil') {
  $nuevoNombre = trim((string)($_POST['nombre'] ?? ''));
  $nuevoCorreo = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
  if ($nuevoNombre === '' || !filter_var($nuevoCorreo, FILTER_VALIDATE_EMAIL)) {
    $err = 'Verifica nombre y correo.';
  } else {
    // ¿Correo ya usado por otro?
    if ($st = $conexion->prepare("SELECT idusuarios FROM usuarios WHERE correo=? AND idusuarios<>?")) {
      $st->bind_param('si', $nuevoCorreo, $uid); $st->execute();
      $dup = $st->get_result()->num_rows > 0; $st->close();
      if ($dup) { $err = 'Ese correo ya está en uso.'; }
    }
    // Avatar (opcional)
    $nuevoAvatar = null;
    if (!$err && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
      if ($_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['avatar'];
        $okSize = $f['size'] <= 2*1024*1024; // 2MB
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $okExt = in_array($ext, ['jpg','jpeg','png']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']); finfo_close($finfo);
        $okMime = in_array($mime, ['image/jpeg','image/png']);
        if (!$okSize || !$okExt || !$okMime) {
          $err = 'Avatar inválido. Usa JPG/PNG (máx. 2MB).';
        } else {
          $dir = __DIR__ . '/uploads/avatars';
          if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
          $safe = preg_replace('/[^a-z0-9\-_\.]/i','_', pathinfo($f['name'], PATHINFO_FILENAME));
          $nombreFinal = $uid . '_' . $safe . '_' . time() . '.' . $ext;
          if (move_uploaded_file($f['tmp_name'], $dir.'/'.$nombreFinal)) {
            $nuevoAvatar = 'uploads/avatars/'.$nombreFinal;
          } else {
            $err = 'No se pudo guardar el avatar.';
          }
        }
      } else {
        $err = 'Error al subir el avatar.';
      }
    }
    if (!$err) {
      // actualizar usuarios
      if ($st = $conexion->prepare("UPDATE usuarios SET nombre=?, correo=? WHERE idusuarios=?")) {
        $st->bind_param('ssi', $nuevoNombre, $nuevoCorreo, $uid);
        $st->execute(); $st->close();
        $_SESSION['usuario'] = $nuevoNombre;  // refrescar saludo
      }
      // upsert de user_prefs (solo avatar aquí si llegó)
      if ($nuevoAvatar !== null) {
        if ($st = $conexion->prepare("INSERT INTO user_prefs (user_id, avatar) VALUES (?,?) ON DUPLICATE KEY UPDATE avatar=VALUES(avatar)")) {
          $st->bind_param('is', $uid, $nuevoAvatar);
          $st->execute(); $st->close();
          $prefs['avatar'] = $nuevoAvatar;
        }
      }
      $msg = 'Perfil actualizado.';
      $perfil['nombre'] = $nuevoNombre; $perfil['correo'] = $nuevoCorreo;
    }
  }
}

if (!$err && is_post() && ($_POST['action'] ?? '') === 'prefs') {
  $idioma   = in_array($_POST['idioma'] ?? 'es', ['es','en'], true) ? $_POST['idioma'] : 'es';
  $tema     = in_array($_POST['tema'] ?? 'sistema', ['sistema','claro','oscuro'], true) ? $_POST['tema'] : 'sistema';
  $fuente   = in_array($_POST['tam_fuente'] ?? 'normal', ['normal','grande'], true) ? $_POST['tam_fuente'] : 'normal';
  $alto     = isset($_POST['alto_contraste']) ? 1 : 0;
  $simple   = isset($_POST['simplificado']) ? 1 : 0;
  $compart  = isset($_POST['compartir_cuidador']) ? 1 : 0;

  if ($st = $conexion->prepare("
    INSERT INTO user_prefs (user_id, idioma, tema, tam_fuente, alto_contraste, simplificado, compartir_cuidador)
    VALUES (?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      idioma=VALUES(idioma),
      tema=VALUES(tema),
      tam_fuente=VALUES(tam_fuente),
      alto_contraste=VALUES(alto_contraste),
      simplificado=VALUES(simplificado),
      compartir_cuidador=VALUES(compartir_cuidador)
  ")) {
    $st->bind_param('isssiii', $uid, $idioma, $tema, $fuente, $alto, $simple, $compart);
    $st->execute(); $st->close();
    $prefs = array_merge($prefs, [
      'idioma'=>$idioma,'tema'=>$tema,'tam_fuente'=>$fuente,
      'alto_contraste'=>$alto,'simplificado'=>$simple,'compartir_cuidador'=>$compart
    ]);
    $msg = 'Preferencias guardadas.';
  } else {
    $err = 'No se pudieron guardar las preferencias.';
  }
}

// --------------------- VISTA ---------------------
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ajustes</title>
  <link rel="stylesheet" href="../css/ajustes.css">
  <link rel="stylesheet" href="../css/ajustes.css">  
  <style>
    /* mini utilidades locales */
    .msg{margin:12px 16px; padding:10px 12px; border-radius:10px; font-size:14px}
    .ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    .avatar{width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid #e5e7eb}
      body{
    display: block !important;
    height: auto !important;
    width: auto !important;
    background: #e8f2f2;       /* mismo fondo suave del resto de módulos */
  }
  /* Evita que reglas del landing afecten el interior */
  .container { all: unset; }
  </style>
</head>
<body>
  <div class="shell">
    <header class="hero">
      <a class="link" href="<?=h($toDash)?>">← Volver</a>
      <h1>Configuración</h1>
      <p class="sub">Visualiza su red de apoyo o su equipo de salud asignado.</p>
    </header>

    <?php if($msg): ?><div class="msg ok"><?=h($msg)?></div><?php endif; ?>
    <?php if($err): ?><div class="msg err"><?=h($err)?></div><?php endif; ?>

    <!-- Sección: Mi Perfil personal -->
    <section class="card">
      <div class="row between">
        <h2>Mi Perfil personal</h2>
      </div>
      <p class="muted">Editar datos básicos · Cambiar foto/avatar</p>

      <form method="post" enctype="multipart/form-data" class="grid">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="perfil">
        <div class="col">
          <label>Nombre</label>
          <input type="text" name="nombre" value="<?=h($perfil['nombre'])?>" required maxlength="80">
        </div>
        <div class="col">
          <label>Correo</label>
          <input type="email" name="correo" value="<?=h($perfil['correo'])?>" required maxlength="120">
        </div>
        <div class="col">
          <label>Avatar (JPG/PNG · máx. 2MB)</label>
          <input type="file" name="avatar" accept=".jpg,.jpeg,.png">
          <?php if(!empty($prefs['avatar'])): ?>
            <div style="margin-top:6px"><img class="avatar" src="<?=h($prefs['avatar'])?>" alt="Avatar actual"></div>
          <?php endif; ?>
        </div>
        <div class="actions">
          <button class="btn" type="submit">Guardar perfil</button>
        </div>
      </form>
    </section>

    <!-- Sección: Notificaciones y recordatorios (solo UI; lógica de notifs podría ir después) -->
    <section class="card">
      <div class="row between">
        <h2>Notificaciones y recordatorios</h2>
      </div>
      <p class="muted">Activar/desactivar alertas (pendiente de backend de notificaciones).</p>
      <div class="grid">
        <div class="col">
          <label><input type="checkbox" checked disabled> Recordatorios de toma de medicamentos</label>
        </div>
        <div class="col">
          <label><input type="checkbox" checked disabled> Avisos de seguimiento</label>
        </div>
      </div>
    </section>

    <!-- Sección: Apariencia y visualización -->
    <section class="card">
      <div class="row between">
        <h2>Apariencia y visualización</h2>
      </div>
      <form method="post" class="grid">
        <input type="hidden" name="csrf" value="<?=$csrf?>">
        <input type="hidden" name="action" value="prefs">

        <div class="col">
          <label>Idioma</label>
          <select name="idioma">
            <option value="es" <?= $prefs['idioma']==='es'?'selected':'' ?>>Español</option>
            <option value="en" <?= $prefs['idioma']==='en'?'selected':'' ?>>English</option>
          </select>
        </div>

        <div class="col">
          <label>Tema</label>
          <select name="tema">
            <option value="sistema" <?= $prefs['tema']==='sistema'?'selected':'' ?>>Seguir sistema</option>
            <option value="claro"   <?= $prefs['tema']==='claro'?'selected':'' ?>>Claro</option>
            <option value="oscuro"  <?= $prefs['tema']==='oscuro'?'selected':'' ?>>Oscuro</option>
          </select>
        </div>

        <div class="col">
          <label>Tamaño de letra</label>
          <select name="tam_fuente">
            <option value="normal" <?= $prefs['tam_fuente']==='normal'?'selected':'' ?>>Normal</option>
            <option value="grande" <?= $prefs['tam_fuente']==='grande'?'selected':'' ?>>Grande</option>
          </select>
        </div>

        <div class="col checkers">
          <label><input type="checkbox" name="alto_contraste" <?= $prefs['alto_contraste']?'checked':'' ?>> Alto contraste</label>
          <label><input type="checkbox" name="simplificado" <?= $prefs['simplificado']?'checked':'' ?>> Interfaz simplificada</label>
        </div>

        <div class="col">
          <label><input type="checkbox" name="compartir_cuidador" <?= $prefs['compartir_cuidador']?'checked':'' ?>> Compartir datos con cuidador</label>
        </div>

        <div class="actions">
          <button class="btn" type="submit">Guardar preferencias</button>
        </div>
      </form>
    </section>

    <!-- Sección: Privacidad y datos -->
    <section class="card">
      <div class="row between">
        <h2>Privacidad y datos</h2>
      </div>
      <p class="muted">Descarga tu historial o elimina la cuenta.</p>
      <div class="row">
        <a class="btn-link" href="../modulos/salud.php?view=export&from=1900-01-01&to=2999-12-31">Descargar historial de síntomas (CSV)</a>
        <!-- Eliminar cuenta: requiere confirmación real y política de negocio -->
        <form method="post" onsubmit="return confirm('¿Seguro que deseas eliminar tu cuenta? Esta acción es irreversible.');">
          <input type="hidden" name="csrf" value="<?=$csrf?>">
          <input type="hidden" name="action" value="delete_account">
          <button class="btn danger" type="submit" disabled title="Demo">Eliminar cuenta (demo)</button>
        </form>
      </div>
    </section>

    <!-- Barra inferior -->
    <footer>
      <nav>
        <button onclick="location.href='<?= $toDash ?>'">
          <img src="../imagenes/logo.png" width="40" alt="Inicio">
        </button>
        <button onclick="location.href='salud.php'"><img src="../imagenes/historial.png" width="30" alt=""></button>
        <button onclick="location.href='ubicacion.php'"><img src="../imagenes/ubicacion.png" width="35" alt=""></button>
        <button onclick="location.href='ayuda.php'"><img src="../imagenes/configuracion.png" width="35" alt=""></button>
      </nav>
    </footer>

  </div>
</body>
</html>
