<?php
// actions/registro_procesar.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/session.php';

if (method_exists($conexion, 'set_charset')) {
  $conexion->set_charset('utf8mb4');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../login.php');
  exit;
}

$nombre      = trim($_POST['nombre'] ?? '');
$correo      = mb_strtolower(trim($_POST['correo'] ?? ''));
$rol         = trim($_POST['rol'] ?? '');
$contrasena  = $_POST['contraseña'] ?? '';
$confirmar   = $_POST['confirmar'] ?? '';

// Validaciones mínimas
if ($nombre === '' || $correo === '' || $rol === '' || $contrasena === '' || $confirmar === '') {
  header('Location: ../login.php?tab=register&err=empty');
  exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
  header('Location: ../login.php?tab=register&err=email');
  exit;
}
if (!in_array($rol, ['paciente','cuidador','admin'], true)) {
  header('Location: ../login.php?tab=register&err=role');
  exit;
}
if ($contrasena !== $confirmar) {
  header('Location: ../login.php?tab=register&err=nomatch');
  exit;
}

// (Opcional) Validación del nombre en servidor
if (!preg_match("/^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+(?:[ '\-][A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)*$/u", $nombre)) {
  header('Location: ../login.php?tab=register&err=badname');
  exit;
}

// Mapear rol -> idroles
$idRol = 1;
if ($rol === 'cuidador') $idRol = 2;
if ($rol === 'admin')    $idRol = 3;

// ¿Correo ya existe?
$sqlCheck = "SELECT idusuarios FROM usuarios WHERE correo = ?";
if (!$stmt = $conexion->prepare($sqlCheck)) {
  header('Location: ../login.php?tab=register&err=server');
  exit;
}
$stmt->bind_param('s', $correo);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
  $stmt->close();
  header('Location: ../login.php?tab=register&err=dupe');
  exit;
}
$stmt->close();

// Hash y guardar (columna `contraseña` con backticks por la ñ)
$hash = password_hash($contrasena, PASSWORD_DEFAULT);

$sql = "INSERT INTO usuarios (nombre, correo, `contraseña`, idroles)
        VALUES (?, ?, ?, ?)";
if (!$stmt = $conexion->prepare($sql)) {
  header('Location: ../login.php?tab=register&err=server');
  exit;
}
$stmt->bind_param('sssi', $nombre, $correo, $hash, $idRol);

if ($stmt->execute()) {
  $stmt->close();
  // Registro ok -> vuelve a login con flag de éxito
  header('Location: ../login.php?ok=registered');
  exit;
} else {
  $stmt->close();
  header('Location: ../login.php?tab=register&err=save');
  exit;
}