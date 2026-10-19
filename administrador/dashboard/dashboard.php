<?php
$aut = __DIR__ . '/../../incluides/autenticacion.php';
if (!is_file($aut)) { $aut = __DIR__ . '/../../includes/auth.php'; }
require $aut;
require __DIR__ . '/../../incluides/conexion.php';

date_default_timezone_set('America/La_Paz');

/* Ajusta estas rutas a tus carpetas reales */
const BASE          = '/PROYECTO_PAGE';
const LINK_REPORTES = BASE . '/administrador/admin_reportes.php';
const LINK_USUARIOS = BASE . '/administrador/admin_usuarios.php';
const LINK_INICIO   = BASE . '/index.php';
const LINK_LOGOUT   = BASE . '/auth/logout.php';

/* =====================================================================
   1) DATOS EN VIVO  →  dashboard.php?datos=1  (devuelve JSON)
   ===================================================================== */
if (isset($_GET['datos'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (empty($_SESSION['usuario']['id']) || !es_admin()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
        exit;
    }
    session_write_close();

    $db = $conexion ?? $conn ?? null;
    if (!($db instanceof mysqli)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'mensaje' => 'Sin conexión a la base de datos']);
        exit;
    }

    $TIPOS = [
        'seguridad' => 'Seguridad', 'alumbrado' => 'Alumbrado', 'basura' => 'Basura',
        'ruidos' => 'Ruidos', 'servicios_basicos' => 'Servicios básicos',
        'vias_transporte' => 'Vías y transporte',
    ];
    $etiqueta = fn($t) => $TIPOS[$t] ?? ucfirst(str_replace('_', ' ', $t));

    $hace = function (string $fecha): string {
        $s = max(0, time() - strtotime($fecha));
        if ($s < 60)    return 'Hace un momento';
        if ($s < 3600)  return 'Hace ' . floor($s / 60) . ' min';
        if ($s < 86400) return 'Hace ' . floor($s / 3600) . ' h';
        return 'Hace ' . floor($s / 86400) . ' días';
    };

    // Estados y totales
    $estados = ['pendiente' => 0, 'en proceso' => 0, 'resuelto' => 0];
    $r = $db->query("SELECT estado, COUNT(*) FROM reportes GROUP BY estado");
    while ($f = $r->fetch_row()) {
        if (isset($estados[$f[0]])) $estados[$f[0]] = (int)$f[1];
    }
    $total    = array_sum($estados);
    $usuarios = (int)$db->query("SELECT COUNT(*) FROM usuario WHERE rol = 'usuario'")->fetch_row()[0];
    $hoy      = (int)$db->query("SELECT COUNT(*) FROM reportes WHERE DATE(fecha) = CURDATE()")->fetch_row()[0];

    // Últimos 7 días
    $porDia = [];
    $r = $db->query("SELECT DATE(fecha), COUNT(*) FROM reportes
                     WHERE fecha >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(fecha)");
    while ($f = $r->fetch_row()) $porDia[$f[0]] = (int)$f[1];

    $nombresDia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $etiquetas = $valores = [];
    for ($i = 6; $i >= 0; $i--) {
        $ts = strtotime("-$i day");
        $etiquetas[] = $nombresDia[(int)date('w', $ts)];
        $valores[]   = $porDia[date('Y-m-d', $ts)] ?? 0;
    }

    // Por tipo
    $tipos = [];
    $r = $db->query("SELECT tipo, COUNT(*) c FROM reportes GROUP BY tipo ORDER BY c DESC");
    while ($f = $r->fetch_row()) $tipos[] = ['nombre' => $etiqueta($f[0]), 'valor' => (int)$f[1]];

    // Actividad reciente (denuncias + respuestas)
    $act = [];
    $r = $db->query("SELECT tipo, fecha FROM reportes ORDER BY fecha DESC LIMIT 6");
    while ($f = $r->fetch_row()) $act[] = ['texto' => 'Nueva denuncia de ' . $etiqueta($f[0]), 'fecha' => $f[1]];
    $r = $db->query("SELECT reporte_id, fecha FROM respuestas ORDER BY fecha DESC LIMIT 6");
    while ($f = $r->fetch_row()) $act[] = ['texto' => 'Respuesta enviada en la denuncia #' . $f[0], 'fecha' => $f[1]];
    usort($act, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
    $actividad = array_map(fn($a) => ['texto' => $a['texto'], 'cuando' => $hace($a['fecha'])], array_slice($act, 0, 6));

    // Últimas denuncias
    $recientes = [];
    $r = $db->query("SELECT id, tipo, direccion, fecha, estado FROM reportes ORDER BY fecha DESC LIMIT 8");
    while ($f = $r->fetch_assoc()) {
        $recientes[] = [
            'id'     => (int)$f['id'],
            'tipo'   => $etiqueta($f['tipo']),
            'lugar'  => $f['direccion'],
            'fecha'  => date('d/m/Y', strtotime($f['fecha'])),
            'estado' => $f['estado'] === 'en proceso' ? 'proceso' : $f['estado'],
        ];
    }

    echo json_encode([
        'ok'   => true,
        'kpis' => ['total' => $total, 'pendientes' => $estados['pendiente'],
                   'resueltas' => $estados['resuelto'], 'usuarios' => $usuarios, 'hoy' => $hoy],
        'semana'  => ['etiquetas' => $etiquetas, 'valores' => $valores],
        'estados' => [
            ['nombre' => 'Resueltas',  'valor' => $estados['resuelto'],   'color' => '#34d399'],
            ['nombre' => 'En proceso', 'valor' => $estados['en proceso'], 'color' => '#fbbf24'],
            ['nombre' => 'Pendientes', 'valor' => $estados['pendiente'],  'color' => '#f87171'],
        ],
        'tipos' => $tipos, 'actividad' => $actividad, 'recientes' => $recientes,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =====================================================================
   2) PÁGINA (solo administradores)
   ===================================================================== */
requerir_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · DenunciaDigital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE ?>/assets_css/style_dashboard.css">
  <link rel="stylesheet" href="<?= BASE ?>/assets_css/style_logout.css">
</head>
<body>
  <div class="app" id="app">

    <!-- ============ BARRA LATERAL ============ -->
    <aside class="sidebar" id="sidebar">
      <div class="brand">
        <span class="brand-mark">DD</span>
        <span class="brand-name">Denuncia<em>Digital</em></span>
      </div>

      <nav class="menu" aria-label="Menú principal">
        <p class="menu-title">Principal</p>

        <a class="menu-link active" href="#resumen">
          <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
          <span>Resumen</span>
        </a>
        <a class="menu-link" href="<?= LINK_REPORTES ?>">
          <svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
          <span>Denuncias</span>
          <b class="pill" id="pillPend">0</b>
        </a>
        <a class="menu-link" href="<?= LINK_USUARIOS ?>">
          <svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3.2-5.5 6.5-5.5s5.9 2 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.7c1.9.6 3.1 2.4 3.5 5.3"/></svg>
          <span>Usuarios</span>
        </a>

        <p class="menu-title">Análisis</p>

        <a class="menu-link" href="#estadisticas">
          <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/></svg>
          <span>Estadísticas</span>
        </a>
        <a class="menu-link" href="#recientes">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <span>Recientes</span>
        </a>

        <p class="menu-title">Cuenta</p>

        <a class="menu-link" href="<?= LINK_INICIO ?>">
          <svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>
          <span>Ir al sitio</span>
        </a>
        <a class="menu-link danger" href="<?= LINK_LOGOUT ?>">
          <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
          <span>Cerrar sesión</span>
        </a>
      </nav>

      <div class="side-card">
        <div class="radar-mini"><i></i><i></i></div>
        <h4>Zona El Kenko</h4>
        <p>Monitoreo activo de denuncias en tiempo real.</p>
      </div>
    </aside>

    <div class="main">

      <!-- ============ ENCABEZADO ============ -->
      <header class="topbar" id="topbar">
        <button class="icon-btn burger" id="menuBtn" aria-label="Abrir menú" aria-expanded="false">
          <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>

        <div class="page-title">
          <h1>Panel de control</h1>
          <small id="fecha">—</small>
        </div>

        <label class="search" aria-label="Buscar denuncias">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
          <input type="search" id="buscador" placeholder="Buscar denuncia, tipo o zona..." autocomplete="off">
        </label>

        <div class="top-actions">
          <button class="icon-btn" id="notifBtn" aria-label="Notificaciones">
            <svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>
            <span class="dot" id="notifDot"></span>
          </button>

          <div class="user">
            <span class="avatar">AD</span>
            <span class="user-info">
              <strong>Administrador</strong>
              <small>DenunciaDigital</small>
            </span>
          </div>
        </div>
      </header>

      <!-- ============ CUERPO ============ -->
      <main class="content" id="content">

        <section class="hello anim" style="--i:0" id="resumen">
          <div>
            <h2 id="saludo">Bienvenido</h2>
            <p>Este es el resumen de las denuncias de tu comunidad.</p>
          </div>
          <a href="<?= LINK_REPORTES ?>" class="btn">Gestionar denuncias</a>
        </section>

        <section class="kpis">
          <article class="kpi anim" style="--i:1">
            <div class="kpi-ico"><svg viewBox="0 0 24 24"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg></div>
            <p>Total de denuncias</p>
            <strong id="kTotal">0</strong>
            <span class="trend up" id="kHoy">Hoy: 0</span>
          </article>

          <article class="kpi anim" style="--i:2">
            <div class="kpi-ico warn"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>
            <p>Pendientes</p>
            <strong id="kPend">0</strong>
            <span class="trend down">Requieren atención</span>
          </article>

          <article class="kpi anim" style="--i:3">
            <div class="kpi-ico ok"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/></svg></div>
            <p>Resueltas</p>
            <strong id="kRes">0</strong>
            <span class="trend up" id="kPct">0% del total</span>
          </article>

          <article class="kpi anim" style="--i:4">
            <div class="kpi-ico"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.5 3.2-5.5 6.5-5.5s5.9 2 6.5 5.5"/></svg></div>
            <p>Usuarios registrados</p>
            <strong id="kUsu">0</strong>
            <span class="trend up">Cuentas de ciudadanos</span>
          </article>
        </section>

        <section class="grid-2" id="estadisticas">
          <article class="panel anim" style="--i:5">
            <header class="panel-head">
              <h3>Denuncias por semana</h3>
              <span class="tag">Últimos 7 días</span>
            </header>
            <div class="line-wrap">
              <svg id="lineChart" viewBox="0 0 600 220" preserveAspectRatio="none" role="img" aria-label="Gráfico de denuncias por semana"></svg>
              <div class="line-labels" id="lineLabels"></div>
            </div>
          </article>

          <article class="panel anim" style="--i:6">
            <header class="panel-head"><h3>Por estado</h3></header>
            <div class="donut-box">
              <div class="donut">
                <svg viewBox="0 0 180 180" id="donut" role="img" aria-label="Gráfico por estado"></svg>
                <div class="donut-center">
                  <strong id="donutTotal">0</strong>
                  <small>denuncias</small>
                </div>
              </div>
              <ul class="legend" id="donutLegend"></ul>
            </div>
          </article>
        </section>

        <section class="grid-2">
          <article class="panel anim" style="--i:7">
            <header class="panel-head"><h3>Denuncias por tipo</h3></header>
            <div class="bars" id="barChart"></div>
          </article>

          <article class="panel anim" style="--i:8">
            <header class="panel-head"><h3>Actividad reciente</h3></header>
            <ul class="timeline" id="activity"></ul>
          </article>
        </section>

        <section class="panel anim" style="--i:9" id="recientes">
          <header class="panel-head">
            <h3>Últimas denuncias</h3>
            <a href="<?= LINK_REPORTES ?>" class="link">Ver todas →</a>
          </header>
          <div class="table-scroll">
            <table>
              <thead>
                <tr><th>#</th><th>Tipo</th><th>Ubicación</th><th>Fecha</th><th>Estado</th></tr>
              </thead>
              <tbody id="tablaBody"></tbody>
            </table>
            <p class="empty" id="vacio" hidden>Aún no hay denuncias registradas.</p>
          </div>
        </section>

        <footer class="foot">© <span id="anio"></span> DenunciaDigital · Hecho para la comunidad</footer>
      </main>
    </div>

    <div class="overlay" id="overlay"></div>
  </div>

  <script src="<?= BASE ?>/assets_js/script_dashboard.js"></script>
  <script src="<?= BASE ?>/assets_js/script_logout.js"></script>
</body>
</html>