<?php
/* =====================================================================
   registro.php  |  Denuncia Digital
   Todo en un solo archivo: backend (PHP) + HTML + CSS + JS
   Tabla: usuario (id, nombre, correo, contrasena, direccion, apellido)
   ===================================================================== */

// ---------- Configuración rápida ----------
const PAGINA_LOGIN   = 'login.php';   // A dónde ir después de registrarse
const PAGINA_DESTINO = 'index.html';   // Página principal (si ya hay sesión activa)

// ---------- Sesión ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Si ya inició sesión, no necesita registrarse
if (!empty($_SESSION['usuario']['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST' && is_file(__DIR__ . '/' . PAGINA_DESTINO)) {
    header('Location: ' . PAGINA_DESTINO);
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------------
   PROCESAMIENTO DEL REGISTRO (petición AJAX por POST)
   --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $responder = function (bool $ok, string $mensaje, array $extra = [], int $codigo = 200) {
        http_response_code($codigo);
        echo json_encode(array_merge(['ok' => $ok, 'mensaje' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    };

    // 1) CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['csrf'], $csrf)) {
        $responder(false, 'La sesión del formulario expiró. Recarga la página e inténtalo de nuevo.', [], 419);
    }

    // 2) Recibir y limpiar datos
    $nombre    = trim((string)($_POST['nombre'] ?? ''));
    $apellido  = trim((string)($_POST['apellido'] ?? ''));
    $correo    = mb_strtolower(trim((string)($_POST['correo'] ?? '')));
    $direccion = trim((string)($_POST['direccion'] ?? ''));
    $password  = (string)($_POST['contrasena'] ?? '');
    $confirmar = (string)($_POST['confirmar'] ?? '');

    // 3) Validar en el servidor (nunca confiar solo en el navegador)
    $reNombre = '/^[\p{L}][\p{L}\s\'.-]{1,59}$/u';

    if (!preg_match($reNombre, $nombre)) {
        $responder(false, 'Escribe un nombre válido (solo letras, mínimo 2).', ['campo' => 'nombre'], 422);
    }
    if (!preg_match($reNombre, $apellido)) {
        $responder(false, 'Escribe un apellido válido (solo letras, mínimo 2).', ['campo' => 'apellido'], 422);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 100) {
        $responder(false, 'El correo electrónico no tiene un formato válido.', ['campo' => 'correo'], 422);
    }
    if (mb_strlen($direccion) < 5 || mb_strlen($direccion) > 150) {
        $responder(false, 'La dirección debe tener entre 5 y 150 caracteres.', ['campo' => 'direccion'], 422);
    }
    if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $responder(false, 'La contraseña debe tener entre 8 y 72 caracteres, con letras y números.', ['campo' => 'contrasena'], 422);
    }
    if (!hash_equals($password, $confirmar)) {
        $responder(false, 'Las contraseñas no coinciden.', ['campo' => 'confirmar'], 422);
    }

    // 3.1) Evitar HTML/etiquetas en campos de texto
    $nombre    = strip_tags($nombre);
    $apellido  = strip_tags($apellido);
    $direccion = strip_tags($direccion);

    // 4) Conexión a la base de datos (./conexion.php)
    //    ob_start descarta cualquier "echo" que tenga conexion.php
    ob_start();
    require_once __DIR__ . '/conexion.php';
    ob_end_clean();

    $db = $conexion ?? $conn ?? $con ?? $pdo ?? $mysqli ?? $db ?? $cn ?? null;
    if ($db === null) {
        $responder(false, 'No se pudo conectar con la base de datos.', [], 500);
    }

    // 5) Cifrar la contraseña (nunca se guarda en texto plano)
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 6) Comprobar que el correo no exista e insertar el usuario
    try {
        if ($db instanceof PDO) {
            $st = $db->prepare('SELECT id FROM usuario WHERE correo = ? LIMIT 1');
            $st->execute([$correo]);
            if ($st->fetch()) {
                $responder(false, 'Ese correo ya está registrado. Inicia sesión o usa otro correo.', ['campo' => 'correo'], 409);
            }
            $st = $db->prepare('INSERT INTO usuario (nombre, apellido, correo, contrasena, direccion) VALUES (?, ?, ?, ?, ?)');
            $st->execute([$nombre, $apellido, $correo, $hash, $direccion]);

        } elseif ($db instanceof mysqli) {
            $st = $db->prepare('SELECT id FROM usuario WHERE correo = ? LIMIT 1');
            $st->bind_param('s', $correo);
            $st->execute();
            $st->store_result();
            $existe = $st->num_rows > 0;
            $st->close();
            if ($existe) {
                $responder(false, 'Ese correo ya está registrado. Inicia sesión o usa otro correo.', ['campo' => 'correo'], 409);
            }
            $st = $db->prepare('INSERT INTO usuario (nombre, apellido, correo, contrasena, direccion) VALUES (?, ?, ?, ?, ?)');
            $st->bind_param('sssss', $nombre, $apellido, $correo, $hash, $direccion);
            $st->execute();
            $st->close();

        } else {
            $responder(false, 'Tipo de conexión no compatible (usa mysqli o PDO).', [], 500);
        }
    } catch (Throwable $e) {
        // 1062 = clave duplicada (por si la tabla tiene índice UNIQUE en correo)
        $codigo = ($e instanceof PDOException) ? ($e->errorInfo[1] ?? 0) : (int)$e->getCode();
        if ($codigo === 1062) {
            $responder(false, 'Ese correo ya está registrado. Inicia sesión o usa otro correo.', ['campo' => 'correo'], 409);
        }
        error_log('[registro.php] ' . $e->getMessage());
        $responder(false, 'No se pudo guardar el usuario. Inténtalo más tarde.', [], 500);
    }

    // 7) Registro correcto
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $responder(true, 'Cuenta creada correctamente. Ahora inicia sesión.', ['redirigir' => PAGINA_LOGIN]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="dark">
  <title>Crear cuenta | Denuncia Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="style_login.css">
  <link rel="stylesheet" href="style_registro.css">
</head>
<body>
  <main class="layout">
    <!-- Panel de marca con radar animado -->
    <section class="brand" aria-hidden="true">
      <div class="radar">
        <span class="ring"></span><span class="ring"></span><span class="ring"></span>
        <span class="sweep"></span>
        <span class="blip b1"></span><span class="blip b2"></span><span class="blip b3"></span>
        <svg class="shield" viewBox="0 0 24 24" width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.2 7.9-8 9-4.8-1.1-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
      </div>
      <h2 class="brand-title">Tu voz empieza aquí.</h2>
      <p class="brand-text">Crea tu cuenta para registrar y dar seguimiento a tus denuncias de forma segura.</p>
    </section>

    <!-- Formulario -->
    <section class="panel">
      <form id="registerForm" class="card" method="post" action="registro.php" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="logo">
          <span class="logo-mark"></span>
          <h1>Denuncia <em>Digital</em></h1>
        </div>
        <p class="subtitle">Crea tu cuenta para continuar</p>

        <div class="grid2">
          <div class="field" data-field="nombre">
            <input type="text" id="nombre" name="nombre" placeholder=" " autocomplete="given-name" maxlength="60" required>
            <label for="nombre">Nombre</label>
            <small class="hint" id="nombreHint" role="alert"></small>
          </div>
          <div class="field" data-field="apellido">
            <input type="text" id="apellido" name="apellido" placeholder=" " autocomplete="family-name" maxlength="60" required>
            <label for="apellido">Apellido</label>
            <small class="hint" id="apellidoHint" role="alert"></small>
          </div>
        </div>

        <div class="field" data-field="correo">
          <input type="email" id="correo" name="correo" placeholder=" " autocomplete="email" maxlength="100" required>
          <label for="correo">Correo electrónico</label>
          <small class="hint" id="correoHint" role="alert"></small>
        </div>

        <div class="field" data-field="direccion">
          <input type="text" id="direccion" name="direccion" placeholder=" " autocomplete="street-address" maxlength="150" required>
          <label for="direccion">Dirección</label>
          <small class="hint" id="direccionHint" role="alert"></small>
        </div>

        <div class="field" data-field="contrasena">
          <input type="password" id="contrasena" name="contrasena" placeholder=" " autocomplete="new-password" maxlength="72" required>
          <label for="contrasena">Contraseña</label>
          <button type="button" class="eye" data-toggle="contrasena" aria-label="Mostrar contraseña">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M3 3l18 18"/></svg>
          </button>
          <div class="meter" id="meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <ul class="rules" id="rules" aria-label="Requisitos de la contraseña">
            <li data-rule="len">8 caracteres</li>
            <li data-rule="letter">Una letra</li>
            <li data-rule="num">Un número</li>
          </ul>
          <small class="hint" id="contrasenaHint" role="alert"></small>
        </div>

        <div class="field" data-field="confirmar">
          <input type="password" id="confirmar" name="confirmar" placeholder=" " autocomplete="new-password" maxlength="72" required>
          <label for="confirmar">Confirmar contraseña</label>
          <button type="button" class="eye" data-toggle="confirmar" aria-label="Mostrar contraseña">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M3 3l18 18"/></svg>
          </button>
          <small class="hint" id="confirmarHint" role="alert"></small>
        </div>

        <button type="submit" class="btn top" id="submitBtn">
          <span class="btn-text">Crear cuenta</span>
          <span class="spinner" aria-hidden="true"></span>
        </button>

        <p class="foot">¿Ya tienes cuenta? <a href="<?= PAGINA_LOGIN ?>">Inicia sesión</a></p>
      </form>
    </section>
  </main>

  <div class="toasts" id="toasts" role="region" aria-live="polite" aria-label="Notificaciones"></div>

  <script src="script_registro.js"></script>
</body>
</html>