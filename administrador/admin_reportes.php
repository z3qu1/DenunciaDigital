<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';
requerir_admin();

$estadosValidos = ['pendiente', 'en proceso', 'resuelto'];

/* ===================== RESPONDER / CAMBIAR ESTADO ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id      = (int)($_POST['id'] ?? 0);
    $estado  = $_POST['estado'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');

    if ($id > 0 && in_array($estado, $estadosValidos, true)) {
        $q = $conexion->prepare("SELECT estado, usuario_id FROM reportes WHERE id = ?");
        $q->bind_param('i', $id);
        $q->execute();
        $rep = $q->get_result()->fetch_assoc();

        if ($rep) {
            $up = $conexion->prepare("UPDATE reportes SET estado = ? WHERE id = ?");
            $up->bind_param('si', $estado, $id);
            $up->execute();

            $avisos = [];
            if ($rep['estado'] !== $estado) {
                $avisos[] = "Tu denuncia #$id cambió a: $estado";
            }
            if ($mensaje !== '') {
                $adminId = (int)$_SESSION['usuario']['id'];
                $ins = $conexion->prepare("INSERT INTO respuestas (reporte_id, admin_id, mensaje) VALUES (?, ?, ?)");
                $ins->bind_param('iis', $id, $adminId, $mensaje);
                $ins->execute();
                $avisos[] = "El administrador respondió tu denuncia #$id";
            }
            if (!empty($rep['usuario_id'])) {
                $uid = (int)$rep['usuario_id'];
                $nt = $conexion->prepare("INSERT INTO notificaciones (usuario_id, reporte_id, mensaje) VALUES (?, ?, ?)");
                foreach ($avisos as $texto) {
                    $nt->bind_param('iis', $uid, $id, $texto);
                    $nt->execute();
                }
            }
        }
    }
    // Vuelve a la misma vista (con los mismos filtros) y a la misma denuncia
    header('Location: ' . $_SERVER['REQUEST_URI'] . '#r' . $id);
    exit;
}

/* ===================== FILTROS ===================== */
$vista   = in_array($_GET['vista'] ?? '', ['usuario', 'dia', 'semana'], true) ? $_GET['vista'] : 'usuario';
$periodo = in_array($_GET['periodo'] ?? '', ['hoy', 'semana', 'mes'], true) ? $_GET['periodo'] : 'todos';
$fEstado = in_array($_GET['estado'] ?? '', $estadosValidos, true) ? $_GET['estado'] : '';
$busca   = trim($_GET['q'] ?? '');

$titulos = [
    ''           => 'Reportes recibidos',
    'pendiente'  => 'Denuncias pendientes',
    'en proceso' => 'Denuncias en proceso',
    'resuelto'   => 'Denuncias resueltas',
];
$tituloPagina = $titulos[$fEstado] ?? 'Reportes recibidos';

$where = []; $tipos = ''; $params = [];

if ($periodo === 'hoy')    $where[] = "fecha >= CURDATE()";
if ($periodo === 'semana') $where[] = "fecha >= CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY";
if ($periodo === 'mes')    $where[] = "fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
if ($fEstado !== '') { $where[] = "estado = ?"; $tipos .= 's'; $params[] = $fEstado; }
if ($busca !== '')   { $where[] = "nombre LIKE ?"; $tipos .= 's'; $params[] = '%' . $busca . '%'; }

$sql = "SELECT * FROM reportes" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY fecha DESC";
$st = $conexion->prepare($sql);
if ($params) $st->bind_param($tipos, ...$params);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);

/* Totales reales por usuario (ignoran el filtro de estado) */
$totUsuario = [];
$rt = $conexion->query("SELECT usuario_id, estado, COUNT(*) c FROM reportes WHERE usuario_id IS NOT NULL GROUP BY usuario_id, estado");
while ($t = $rt->fetch_assoc()) {
    $totUsuario[(int)$t['usuario_id']][$t['estado']] = (int)$t['c'];
}

/* ===================== AGRUPAR ===================== */
$grupos = [];
foreach ($filas as $r) {
    $ts = strtotime($r['fecha']);
    if ($vista === 'usuario') {
        $clave       = $r['usuario_id'] ? 'u' . $r['usuario_id'] : 'n' . $r['nombre'];
        $nombreGrupo = $r['nombre'];
    } elseif ($vista === 'dia') {
        $clave       = date('Y-m-d', $ts);
        $nombreGrupo = date('d/m/Y', $ts);
    } else {
        $lunes       = strtotime('monday this week', $ts);
        $clave       = date('Y-m-d', $lunes);
        $nombreGrupo = 'Semana del ' . date('d/m', $lunes) . ' al ' . date('d/m/Y', strtotime('+6 day', $lunes));
    }
    if (!isset($grupos[$clave])) {
        $grupos[$clave] = [
            'titulo' => $nombreGrupo,
            'items'  => [],
            'ultima' => $r['fecha'],
            'uid'    => $r['usuario_id'],
            'est'    => ['pendiente' => 0, 'en proceso' => 0, 'resuelto' => 0],
        ];
    }
    $grupos[$clave]['items'][] = $r;
    if (isset($grupos[$clave]['est'][$r['estado']])) $grupos[$clave]['est'][$r['estado']]++;
}

/* Detalle de cada denuncia */
$evStmt  = $conexion->prepare("SELECT archivo, tipo FROM evidencias WHERE reporte_id = ?");
$resStmt = $conexion->prepare("SELECT mensaje, fecha FROM respuestas WHERE reporte_id = ? ORDER BY fecha ASC");

/* Construye enlaces conservando los filtros */
function url(array $cambios): string {
    $q = array_merge($_GET, $cambios);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de reportes · Denuncia Digital</title>
  <link rel="stylesheet" href="/PROYECTO_PAGE/assets_css/style_admin.css">
  <link rel="stylesheet" href="/PROYECTO_PAGE/assets_css/style_logout.css">
</head>
<body>
  <main class="panel ancho">
    <nav class="navegacion">
      <a href="admin_reportes.php" class="activo">Reportes</a>
      <a href="admin_usuarios.php">Usuarios</a>
      <a href="dashboard/dashboard.php">Panel de control</a>
      <a href="/PROYECTO_PAGE/auth/logout.php" class="salir">Cerrar sesión</a>
    </nav>

    <h1><?= e($tituloPagina) ?></h1>

    <!-- ============ FILTROS ============ -->
    <section class="filtros">
      <div class="chips">
        <span>Agrupar por:</span>
        <a class="chip <?= $vista === 'usuario' ? 'on' : '' ?>" href="<?= e(url(['vista' => 'usuario'])) ?>">Usuario</a>
        <a class="chip <?= $vista === 'dia' ? 'on' : '' ?>"     href="<?= e(url(['vista' => 'dia'])) ?>">Día</a>
        <a class="chip <?= $vista === 'semana' ? 'on' : '' ?>"  href="<?= e(url(['vista' => 'semana'])) ?>">Semana</a>
      </div>

      <div class="chips">
        <span>Periodo:</span>
        <a class="chip <?= $periodo === 'todos' ? 'on' : '' ?>"  href="<?= e(url(['periodo' => ''])) ?>">Todos</a>
        <a class="chip <?= $periodo === 'hoy' ? 'on' : '' ?>"    href="<?= e(url(['periodo' => 'hoy'])) ?>">Hoy</a>
        <a class="chip <?= $periodo === 'semana' ? 'on' : '' ?>" href="<?= e(url(['periodo' => 'semana'])) ?>">Esta semana</a>
        <a class="chip <?= $periodo === 'mes' ? 'on' : '' ?>"    href="<?= e(url(['periodo' => 'mes'])) ?>">Este mes</a>
      </div>

      <form method="get" class="buscar">
        <input type="hidden" name="vista" value="<?= e($vista) ?>">
        <input type="hidden" name="periodo" value="<?= e($periodo === 'todos' ? '' : $periodo) ?>">
        <select name="estado" onchange="this.form.submit()">
          <option value="">Todos los estados</option>
          <?php foreach ($estadosValidos as $es): ?>
            <option value="<?= e($es) ?>" <?= $fEstado === $es ? 'selected' : '' ?>><?= e($es) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nombre...">
        <button type="submit">Buscar</button>
      </form>

      <p class="resumen"><?= count($filas) ?> denuncia(s) en <?= count($grupos) ?> grupo(s)</p>
    </section>

    <?php if (!$grupos): ?>
      <p class="vacio-msg">No hay denuncias con esos filtros.</p>
    <?php endif; ?>

    <!-- ============ GRUPOS ============ -->
    <?php foreach ($grupos as $g): ?>
      <details class="grupo">
        <summary>
          <span class="g-nombre"><?= e($g['titulo']) ?></span>
          <span class="g-info">
            <b class="g-total"><?= count($g['items']) ?> denuncia<?= count($g['items']) === 1 ? '' : 's' ?></b>
            <?php if ($g['est']['pendiente']): ?><b class="g-pend"><?= $g['est']['pendiente'] ?> pendiente<?= $g['est']['pendiente'] === 1 ? '' : 's' ?></b><?php endif; ?>
            <?php if ($g['est']['en proceso']): ?><b class="g-proc"><?= $g['est']['en proceso'] ?> en proceso</b><?php endif; ?>
            <?php if ($g['est']['resuelto']): ?><b class="g-res"><?= $g['est']['resuelto'] ?> resuelta<?= $g['est']['resuelto'] === 1 ? '' : 's' ?></b><?php endif; ?>
            <?php if ($vista === 'usuario' && $g['uid']): ?>
              <?php $t = $totUsuario[(int)$g['uid']] ?? []; ?>
              <small>Total del usuario: <?= array_sum($t) ?> (<?= $t['pendiente'] ?? 0 ?> pend. · <?= $t['en proceso'] ?? 0 ?> proc. · <?= $t['resuelto'] ?? 0 ?> res.)</small>
            <?php endif; ?>
            <small>Última: <?= e(date('d/m/Y H:i', strtotime($g['ultima']))) ?></small>
          </span>
        </summary>

        <div class="grupo-cuerpo">
          <?php foreach ($g['items'] as $r): ?>
            <article class="tarjeta" id="r<?= (int)$r['id'] ?>">
              <header>
                <span class="tipo"><?= e(str_replace('_', ' ', $r['tipo'])) ?></span>
                <span class="estado <?= e(str_replace(' ', '-', $r['estado'])) ?>"><?= e($r['estado']) ?></span>
              </header>

              <p class="meta">#<?= (int)$r['id'] ?> · <?= e($r['nombre']) ?> · <?= e($r['fecha']) ?></p>
              <p><strong>Ubicación:</strong> <?= e($r['direccion']) ?></p>
              <p><?= nl2br(e($r['descripcion'])) ?></p>

              <?php
                $evStmt->bind_param('i', $r['id']);
                $evStmt->execute();
                $evidencias = $evStmt->get_result();
              ?>
              <div class="evidencias">
                <?php while ($ev = $evidencias->fetch_assoc()): ?>
                  <?php if ($ev['tipo'] === 'video'): ?>
                    <video src="/PROYECTO_PAGE/uploads/<?= e($ev['archivo']) ?>" controls></video>
                  <?php else: ?>
                    <a href="/PROYECTO_PAGE/uploads/<?= e($ev['archivo']) ?>" target="_blank">
                      <img src="/PROYECTO_PAGE/uploads/<?= e($ev['archivo']) ?>" alt="Evidencia">
                    </a>
                  <?php endif; ?>
                <?php endwhile; ?>
              </div>

              <?php
                $resStmt->bind_param('i', $r['id']);
                $resStmt->execute();
                $respuestas = $resStmt->get_result();
              ?>
              <?php while ($resp = $respuestas->fetch_assoc()): ?>
                <div class="respuesta">
                  <small><?= e($resp['fecha']) ?> · Respuesta del administrador</small>
                  <p><?= nl2br(e($resp['mensaje'])) ?></p>
                </div>
              <?php endwhile; ?>

              <form method="post" class="responder">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <textarea name="mensaje" rows="3" placeholder="Escribe una respuesta para el usuario (opcional)"></textarea>
                <div class="fila">
                  <select name="estado">
                    <?php foreach ($estadosValidos as $es): ?>
                      <option value="<?= e($es) ?>" <?= $r['estado'] === $es ? 'selected' : '' ?>><?= e($es) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit">Guardar</button>
                </div>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </main>

  <script>
  // Si la URL trae #r12, abre el grupo que contiene esa denuncia y baja hasta ella
  (function () {
    var destino = location.hash ? document.querySelector(location.hash) : null;
    if (destino) {
      var grupo = destino.closest('details');
      if (grupo) grupo.open = true;
      destino.scrollIntoView({ block: 'center' });
    } else {
      var primero = document.querySelector('details.grupo');
      if (primero) primero.open = true;   // abre el primero para que no se vea vacío
    }
  })();
  </script>
  <script src="/PROYECTO_PAGE/assets_js/script_logout.js"></script>
</body>
</html>