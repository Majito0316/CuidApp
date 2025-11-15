<?php
// actions/login_procesar.php
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/session.php';

// Seguridad de charset
if (method_exists($conexion, 'set_charset')) {
  $conexion->set_charset('utf8mb4');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../login.php');
  exit;
}

$correo     = mb_strtolower(trim($_POST['correo'] ?? ''));
$contrasena = $_POST['contraseña'] ?? '';

if ($correo === '' || $contrasena === '') {
  header('Location: ../login.php?err=empty');
  exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
  header('Location: ../login.php?err=email');
  exit;
}

// Buscar usuario por correo
$sql = "SELECT idusuarios, nombre, correo, `contraseña`, idroles
        FROM usuarios
        WHERE correo = ?";
if (!$stmt = $conexion->prepare($sql)) {
  header('Location: ../login.php?err=server');
  exit;
}
$stmt->bind_param('s', $correo);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
  $stmt->close();
  header('Location: ../login.php?err=nouser');
  exit;
}

$usuario = $res->fetch_assoc();
$stmt->close();

// Verificar contraseña
if (!password_verify($contrasena, $usuario['contraseña'])) {
  header('Location: ../login.php?err=badpass');
  exit;
}

// Éxito: crear sesión y redirigir por rol
session_regenerate_id(true);
$_SESSION['uid']     = (int)$usuario['idusuarios'];
$_SESSION['usuario'] = $usuario['nombre'];
$_SESSION['rol']     = (int)$usuario['idroles'];

// Destinos por rol
$destinos = [
  1 => '../dashboards/dashboardPaciente.php',
  2 => '../dashboards/dashboardCuidador.php',
  3 => '../dashboards/dashboardAdmin.php',
];

$destino = $destinos[$_SESSION['rol']] ?? '../dashboards/dashboardPaciente.php';
header("Location: $destino");
exit;