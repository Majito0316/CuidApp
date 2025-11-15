<?php
// includes/paths.php
require_once __DIR__ . '/session.php';

if (!function_exists('dash_path')) {
  function dash_path(): string {
    // Ajusta a tus nombres REALES de archivos:
    $map = [
      1 => 'dashboards/dashboardPaciente.php',
      2 => 'dashboards/dashboardCuidador.php',
      3 => 'dashboards/dashboardAdmin.php',
    ];

    $target = $map[rol() ?? 0] ?? 'login.php';

    // Detecta desde dónde se llama para prefijar correctamente
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $prefix = '';
    if (strpos($script, '/modulos/') !== false) {
      $prefix = '../';
    } elseif (strpos($script, '/dashboards/') !== false) {
      $prefix = './';
    } else {
      $prefix = '';
    }
    return $prefix . $target;
  }
}

if (!function_exists('logout_url')) {
  function logout_url(string $prefix = ''): string {
    $pref = rtrim($prefix, '/');
    return ($pref ? $pref.'/' : '') . 'salir.php?csrf=' . rawurlencode(csrf_token());
  }
}
