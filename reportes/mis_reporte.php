<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';
requerir_login();

// Detecta la variable de conexión (funciona con $conexion o $conn)
$conexion = $conexion ?? $conn ?? $mysqli ?? $con ?? $db ?? $cn ?? null;

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Revisa conexion.php (debe usar mysqli).');
}
$conn->set_charset('utf8mb4');

$miId = (int)$_SESSION['usuario']['id'];

$st = $conn->prepare("SELECT id, tipo, descripcion, direccion, estado, fecha FROM reportes WHERE usuario_id = ? ORDER BY fecha DESC");
$st->bind_param('i', $miId);
$st->execute();
$reportes = $st->get_result();

$resStmt = $conn->prepare("SELECT mensaje, fecha FROM respuestas WHERE reporte_id = ? ORDER BY fecha ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis denuncias · Denuncia Digital</title>
  <link rel="stylesheet" href="../assets_css/style_admin.css">
  <link rel="stylesheet" href="../assets_css/style_logout.css">
  <link rel="stylesheet" href="../assets_css/style_notificaciones.css">
</head>
<body>
  <main class="panel">
    <nav class="navegacion">
      <a href="../index.php">Inicio</a>
      <a href="reporte.php">Nueva denuncia</a>
      <a href="mis_reporte.php" class="activo">Mis denuncias</a>
      <span id="campana"></span>
      <a href="logout.php" class="salir">Cerrar sesión</a>
    </nav>

    <h1>Mis denuncias</h1>

    <?php if ($reportes->num_rows === 0): ?>
      <p>Todavía no has enviado denuncias.</p>
    <?php endif; ?>

    <?php while ($r = $reportes->fetch_assoc()): ?>
      <article class="tarjeta">
        <header>
          <span class="tipo"><?= e(str_replace('_', ' ', $r['tipo'])) ?></span>
          <span class="estado <?= e(str_replace(' ', '-', $r['estado'])) ?>"><?= e($r['estado']) ?></span>
        </header>

        <p class="meta">#<?= (int)$r['id'] ?> · <?= e($r['fecha']) ?></p>
        <p><strong>Ubicación:</strong> <?= e($r['direccion']) ?></p>
        <p><?= nl2br(e($r['descripcion'])) ?></p>

        <?php
          $resStmt->bind_param('i', $r['id']);
          $resStmt->execute();
          $respuestas = $resStmt->get_result();
        ?>
        <?php while ($resp = $respuestas->fetch_assoc()): ?>
          <div class="respuesta">
            <small><?= e($resp['fecha']) ?> · Respuesta de la administración</small>
            <p><?= nl2br(e($resp['mensaje'])) ?></p>
          </div>
        <?php endwhile; ?>
      </article>
    <?php endwhile; ?>
  </main>
  <script src="../assets_js/script_logout.js"></script>
  <script src="../assets_js/script_notificaciones.js"></script>
</body>
</html>