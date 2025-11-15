<?php
// salir.php
require_once __DIR__.'/includes/session.php';

// Verificación CSRF (POST preferido; GET con token permitido si lo usas como enlace)
$token = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
$metodoValido = (
  ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals(csrf_token(), (string)$token)) ||
  ($_SERVER['REQUEST_METHOD'] === 'GET'  && hash_equals(csrf_token(), (string)$token))
);

if (!$metodoValido) {
  http_response_code(400);
  echo 'Solicitud inválida.';
  exit;
}

// Limpia la sesión por completo
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirige al login con un flash param
header('Location: login.php?out=1');
exit;
