<?php
require __DIR__ . '/../incluides/autenticacion.php';
requerir_login();

$tipoElegido = $_GET['tipo'] ?? '';
$tipos = [
  'seguridad'         => 'Seguridad',
  'alumbrado'         => 'Alumbrado',
  'basura'            => 'Basura',
  'ruidos'            => 'Ruidos',
  'servicios_basicos' => 'Servicios básicos',
  'vias_transporte'   => 'Vías y transporte',
];
$nombreCompleto = $_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Nueva denuncia · Denuncia Digital</title>
  
  <link rel="stylesheet" href="/PROYECTO_PAGE/assets_css/style_notificaciones.css">
  <link rel="stylesheet" href="/PROYECTO_PAGE/assets_css/style_reporte.css">
</head>
<body>
  <div class="pagina">
    <nav class="nav-sup">
      <a href="/PROYECTO_PAGE/index.php">Inicio</a>
      <a href="/PROYECTO_PAGE/reportes/reporte.php" class="activo">Nueva denuncia</a>
      <a href="/PROYECTO_PAGE/reportes/mis_reporte.php">Mis denuncias</a>
      <span id="campana"></span>
      <a href="/PROYECTO_PAGE/auth/logout.php" class="salir">Cerrar sesión</a>
    </nav>
  <main class="contenedor">
    <h1>Registrar denuncia</h1>
    <p class="sub">Cuéntanos qué ocurre en tu zona.</p>

    <form id="formReporte" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <label for="nombre">Nombre</label>
      <input type="text" id="nombre" value="<?= e($nombreCompleto) ?>" readonly>

      <label for="tipo">Tipo de denuncia</label>
      <select id="tipo" name="tipo" required>
        <option value="">Selecciona una opción</option>
        <?php foreach ($tipos as $valor => $texto): ?>
          <option value="<?= e($valor) ?>" <?= $tipoElegido === $valor ? 'selected' : '' ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="descripcion">Descripción del problema</label>
      <textarea id="descripcion" name="descripcion" rows="5" required></textarea>

      <label for="direccion">Dirección o ubicación del suceso</label>
      <input type="text" id="direccion" name="direccion" maxlength="255"
             placeholder="Ej: Av. 6 de Marzo, cerca de la plaza" required>

      <label for="evidencias">Fotos o videos (máx. 5 archivos)</label>
      <input type="file" id="evidencias" name="evidencias[]"
             accept="image/jpeg,image/png,image/webp,video/mp4,video/webm" multiple>

      <button type="submit" id="btnEnviar">Enviar denuncia</button>
      <p id="mensaje" role="status"></p>
    </form>
  </main>
  <div class="resultado-fondo" id="resultado" hidden>
  <div class="resultado-caja" role="alertdialog" aria-modal="true" aria-labelledby="resTitulo">
    <div class="resultado-icono" id="resIcono"></div>
    <h2 id="resTitulo"></h2>
    <p id="resTexto"></p>
    <button type="button" id="resCerrar">Aceptar</button>
  </div>
</div>

  <script src="../assets_js/script_reporte.js"></script>
  <script src="/PROYECTO_PAGE/assets_js/script_notificaciones.js"></script>
  <script src="/PROYECTO_PAGE/assets_js/script_logout.js"></script>
</body>
</html>