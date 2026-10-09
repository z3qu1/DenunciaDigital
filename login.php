<?php
/* =====================================================================
   login.php  |  Denuncia Digital
   Todo en un solo archivo: backend (PHP) + HTML + CSS + JS
   Tabla: usuario (id, nombre, correo, contrasena, direccion, apellido)
   ===================================================================== */

// ---------- Configuración rápida ----------
const PAGINA_DESTINO       = 'index.html';   // A dónde ir después de iniciar sesión
const PAGINA_REGISTRO      = 'registro.php'; // Enlace "Regístrate"
const PAGINA_RECUPERAR     = 'recuperar.php';// Enlace "¿Olvidaste tu contraseña?"
const MAX_INTENTOS         = 5;              // Intentos fallidos antes de bloquear
const SEGUNDOS_BLOQUEO     = 60;             // Duración del bloqueo
const DIAS_RECORDAR        = 30;             // Duración de sesión con "Recordarme"
const PERMITIR_TEXTO_PLANO = true;           // true = acepta contraseñas sin cifrar (solo mientras migras a password_hash)

// ---------- Sesión ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Si ya hay sesión activa, ir directo al sistema
if (!empty($_SESSION['usuario']['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . PAGINA_DESTINO);
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------------
   PROCESAMIENTO DEL LOGIN (petición AJAX por POST)
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

    // 2) Bloqueo por intentos fallidos
    $ahora = time();
    if (!empty($_SESSION['bloqueo_hasta']) && $_SESSION['bloqueo_hasta'] > $ahora) {
        $resta = $_SESSION['bloqueo_hasta'] - $ahora;
        $responder(false, "Demasiados intentos. Espera {$resta} segundos para volver a intentar.", ['espera' => $resta], 429);
    }

    // 3) Validar datos de entrada
    $correo   = trim((string)($_POST['correo'] ?? ''));
    $password = (string)($_POST['contrasena'] ?? '');
    $recordar = !empty($_POST['recordar']);

    if ($correo === '' || $password === '') {
        $responder(false, 'Ingresa tu correo y tu contraseña.', [], 422);
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $responder(false, 'El correo electrónico no tiene un formato válido.', [], 422);
    }

    // 4) Conexión a la base de datos (./conexion.php)
    //    ob_start descarta cualquier "echo" que tenga conexion.php
    ob_start();
    require_once __DIR__ . '/conexion.php';
    ob_end_clean();

    // Detecta el nombre de la variable de conexión más común
    $db = $conexion ?? $conn ?? $con ?? $pdo ?? $mysqli ?? $db ?? $cn ?? null;
    if ($db === null) {
        $responder(false, 'No se pudo conectar con la base de datos.', [], 500);
    }

    // 5) Buscar usuario por correo (consulta preparada)
    $usuario = null;
    try {
        if ($db instanceof PDO) {
            $st = $db->prepare('SELECT id, nombre, apellido, correo, contrasena, direccion FROM usuario WHERE correo = ? LIMIT 1');
            $st->execute([$correo]);
            $usuario = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($db instanceof mysqli) {
            $st = $db->prepare('SELECT id, nombre, apellido, correo, contrasena, direccion FROM usuario WHERE correo = ? LIMIT 1');
            $st->bind_param('s', $correo);
            $st->execute();
            $st->bind_result($id, $nombre, $apellido, $correoDb, $hash, $direccion);
            if ($st->fetch()) {
                $usuario = [
                    'id' => $id, 'nombre' => $nombre, 'apellido' => $apellido,
                    'correo' => $correoDb, 'contrasena' => $hash, 'direccion' => $direccion,
                ];
            }
            $st->close();
        } else {
            $responder(false, 'Tipo de conexión no compatible (usa mysqli o PDO).', [], 500);
        }
    } catch (Throwable $e) {
        error_log('[login.php] ' . $e->getMessage());
        $responder(false, 'Ocurrió un error en el servidor. Inténtalo más tarde.', [], 500);
    }

    // 6) Verificar contraseña (hash moderno o texto plano si está permitido)
    $valido = false;
    if ($usuario) {
        $guardada = (string)$usuario['contrasena'];
        if (password_get_info($guardada)['algo'] !== null) {
            $valido = password_verify($password, $guardada);
        } elseif (PERMITIR_TEXTO_PLANO) {
            $valido = hash_equals($guardada, $password);
        }
    } else {
        // Evita revelar si el correo existe (consume tiempo similar)
        password_verify($password, '$2y$10$usesomesillystringforsalt.invalidhashvalueXXXXXXXXXXXXXXXX');
    }

    if (!$valido) {
        $_SESSION['intentos'] = ($_SESSION['intentos'] ?? 0) + 1;
        if ($_SESSION['intentos'] >= MAX_INTENTOS) {
            $_SESSION['bloqueo_hasta'] = $ahora + SEGUNDOS_BLOQUEO;
            $_SESSION['intentos'] = 0;
            $responder(false, 'Demasiados intentos fallidos. Espera ' . SEGUNDOS_BLOQUEO . ' segundos.', ['espera' => SEGUNDOS_BLOQUEO], 429);
        }
        $quedan = MAX_INTENTOS - $_SESSION['intentos'];
        $responder(false, "Correo o contraseña incorrectos. Te quedan {$quedan} intentos.", [], 401);
    }

    // 7) Inicio de sesión correcto
    session_regenerate_id(true);
    unset($_SESSION['intentos'], $_SESSION['bloqueo_hasta']);
    $_SESSION['usuario'] = [
        'id'        => (int)$usuario['id'],
        'nombre'    => $usuario['nombre'],
        'apellido'  => $usuario['apellido'],
        'correo'    => $usuario['correo'],
        'direccion' => $usuario['direccion'],
    ];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));

    // "Recordarme": extiende la cookie de sesión
    if ($recordar) {
        setcookie(session_name(), session_id(), [
            'expires'  => time() + (DIAS_RECORDAR * 86400),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $responder(true, 'Bienvenido, ' . $usuario['nombre'] . '.', ['redirigir' => PAGINA_DESTINO]);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="color-scheme" content="dark">
  <title>Iniciar sesión | Denuncia Digital</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg: #04070b;
      --bg-2: #0a1118;
      --card: #0c141c;
      --line: #1c2a38;
      --line-2: #2a3d50;
      --text: #e8f3fb;
      --muted: #8196a8;
      --sky: #38bdf8;
      --sky-2: #7dd3fc;
      --sky-dark: #0284c7;
      --ok: #34d399;
      --err: #f87171;
      --radius: 14px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0;
      font-family: "Sora", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      color: var(--text);
      background: var(--bg);
      -webkit-font-smoothing: antialiased;
    }

    /* ================= LAYOUT ================= */
    .layout { display: grid; grid-template-columns: 1.05fr 1fr; min-height: 100vh; min-height: 100dvh; }

    /* ================= PANEL DE MARCA ================= */
    .brand {
      position: relative; overflow: hidden;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: 48px 40px; text-align: center;
      background:
        radial-gradient(70% 55% at 50% 38%, rgba(56,189,248,.16), transparent 70%),
        linear-gradient(180deg, #060b11, #020407);
      border-right: 1px solid var(--line);
    }
    .brand::before { /* cuadrícula tenue */
      content: ""; position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(56,189,248,.06) 1px, transparent 1px),
        linear-gradient(90deg, rgba(56,189,248,.06) 1px, transparent 1px);
      background-size: 44px 44px;
      -webkit-mask-image: radial-gradient(circle at 50% 40%, #000 20%, transparent 72%);
              mask-image: radial-gradient(circle at 50% 40%, #000 20%, transparent 72%);
    }
    .radar {
      position: relative; z-index: 1; width: min(330px, 60vw); aspect-ratio: 1; border-radius: 50%;
      display: grid; place-items: center; margin-bottom: 42px;
      border: 1px solid rgba(56,189,248,.45);
      box-shadow: 0 0 60px -10px rgba(56,189,248,.35), inset 0 0 50px rgba(56,189,248,.08);
    }
    .ring { position: absolute; inset: 0; border-radius: 50%; border: 1px solid rgba(56,189,248,.22); }
    .ring:nth-child(2) { inset: 17%; }
    .ring:nth-child(3) { inset: 34%; }
    .radar::before, .radar::after { /* ejes en cruz */
      content: ""; position: absolute; background: rgba(56,189,248,.16);
    }
    .radar::before { left: 50%; top: 0; bottom: 0; width: 1px; }
    .radar::after  { top: 50%; left: 0; right: 0; height: 1px; }
    .sweep {
      position: absolute; inset: 0; border-radius: 50%;
      background: conic-gradient(from 0deg, rgba(56,189,248,0) 0deg, rgba(56,189,248,0) 285deg, rgba(56,189,248,.5) 360deg);
      animation: spin 4.5s linear infinite;
    }
    .blip {
      position: absolute; width: 9px; height: 9px; border-radius: 50%;
      background: var(--sky-2); box-shadow: 0 0 10px var(--sky);
      animation: ping 3s ease-out infinite;
    }
    .b1 { top: 22%; left: 64%; }
    .b2 { top: 68%; left: 24%; animation-delay: 1s; }
    .b3 { top: 58%; left: 76%; animation-delay: 2s; }
    .shield {
      position: relative; z-index: 2; width: 78px; height: 78px; padding: 16px; border-radius: 50%;
      color: var(--sky-2); background: #050a10; border: 1px solid rgba(56,189,248,.55);
      box-shadow: 0 0 24px rgba(56,189,248,.35);
    }
    .brand-title { position: relative; z-index: 1; font-size: clamp(1.7rem, 2.6vw, 2.5rem); font-weight: 800; margin: 0 0 12px; letter-spacing: -.02em; }
    .brand-text { position: relative; z-index: 1; margin: 0; max-width: 34ch; color: var(--muted); line-height: 1.65; font-size: .98rem; }

    @keyframes spin { to { transform: rotate(360deg); } }
    @keyframes ping { 0% { box-shadow: 0 0 10px var(--sky), 0 0 0 0 rgba(56,189,248,.65); } 70%, 100% { box-shadow: 0 0 10px var(--sky), 0 0 0 16px rgba(56,189,248,0); } }

    /* ================= PANEL FORMULARIO ================= */
    .panel { display: grid; place-items: center; padding: 32px 20px; background: var(--bg); }
    .card {
      width: 100%; max-width: 420px; padding: 38px 34px 30px;
      background: linear-gradient(180deg, var(--card), var(--bg-2));
      border: 1px solid var(--line); border-radius: 20px;
      box-shadow: 0 30px 60px -30px rgba(0,0,0,.9), 0 0 0 1px rgba(56,189,248,.04), 0 0 80px -40px rgba(56,189,248,.35);
    }
    .card.shake { animation: shake .45s cubic-bezier(.36,.07,.19,.97); }
    @keyframes shake {
      10%, 90% { transform: translateX(-2px); } 20%, 80% { transform: translateX(4px); }
      30%, 50%, 70% { transform: translateX(-7px); } 40%, 60% { transform: translateX(7px); }
    }

    .logo { display: flex; align-items: center; gap: 12px; }
    .logo-mark { width: 14px; height: 30px; flex: none; border-radius: 4px 12px 4px 12px; background: linear-gradient(180deg, var(--sky-2), var(--sky-dark)); box-shadow: 0 0 16px rgba(56,189,248,.55); }
    .logo h1 { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; }
    .logo em { font-style: normal; color: var(--sky); }
    .subtitle { color: var(--muted); margin: 8px 0 26px; font-size: .95rem; }

    /* ---------- Campos (etiqueta flotante) ---------- */
    .field { position: relative; margin-bottom: 14px; }
    .field input[type="email"], .field input[type="password"], .field input[type="text"] {
      width: 100%; height: 56px; padding: 22px 48px 6px 15px;
      font: inherit; font-size: .98rem; color: var(--text);
      background: #070d13; border: 1.5px solid var(--line-2); border-radius: var(--radius);
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .field label {
      position: absolute; left: 16px; top: 18px; color: var(--muted); font-size: .95rem; pointer-events: none;
      transform-origin: left top; transition: transform .18s ease, font-size .18s ease, color .18s ease;
    }
    .field input:focus + label,
    .field input:not(:placeholder-shown) + label { transform: translateY(-12px); font-size: .72rem; }
    .field input:focus { outline: none; border-color: var(--sky); box-shadow: 0 0 0 4px rgba(56,189,248,.16); background: #08111a; }
    .field input:focus + label { color: var(--sky); }
    .field.invalid input { border-color: var(--err); }
    .field.invalid input:focus { box-shadow: 0 0 0 4px rgba(248,113,113,.16); }
    .field.invalid input:focus + label, .field.invalid label { color: var(--err); }
    .field.valid input { border-color: rgba(52,211,153,.7); }

    /* Autocompletado del navegador en modo oscuro */
    .field input:-webkit-autofill,
    .field input:-webkit-autofill:focus {
      -webkit-text-fill-color: var(--text);
      -webkit-box-shadow: 0 0 0 100px #070d13 inset;
      caret-color: var(--text);
      transition: background-color 9999s ease-out 0s;
    }

    /* Texto de ayuda / error (en el flujo, ya no se solapa) */
    .hint { display: block; min-height: 18px; margin: 6px 4px 0; font-size: .78rem; line-height: 1.3; color: var(--muted); }
    .field.invalid .hint { color: var(--err); }

    /* Botón del ojo */
    .eye { position: absolute; right: 8px; top: 10px; width: 36px; height: 36px; display: grid; place-items: center; background: none; border: 0; border-radius: 10px; color: var(--muted); cursor: pointer; transition: color .15s, background .15s; }
    .eye:hover { color: var(--sky-2); background: rgba(56,189,248,.1); }
    .eye:focus-visible { outline: 2px solid var(--sky); outline-offset: 1px; }
    .eye .slash { opacity: 0; transition: opacity .15s; }
    .eye.on .slash { opacity: 1; }

    /* Medidor de contraseña */
    .meter { display: flex; gap: 4px; height: 3px; margin: 8px 2px 0; opacity: 0; transition: opacity .2s; }
    .meter.show { opacity: 1; }
    .meter i { flex: 1; border-radius: 2px; background: var(--line-2); transition: background .2s; }
    .meter[data-level="1"] i:nth-child(-n+1) { background: var(--err); }
    .meter[data-level="2"] i:nth-child(-n+2) { background: #fbbf24; }
    .meter[data-level="3"] i:nth-child(-n+3) { background: var(--sky); }
    .meter[data-level="4"] i:nth-child(-n+4) { background: var(--ok); }

    /* ---------- Recordarme / olvidé ---------- */
    .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin: 10px 0 22px; font-size: .88rem; }
    .row a, .foot a { color: var(--sky); font-weight: 600; text-decoration: none; }
    .row a:hover, .foot a:hover { color: var(--sky-2); text-decoration: underline; }
    .row a:focus-visible, .foot a:focus-visible { outline: 2px solid var(--sky); outline-offset: 3px; border-radius: 4px; }
    .check { display: inline-flex; align-items: center; gap: 9px; cursor: pointer; color: var(--muted); user-select: none; position: relative; }
    .check input { position: absolute; opacity: 0; width: 0; height: 0; }
    .check span { width: 19px; height: 19px; display: grid; place-items: center; border: 1.5px solid var(--line-2); border-radius: 6px; background: #070d13; transition: .15s; }
    .check input:checked + span { background: var(--sky); border-color: var(--sky); }
    .check input:checked + span::after { content: ""; width: 5px; height: 10px; border: solid #04121c; border-width: 0 2px 2px 0; transform: rotate(45deg) translate(-1px,-1px); }
    .check input:focus-visible + span { outline: 2px solid var(--sky); outline-offset: 2px; }

    /* ---------- Botón principal ---------- */
    .btn {
      position: relative; width: 100%; height: 54px; border: 0; border-radius: var(--radius);
      font: inherit; font-weight: 700; font-size: 1rem; color: #03131d; cursor: pointer;
      background: linear-gradient(135deg, var(--sky-2), var(--sky) 55%, var(--sky-dark));
      box-shadow: 0 10px 26px -10px rgba(56,189,248,.65);
      transition: transform .1s, box-shadow .2s, filter .2s;
    }
    .btn:hover:not(:disabled) { filter: brightness(1.08); box-shadow: 0 14px 30px -10px rgba(56,189,248,.8); }
    .btn:active:not(:disabled) { transform: scale(.985); }
    .btn:focus-visible { outline: 3px solid var(--sky-2); outline-offset: 3px; }
    .btn:disabled { cursor: not-allowed; filter: saturate(.6) brightness(.85); box-shadow: none; }
    .spinner { position: absolute; left: 50%; top: 50%; width: 22px; height: 22px; margin: -11px 0 0 -11px; border-radius: 50%; border: 3px solid rgba(3,19,29,.25); border-top-color: #03131d; opacity: 0; animation: spin .7s linear infinite; }
    .btn.loading .btn-text { opacity: 0; }
    .btn.loading .spinner { opacity: 1; }

    .foot { text-align: center; color: var(--muted); font-size: .9rem; margin: 22px 0 0; }

    /* ================= NOTIFICACIONES ================= */
    .toasts {
      position: fixed; z-index: 100; top: calc(18px + env(safe-area-inset-top, 0px)); right: 18px;
      display: flex; flex-direction: column; gap: 10px;
      width: min(380px, calc(100vw - 36px)); pointer-events: none;
    }
    .toast {
      position: relative; overflow: hidden; pointer-events: auto;
      display: grid; grid-template-columns: 24px 1fr 24px; align-items: start; gap: 12px;
      padding: 14px 12px 16px 14px;
      background: #0d1822; color: var(--text);
      border: 1px solid var(--line-2); border-left: 4px solid var(--sky); border-radius: 12px;
      box-shadow: 0 18px 40px -14px rgba(0,0,0,.85);
      font-size: .9rem; line-height: 1.45;
      animation: toast-in .3s cubic-bezier(.2,.8,.2,1) both;
    }
    .toast.success { border-left-color: var(--ok); }
    .toast.error   { border-left-color: var(--err); }
    .toast.out { animation: toast-out .25s ease forwards; }
    .toast-icon { width: 24px; height: 24px; color: var(--sky); }
    .toast.success .toast-icon { color: var(--ok); }
    .toast.error .toast-icon { color: var(--err); }
    .toast-msg { padding-top: 2px; overflow-wrap: anywhere; }
    .toast-close { width: 24px; height: 24px; display: grid; place-items: center; padding: 0; background: none; border: 0; border-radius: 6px; color: var(--muted); cursor: pointer; }
    .toast-close:hover { color: var(--text); background: rgba(255,255,255,.08); }
    .toast-close:focus-visible { outline: 2px solid var(--sky); }
    .toast-bar { position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; background: currentColor; opacity: .55; transform-origin: left; animation: bar linear forwards; }
    .toast { color: var(--text); }
    .toast .toast-bar { color: var(--sky); }
    .toast.success .toast-bar { color: var(--ok); }
    .toast.error .toast-bar { color: var(--err); }
    .toast:hover .toast-bar { animation-play-state: paused; }

    @keyframes toast-in  { from { opacity: 0; transform: translateX(28px) scale(.98); } to { opacity: 1; transform: none; } }
    @keyframes toast-out { to { opacity: 0; transform: translateX(28px); max-height: 0; padding-block: 0; margin-block: -5px; } }
    @keyframes bar { from { transform: scaleX(1); } to { transform: scaleX(0); } }

    /* ================= RESPONSIVE ================= */
    @media (max-width: 860px) {
      .layout { grid-template-columns: 1fr; }
      .brand { padding: 26px 24px; border-right: 0; border-bottom: 1px solid var(--line); }
      .radar { width: 150px; margin-bottom: 18px; }
      .shield { width: 54px; height: 54px; padding: 11px; }
      .brand-text { display: none; }
      .brand-title { font-size: 1.4rem; margin: 0; }
      .toasts { left: 50%; right: auto; transform: translateX(-50%); }
    }
    @media (max-width: 440px) { .card { padding: 30px 22px 24px; } }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
      .sweep { animation: none; }
    }
  </style>
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
      <h2 class="brand-title">Tu voz, protegida.</h2>
      <p class="brand-text">Registra y da seguimiento a tus denuncias de forma segura desde cualquier lugar.</p>
    </section>

    <!-- Formulario -->
    <section class="panel">
      <form id="loginForm" class="card" method="post" action="login.php" novalidate>
        <input type="hidden" name="csrf" id="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="logo">
          <span class="logo-mark"></span>
          <h1>Denuncia <em>Digital</em></h1>
        </div>
        <p class="subtitle">Inicia sesión para continuar</p>

        <div class="field" data-field="email">
          <input type="email" id="email" name="correo" placeholder=" " autocomplete="email" required>
          <label for="email">Correo electrónico</label>
          <small class="hint" id="emailHint" role="alert"></small>
        </div>

        <div class="field" data-field="password">
          <input type="password" id="password" name="contrasena" placeholder=" " autocomplete="current-password" required>
          <label for="password">Contraseña</label>
          <button type="button" class="eye" id="togglePass" aria-label="Mostrar contraseña">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="slash" d="M3 3l18 18"/></svg>
          </button>
          <div class="meter" id="meter" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <small class="hint" id="passHint" role="alert"></small>
        </div>

        <div class="row">
          <label class="check"><input type="checkbox" id="remember" name="recordar" value="1"><span></span>Recordarme</label>
          <a href="<?= PAGINA_RECUPERAR ?>" id="forgot">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn" id="submitBtn">
          <span class="btn-text">Iniciar sesión</span>
          <span class="spinner" aria-hidden="true"></span>
        </button>

        <p class="foot">¿No tienes cuenta? <a href="<?= PAGINA_REGISTRO ?>" id="register">Regístrate</a></p>
      </form>
    </section>
  </main>

  <div class="toasts" id="toasts" role="region" aria-live="polite" aria-label="Notificaciones"></div>

  <script>
  (() => {
    const form      = document.getElementById('loginForm');
    const card      = form;
    const email     = document.getElementById('email');
    const pass      = document.getElementById('password');
    const emailHint = document.getElementById('emailHint');
    const passHint  = document.getElementById('passHint');
    const meter     = document.getElementById('meter');
    const toggle    = document.getElementById('togglePass');
    const btn       = document.getElementById('submitBtn');
    const btnText   = btn.querySelector('.btn-text');
    const toasts    = document.getElementById('toasts');
    const EMAIL_RE  = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    const BTN_LABEL = btnText.textContent;
    let countdown   = null;

    /* ---------- Notificaciones ---------- */
    const ICONS = {
      success: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/>',
      error:   '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.4v.1"/>',
      info:    '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>'
    };

    function removeToast(t) {
      if (!t || t.dataset.closing) return;
      t.dataset.closing = '1';
      t.classList.add('out');
      setTimeout(() => t.remove(), 260);
    }

    function toast(msg, type = 'info', ms = 5000) {
      // Evita duplicados: si ya hay uno igual, lo reemplaza
      [...toasts.children].forEach(el => {
        if (el.dataset.msg === msg) el.remove();
      });
      // Máximo 3 a la vez
      while (toasts.children.length >= 3) toasts.firstElementChild.remove();

      const t = document.createElement('div');
      t.className = 'toast ' + type;
      t.dataset.msg = msg;
      t.setAttribute('role', type === 'error' ? 'alert' : 'status');

      t.innerHTML =
        '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[type] || ICONS.info) + '</svg>' +
        '<div class="toast-msg"></div>' +
        '<button type="button" class="toast-close" aria-label="Cerrar notificación">' +
          '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>' +
        '</button>' +
        '<span class="toast-bar"></span>';

      t.querySelector('.toast-msg').textContent = msg;       // texto seguro
      t.querySelector('.toast-bar').style.animationDuration = ms + 'ms';
      t.querySelector('.toast-close').addEventListener('click', () => removeToast(t));
      t.querySelector('.toast-bar').addEventListener('animationend', () => removeToast(t));

      toasts.appendChild(t);
    }

    /* ---------- Estado visual de cada campo ---------- */
    function setState(input, hintEl, ok, msg) {
      const field = input.closest('.field');
      field.classList.toggle('invalid', ok === false);
      field.classList.toggle('valid', ok === true);
      input.setAttribute('aria-invalid', ok === false ? 'true' : 'false');
      hintEl.textContent = ok === false ? msg : '';
    }

    function checkEmail(showEmpty = false) {
      const v = email.value.trim();
      if (!v) {
        setState(email, emailHint, showEmpty ? false : null, 'Ingresa tu correo electrónico.');
        return false;
      }
      const ok = EMAIL_RE.test(v);
      setState(email, emailHint, ok, 'Escribe un correo válido, por ejemplo nombre@dominio.com.');
      return ok;
    }

    function checkPass(showEmpty = false) {
      if (!pass.value) {
        setState(pass, passHint, showEmpty ? false : null, 'Ingresa tu contraseña.');
        return false;
      }
      setState(pass, passHint, true, '');
      return true;
    }

    /* ---------- Medidor ---------- */
    function strength(v) {
      let s = 0;
      if (v.length >= 6) s++;
      if (v.length >= 10) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      return Math.min(s, 4);
    }

    email.addEventListener('blur',  () => { if (email.value) checkEmail(); });
    email.addEventListener('input', () => { if (email.closest('.field').classList.contains('invalid')) checkEmail(); });
    pass.addEventListener('input',  () => {
      meter.classList.toggle('show', pass.value.length > 0);
      meter.dataset.level = strength(pass.value);
      if (pass.closest('.field').classList.contains('invalid')) checkPass();
    });

    /* ---------- Mostrar / ocultar contraseña ---------- */
    toggle.addEventListener('click', () => {
      const show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      toggle.classList.toggle('on', show);
      toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      pass.focus();
    });

    /* ---------- Utilidades de estado del botón ---------- */
    function setLoading(on) {
      btn.disabled = on;
      btn.classList.toggle('loading', on);
    }

    function shake() {
      card.classList.remove('shake');
      void card.offsetWidth;              // reinicia la animación
      card.classList.add('shake');
    }

    function startCountdown(seconds) {
      clearInterval(countdown);
      let left = Math.max(1, parseInt(seconds, 10) || 60);
      btn.disabled = true;
      btnText.textContent = 'Espera ' + left + ' s';
      countdown = setInterval(() => {
        left--;
        if (left <= 0) {
          clearInterval(countdown);
          btn.disabled = false;
          btnText.textContent = BTN_LABEL;
        } else {
          btnText.textContent = 'Espera ' + left + ' s';
        }
      }, 1000);
    }

    /* ---------- Envío ---------- */
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (btn.disabled) return;

      const okE = checkEmail(true);
      const okP = checkPass(true);
      if (!okE || !okP) {
        shake();
        (okE ? pass : email).focus();
        toast('Revisa los campos marcados.', 'error');
        return;
      }

      setLoading(true);

      try {
        const res = await fetch(form.getAttribute('action') || 'login.php', {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          credentials: 'same-origin'
        });

        // Lee como texto primero: si PHP devolviera un warning/HTML no se rompe
        const raw = await res.text();
        let data = null;
        try { data = JSON.parse(raw); } catch (_) { /* respuesta no JSON */ }

        if (!data) {
          console.error('Respuesta inesperada del servidor:', raw);
          toast('El servidor devolvió una respuesta inesperada. Revisa conexion.php y los errores de PHP.', 'error', 7000);
          setLoading(false);
          return;
        }

        if (data.ok) {
          toast(data.mensaje || 'Sesión iniciada.', 'success', 2500);
          btn.classList.add('loading');   // el botón queda bloqueado mientras redirige
          setTimeout(() => { window.location.href = data.redirigir || 'index.html'; }, 800);
          return;
        }

        // Error controlado
        setLoading(false);
        shake();
        toast(data.mensaje || 'No se pudo iniciar sesión.', 'error', 6000);

        if (res.status === 419) {
          setTimeout(() => window.location.reload(), 2200);
        } else if (res.status === 401) {
          setState(pass, passHint, false, 'Revisa tu contraseña.');
          pass.focus(); pass.select();
        } else if (res.status === 429 && data.espera) {
          startCountdown(data.espera);
        }
      } catch (err) {
        console.error(err);
        setLoading(false);
        toast('No se pudo conectar con el servidor. Revisa tu conexión.', 'error');
      }
    });
  })();
  </script>
</body>
</html>