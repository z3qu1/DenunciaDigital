<?php
require 'autenticacion.php';

$logueado = !empty($_SESSION['usuario']['id']);
$admin    = $logueado && es_admin();
$nombre   = $logueado ? $_SESSION['usuario']['nombre'] : '';

// A dónde lleva cada botón de "denunciar" según quién eres
$destinoDenuncia = $logueado ? 'reporte.php' : 'login.php';

$tipos = [
  ['seguridad',         '🚨', 'Seguridad',         'Robos, asaltos y personas sospechosas.'],
  ['alumbrado',         '💡', 'Alumbrado',         'Luminarias dañadas o calles oscuras.'],
  ['basura',            '🗑️', 'Basura',            'Botaderos clandestinos y falta de recojo.'],
  ['ruidos',            '🔊', 'Ruidos',            'Fiestas y locales fuera de horario.'],
  ['servicios_basicos', '🚰', 'Servicios básicos', 'Fugas de agua, cortes de luz o gas.'],
  ['vias_transporte',   '🛣️', 'Vías y transporte', 'Baches, tránsito y paradas inseguras.'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Denuncia Digital · El Kenko, El Alto</title>
<meta name="description" content="Plataforma de denuncia digital para la zona El Kenko, El Alto, La Paz.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="style_logout.css">
<script>document.documentElement.classList.add('js');</script>
</head>
<body>

<header class="header" id="header">
  <a href="#inicio" class="logo"><span class="logo-mark">DD</span> Denuncia <em>Digital</em></a>
  <button class="burger" id="burger" aria-label="Abrir menú" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>

  <nav class="nav" id="nav">
    <a href="#inicio">Inicio</a>
    <a href="#como">Cómo funciona</a>
    <a href="#tipos">Tipos</a>
    <a href="#comunidad">Comunidad</a>

    <?php if ($admin): ?>
      <a href="admin_reportes.php">Reportes</a>
      <a href="admin_usuarios.php">Usuarios</a>
      <a href="logout.php" class="nav-salir">Salir</a>
    <?php elseif ($logueado): ?>
      <a href="mis_reportes.php">Mis denuncias</a>
      <a href="reporte.php" class="btn btn-sm">Denunciar</a>
      <a href="logout.php" class="nav-salir">Salir</a>
    <?php else: ?>
      <a href="login.php">Iniciar sesión</a>
      <a href="registro.php" class="btn btn-sm">Registrarse</a>
    <?php endif; ?>
  </nav>
</header>

<main>

<!-- CARRUSEL -->
<section class="hero" id="inicio" aria-label="Destacados">
  <div class="slides" id="slides">

    <article class="slide active s1">
      <div class="slide-txt">
        <?php if ($logueado): ?>
          <h1>Hola, <?= e($nombre) ?></h1>
        <?php else: ?>
          <h1>Tu voz protege a El Kenko</h1>
        <?php endif; ?>
        <p>Denuncia desde tu celular, sin filas y sin miedo. Tu reporte llega directo a la directiva de la zona.</p>
        <?php if ($admin): ?>
          <a href="Dashboard_DD/dashboard.html" class="btn">Ir al panel de control</a>
        <?php else: ?>
          <a href="<?= $destinoDenuncia ?>" class="btn">Hacer una denuncia</a>
        <?php endif; ?>
      </div>
    </article>

    <article class="slide s2">
      <div class="slide-txt">
        <h2>Vecinos atentos, barrio seguro</h2>
        <p>Reporta robos, luminarias apagadas, basura o ruidos molestos en minutos.</p>
        <a href="#tipos" class="btn">Ver tipos de denuncia</a>
      </div>
    </article>

    <article class="slide s3">
      <div class="slide-txt">
        <h2>Una comunidad conectada</h2>
        <p>Conoce a tus dirigentes, teléfonos de emergencia y las reuniones de la zona.</p>
        <a href="#comunidad" class="btn">Conocer la comunidad</a>
      </div>
    </article>

  </div>

  <button class="arrow prev" id="prev" aria-label="Anterior">‹</button>
  <button class="arrow next" id="next" aria-label="Siguiente">›</button>
  <div class="dots" id="dots"></div>
  <div class="progress"><span id="bar"></span></div>
</section>

<!-- COMO FUNCIONA -->
<section class="section" id="como">
  <h2 class="title reveal">Denunciar es simple</h2>
  <ol class="steps">
    <li class="step reveal"><b>1</b><h3>Inicia sesión</h3><p>Regístrate o entra con tu cuenta para enviar tu denuncia.</p></li>
    <li class="step reveal"><b>2</b><h3>Cuéntanos qué pasó</h3><p>Describe el hecho, el lugar y adjunta fotos o videos si los tienes.</p></li>
    <li class="step reveal"><b>3</b><h3>Seguimiento</h3><p>Mira el estado de tu caso y la respuesta de la administración en "Mis denuncias".</p></li>
  </ol>
</section>

<!-- TIPOS -->
<section class="section alt" id="tipos">
  <h2 class="title reveal">¿Qué puedes denunciar?</h2>
  <div class="grid">
    <?php foreach ($tipos as [$valor, $icono, $titulo, $texto]): ?>
      <?php
        if ($admin)         { $href = '#'; }
        elseif ($logueado)  { $href = 'reporte.php?tipo=' . $valor; }
        else                { $href = 'login.php'; }
      ?>
      <a class="card reveal" href="<?= e($href) ?>">
        <span class="ico"><?= $icono ?></span>
        <h3><?= e($titulo) ?></h3>
        <p><?= e($texto) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- COMUNIDAD -->
<section class="section" id="comunidad">
  <h2 class="title reveal">Información de la comunidad</h2>

  <div class="tabs reveal" role="tablist">
    <button class="tab active" data-tab="t1" role="tab">Sobre El Kenko</button>
    <button class="tab" data-tab="t2" role="tab">Emergencias</button>
    <button class="tab" data-tab="t3" role="tab">Directiva y reuniones</button>
  </div>

  <div class="panel active" id="t1">
    <p>El Kenko es una zona de la ciudad de El Alto, departamento de La Paz. Denuncia Digital nace para que los vecinos se organicen, reporten problemas y trabajen junto a sus dirigentes por un barrio más seguro y limpio.</p>
  </div>

  <div class="panel" id="t2">
    <ul class="contacts">
      <li><span>Policía</span><a href="tel:110">110</a></li>
      <li><span>Bomberos</span><a href="tel:119">119</a></li>
      <li><span>Ambulancia</span><a href="tel:118">118</a></li>
    </ul>
    <small>Verifica y actualiza estos números con tu junta vecinal.</small>
  </div>

  <div class="panel" id="t3">
    <ul class="contacts">
      <li><span>Presidente(a) de la zona</span><em>Nombre por definir</em></li>
      <li><span>Reunión mensual</span><em>Fecha y hora por definir</em></li>
      <li><span>Sede vecinal</span><em>Dirección por definir</em></li>
    </ul>
  </div>
</section>

<!-- LLAMADA A LA ACCION -->
<section class="section alt" id="contacto">
  <h2 class="title reveal">Haz tu denuncia</h2>
  <div class="cta reveal">
    <?php if ($admin): ?>
      <p>Estás como administrador. Revisa y responde las denuncias de los vecinos.</p>
      <a href="Dashboard_DD/dashboard.html" class="btn">Panel de control</a>
      <a href="admin_usuarios.php" class="btn btn-sec">Gestionar usuarios</a>
    <?php elseif ($logueado): ?>
      <p>Cuéntanos qué ocurre en tu zona, <?= e($nombre) ?>.</p>
      <a href="reporte.php" class="btn">Nueva denuncia</a>
      <a href="mis_reportes.php" class="btn btn-sec">Ver mis denuncias</a>
    <?php else: ?>
      <p>Para enviar una denuncia y seguir su estado, inicia sesión o crea tu cuenta.</p>
      <a href="login.php" class="btn">Iniciar sesión</a>
      <a href="registro.php" class="btn btn-sec">Registrarme</a>
    <?php endif; ?>
  </div>
</section>

</main>

<footer class="footer">
  <div class="foot-grid">
    <div>
      <a href="#inicio" class="logo"><span class="logo-mark">DD</span> Denuncia <em>Digital</em></a>
      <p>Zona El Kenko, El Alto, La Paz, Bolivia.</p>
    </div>
    <div>
      <h4>Enlaces</h4>
      <a href="#como">Cómo funciona</a>
      <a href="#tipos">Tipos de denuncia</a>
      <a href="#comunidad">Comunidad</a>
    </div>
    <div>
      <h4>Emergencias</h4>
      <a href="tel:110">Policía 110</a>
      <a href="tel:119">Bomberos 119</a>
      <a href="tel:118">Ambulancia 118</a>
    </div>
  </div>
  <p class="copy">© <span id="year"></span> Denuncia Digital · Hecho para la comunidad de El Kenko</p>
</footer>

<button class="top" id="top" aria-label="Volver arriba">↑</button>

<script src="script.js"></script>
<script src="script_logout.js"></script>
<script>
/* Respaldo: garantiza que las secciones aparezcan aunque script.js falle */
(function () {
  var items = document.querySelectorAll('.reveal');
  function mostrar(el) { el.classList.add('in'); }

  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (en) {
        if (en.isIntersecting) { mostrar(en.target); obs.unobserve(en.target); }
      });
    }, { threshold: 0.1 });
    items.forEach(function (el) { obs.observe(el); });
  } else {
    items.forEach(mostrar);
  }

  setTimeout(function () { items.forEach(mostrar); }, 3000);
})();
</script>
</body>
</html>