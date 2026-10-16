<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function es_admin(): bool {
    return (($_SESSION['usuario']['rol'] ?? '') === 'admin');
}

function requerir_login(): void {
    if (empty($_SESSION['usuario']['id'])) {
        header('Location: login.php');
        exit;
    }
}

    function requerir_admin(): void {
    if (empty($_SESSION['usuario']['id'])) {
        header('Location: login_admin.php');
        exit;
    }
    if (!es_admin()) {
        http_response_code(403);
        exit('Acceso denegado: solo administradores.');
    }
}


function verificar_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        exit('Sesión expirada. Recarga la página e inténtalo de nuevo.');
    }
}

function e($texto): string {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}