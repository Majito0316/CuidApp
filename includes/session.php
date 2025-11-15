<?php
// includes/session.php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string {
  return $_SESSION['csrf'] ?? '';
}

function uid(): ?int {
  return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
}

function user(): string {
  return $_SESSION['usuario'] ?? '';
}

function rol(): ?int {
  return isset($_SESSION['rol']) ? (int)$_SESSION['rol'] : null;
}
