<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';
requerir_admin();

$estadosValidos = ['pendiente', 'en proceso', 'resuelto'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $id      = (int)($_POST['id'] ?? 0);
    $estado  = $_POST['estado'] ?? '';
    $mensaje = trim($_POST['mensaje'] ?? '');

    if ($id > 0 && in_array($estado, $estadosValidos, true)) {
        $up = $conexion->prepare("UPDATE reportes SET estado = ? WHERE id = ?");
        $up->bind_param('si', $estado, $id);
        $up->execute();

        if ($mensaje !== '') {
            $adminId = (int)$_SESSION['usuario']['id'];
            $ins = $conexion->prepare("INSERT INTO respuestas (reporte_id, admin_id, mensaje) VALUES (?, ?, ?)");
            $ins->bind_param('iis', $id, $adminId, $mensaje);
            $ins->execute();
        }
    }
    header('Location: admin_reportes.php');
    exit;
}

$reportes = $conexion->query("SELECT * FROM reportes ORDER BY fecha DESC");
$evStmt   = $conexion->prepare("SELECT archivo, tipo FROM evidencias WHERE reporte_id = ?");
$resStmt  = $conexion->prepare("SELECT mensaje, fecha FROM respuestas WHERE reporte_id = ? ORDER BY fecha ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel de reportes · Denuncia Digital</title>
  <link rel="stylesheet" href="../assets_css/style_admin.css">
  <link rel="stylesheet" href="../assets_css/style_logout.css">
</head>
<body>
  <main class="panel">
    <nav class="navegacion">
      <a href="admin_reportes.php" class="activo">Reportes</a>
      <a href="admin_usuarios.php">Usuarios</a>
      <a href="../dashboard/dashboard.html">Panel de control</a>
      <a href="../auth/logout.php" class="salir">Cerrar sesión</a>
    </nav>

    <h1>Reportes recibidos</h1>

    <?php if ($reportes->num_rows === 0): ?>
      <p>Aún no hay denuncias.</p>
    <?php endif; ?>

    <?php while ($r = $reportes->fetch_assoc()): ?>
      <article class="tarjeta">
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
              <video src="uploads/<?= e($ev['archivo']) ?>" controls></video>
            <?php else: ?>
              <a href="uploads/<?= e($ev['archivo']) ?>" target="_blank">
                <img src="uploads/<?= e($ev['archivo']) ?>" alt="Evidencia">
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
              <?php foreach ($estadosValidos as $est): ?>
                <option value="<?= $est ?>" <?= $r['estado'] === $est ? 'selected' : '' ?>><?= $est ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Guardar</button>
          </div>
        </form>
      </article>
    <?php endwhile; ?>
  </main>
  <script src="../assets_js/script_logout.js"></script>
</body>
</html>