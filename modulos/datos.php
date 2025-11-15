<?php
// modulos/datos.php — panel de métricas del sistema (ADMIN)
require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/guards.php';
require_once __DIR__ . '/../includes/paths.php';

require_role([3]); // Solo administradores

// CSRF (para acciones como export)
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = $_SESSION['csrf'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_post(){ return $_SERVER['REQUEST_METHOD']==='POST'; }

function table_exists(mysqli $cx, string $t): bool {
  $res = $cx->query("SHOW TABLES LIKE '". $cx->real_escape_string($t) ."'");
  return $res && $res->num_rows > 0;
}

// ----- Filtros de fecha -----
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

// ----- Export CSV (para Excel) -----
if (isset($_GET['action']) && $_GET['action']==='export_csv') {
  if (!hash_equals($csrf, $_GET['csrf'] ?? '')) { http_response_code(400); exit('CSRF'); }
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="reporte_sistema_'.$from.'_a_'.$to.'.csv"');
  $out = fopen('php://output', 'w');

  // Encabezado
  fputcsv($out, ['Métrica','Valor','Rango',$from.' a '.$to]);

  // Conexión global
  global $conexion;

  // Usuarios por rol
  if (table_exists($conexion,'usuarios')) {
    $q = $conexion->query("SELECT idroles, COUNT(*) c FROM usuarios GROUP BY idroles");
    $roles = [1=>'Paciente',2=>'Cuidador',3=>'Admin'];
    $tot = 0;
    while($r=$q->fetch_assoc()){ $tot += (int)$r['c']; fputcsv($out, ['Usuarios '.$roles[(int)$r['idroles']]??'Otro', $r['c']]); }
    fputcsv($out, ['Usuarios (total)', $tot]);
  } else {
    fputcsv($out, ['Usuarios','Tabla no disponible']);
  }

  // Pedidos (si existe tabla pedidos)
  if (table_exists($conexion,'pedidos')) {
    $stmt = $conexion->prepare("SELECT COUNT(*) t FROM pedidos WHERE DATE(creado_en) BETWEEN ? AND ?");
    $stmt->bind_param('ss',$from,$to); $stmt->execute(); $t = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();
    fputcsv($out, ['Pedidos (total)',$t]);
  }

  // Alertas (síntomas >=7)
  if (table_exists($conexion,'sintomas')) {
    $stmt = $conexion->prepare("SELECT COUNT(*) t FROM sintomas WHERE intensidad>=7 AND DATE(fecha) BETWEEN ? AND ?");
    $stmt->bind_param('ss',$from,$to); $stmt->execute(); $t = $stmt->get_result()->fetch_row()[0] ?? 0; $stmt->close();
    fputcsv($out, ['Alertas (intensidad ≥ 7)',$t]);
  }

  fclose($out); exit;
}

// ----- Carga de datos para pantallas -----
$kpi = [
  'usuarios'    => ['total'=>0,'pacientes'=>0,'cuidadores'=>0,'admins'=>0],
  'pedidos'     => ['total'=>0],
  'alertas'     => ['total'=>0],
];

$series_alertas = [];   // [{fecha, total}]
$roles_labels=[]; $roles_data=[]; // para gráfico de roles
$ranking_centros = [];  // [['Centro',porcentaje]]

/* Usuarios (por rol y total) */
if (table_exists($conexion,'usuarios')) {
  $q = $conexion->query("SELECT idroles, COUNT(*) c FROM usuarios GROUP BY idroles");
  while($r=$q->fetch_assoc()){
    $id = (int)$r['idroles']; $c=(int)$r['c'];
    $kpi['usuarios']['total'] += $c;
    if ($id===1) $kpi['usuarios']['pacientes'] = $c;
    elseif ($id===2) $kpi['usuarios']['cuidadores'] = $c;
    elseif ($id===3) $kpi['usuarios']['admins'] = $c;
  }
  // para gráfico
  $roles_labels = ['Pacientes','Cuidadores','Admins'];
  $roles_data   = [$kpi['usuarios']['pacientes'],$kpi['usuarios']['cuidadores'],$kpi['usuarios']['admins']];
}

/* Pedidos (si existe) */
if (table_exists($conexion,'pedidos')) {
  $stmt = $conexion->prepare("SELECT COUNT(*) t FROM pedidos WHERE DATE(creado_en) BETWEEN ? AND ?");
  $stmt->bind_param('ss',$from,$to); $stmt->execute(); $kpi['pedidos']['total'] = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();
}

/* Alertas (sintomas >= 7) */
if (table_exists($conexion,'sintomas')) {
  // KPI total
  $stmt = $conexion->prepare("SELECT COUNT(*) t FROM sintomas WHERE intensidad>=7 AND DATE(fecha) BETWEEN ? AND ?");
  $stmt->bind_param('ss',$from,$to); $stmt->execute(); $kpi['alertas']['total'] = (int)($stmt->get_result()->fetch_row()[0] ?? 0); $stmt->close();

  // Serie diaria
  $stmt = $conexion->prepare("SELECT DATE(fecha) d, COUNT(*) c FROM sintomas WHERE intensidad>=7 AND DATE(fecha) BETWEEN ? AND ? GROUP BY DATE(fecha) ORDER BY d");
  $stmt->bind_param('ss',$from,$to); $stmt->execute(); $res=$stmt->get_result();
  while($r=$res->fetch_assoc()){ $series_alertas[] = [$r['d'], (int)$r['c']]; }
  $stmt->close();
}

/* Ranking centros (si tienes centros_salud) 
   - Sin una tabla de “eventos por centro” usamos un ranking sintético con fecha de creación
*/
if (table_exists($conexion,'centros_salud')) {
  $res = $conexion->query("SELECT nombre, creado_en FROM centros_salud WHERE activo=1 ORDER BY nombre LIMIT 10");
  // Asignamos un “cumplimiento” ficticio (80–98) para demo; si tienes tabla real, reemplaza por tu métrica
  while($r=$res->fetch_assoc()){
    $ranking_centros[] = [$r['nombre'], rand(80,98)];
  }
}

// Datos para JS
$js_alert_labels = array_map(fn($x)=>$x[0], $series_alertas);
$js_alert_data   = array_map(fn($x)=>$x[1], $series_alertas);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Datos del Sistema</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root{ --primary:#05a4a4; --muted:#6b7280; --border:#e5e7eb; --radius:16px; }
    body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#e8f2f2}
    .shell{max-width:420px;margin:0 auto;min-height:100svh;background:#f5f7f8;display:flex;flex-direction:column}
    .header{background:linear-gradient(#e6f6f6,#dff1f1);padding:24px 18px;text-align:center;border-bottom-left-radius:20px;border-bottom-right-radius:20px}
    .header h1{margin:8px 0 4px;font-size:26px}
    .header p{margin:0;color:var(--muted);font-size:14px}
    .top{display:flex;align-items:center;gap:8px;padding:10px 12px}
    .btn-link{display:inline-block;background:#fff;border:1px solid var(--border);padding:8px 12px;border-radius:10px;text-decoration:none;color:inherit}
    .filters{padding:0 12px 12px;display:grid;grid-template-columns:1fr 1fr;gap:8px}
    .filters .row{display:flex;flex-direction:column;gap:6px}
    .filters input{padding:10px 12px;border:1px solid var(--border);border-radius:10px}
    .filters .actions{grid-column:1/-1;display:flex;gap:8px}
    .btn{appearance:none;border:0;background:var(--primary);color:#fff;font-weight:700;padding:10px 12px;border-radius:12px;cursor:pointer}
    .grid{padding:0 12px 12px;display:grid;gap:12px}
    .card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:12px}
    .kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
    .kpi{background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px;text-align:center}
    .kpi .n{font-weight:800;font-size:18px}
    .muted{color:var(--muted);font-size:12px}
    .rank .row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px dashed #eef}
    .rank .row:last-child{border-bottom:0}
    footer nav{display:flex;justify-content:space-around;padding:10px 0 14px;background:linear-gradient(0deg,#dbe7e7,#eaf3f3);border-top-left-radius:20px;border-top-right-radius:20px;margin-top:auto}
    footer button{background:#fff;border:1px solid var(--border);width:56px;height:56px;border-radius:50%;display:grid;place-items:center;box-shadow:0 4px 12px rgba(0,0,0,.05);cursor:pointer}
    @media print { footer, .top, .filters { display:none !important; } .shell{box-shadow:none} body{background:#fff} }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
  <div class="shell">
    <header class="header">
      <h1>Datos del Sistema</h1>
      <p>Control y aprobación de registros.</p>
    </header>

<div class="toolbar">
  <a class="btn-link" href="<?php echo dash_path(); ?>">← Volver</a>
  <a class="btn-link" href="datos.php?from=<?php echo h($from); ?>&to=<?php echo h($to); ?>">Actualizar ↻</a>
</div>

<form class="filters-bar" method="get" action="datos.php">
  <div class="row">
    <label>Desde</label>
    <input type="date" name="from" value="<?php echo h($from); ?>">
  </div>
  <div class="row">
    <label>Hasta</label>
    <input type="date" name="to" value="<?php echo h($to); ?>">
  </div>

  <div class="actions">
    <button class="btn" type="submit">Filtrar</button>
    <a class="btn-link" href="datos.php?action=export_csv&from=<?php echo h($from); ?>&to=<?php echo h($to); ?>&csrf=<?php echo $csrf; ?>">Exportar CSV</a>
    <a class="btn-link" href="#" onclick="window.print();return false;">Generar PDF</a>
  </div>
</form>


    <section class="grid">
      <div class="card kpi-card">
  <div class="kpis">
    <div class="kpi">
      <div class="n"><?= (int)$kpi['usuarios']['total'] ?></div>
      <div class="label">Usuarios</div>
    </div>
    <div class="kpi">
      <div class="n"><?= (int)$kpi['pedidos']['total'] ?></div>
      <div class="label">Pedidos</div>
    </div>
    <div class="kpi">
      <div class="n"><?= (int)$kpi['alertas']['total'] ?></div>
      <div class="label">Alertas</div>
    </div>
  </div>
</div>
  

      <div class="card">
        <div class="chart-box" id="boxRoles">
          <canvas id="chartRoles"></canvas>
        </div>
        <div class="muted">Distribución de usuarios por rol.</div>
        </div>


        <!-- Gráfico: alertas en el tiempo -->
        <div class="card">
          <div class="chart-box" id="boxAlertas">
            <canvas id="chartAlertas"></canvas>
          </div>
          <div class="muted">Alertas (intensidad ≥7) por día.</div>
        </div>
        


      <!-- Ranking de centros (demo/placeholder si no hay métrica real) -->
      <div class="card rank">
        <strong>Ranking de centros según cumplimiento</strong>
        <div class="muted">*Placeholder si no hay tabla de desempeño.</div>
        <div style="margin-top:8px">
          <?php if (!$ranking_centros): ?>
            <div class="muted">No hay centros activos.</div>
          <?php else: foreach($ranking_centros as $i=>$row): ?>
            <div class="row">
              <div><?= ($i+1).". ".h($row[0]) ?></div>
              <div><strong><?= (int)$row[1] ?>%</strong></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </section>

    <footer>
      <nav>
        <button title="Inicio" onclick="location.href='<?= dash_path() ?>'"><img src="../imagenes/logo.png" alt="Inicio" width="40"></button>
        <button title="Ajustes" onclick="location.href='ajustes.php'"><img src="../imagenes/configuracion.png" alt="Ajustes" width="35"></button>
      </nav>
    </footer>
  </div>

  <script>
  // Datos desde PHP
  const rolesLabels = <?= json_encode($roles_labels, JSON_UNESCAPED_UNICODE) ?>;
  const rolesData   = <?= json_encode($roles_data) ?>;
  const alertLabels = <?= json_encode($js_alert_labels, JSON_UNESCAPED_UNICODE) ?>;
  const alertData   = <?= json_encode($js_alert_data) ?>;

  // Ajustes globales: responsive pero respetando la altura del contenedor
  Chart.defaults.responsive = true;
  Chart.defaults.maintainAspectRatio = false;

  let chartRoles, chartAlertas;

  function renderCharts() {
    // Destruir instancias previas si existen (evita bugs de tamaño)
    if (chartRoles) chartRoles.destroy();
    if (chartAlertas) chartAlertas.destroy();

    const ctxR = document.getElementById('chartRoles').getContext('2d');
    chartRoles = new Chart(ctxR, {
      type: 'doughnut',
      data: { labels: rolesLabels, datasets: [{ data: rolesData }] },
      options: {
        plugins: { legend: { position: 'bottom' } }
      }
    });

    const ctxA = document.getElementById('chartAlertas').getContext('2d');
    chartAlertas = new Chart(ctxA, {
      type: 'line',
      data: {
        labels: alertLabels,
        datasets: [{ label: 'Alertas', data: alertData, tension: .25 }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  // Render al cargar y cuando la ventana cambie de tamaño
  window.addEventListener('load', renderCharts);
  window.addEventListener('resize', () => {
    // Chart.js ya reacciona a resize, pero forzamos un re-render si el contenedor cambió mucho
    renderCharts();
  });
</script>

</body>
</html>
