<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';
$conexion = $conexion ?? $conn ?? null;

if (!($conexion instanceof mysqli)) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}

if (!empty($_SESSION['usuario']['id']) && es_admin()) {
    header('Location: ../administrador/dashboard/dashboard.phps');
    exit;
}

$hayAdmin = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'admin'")->fetch_row()[0] > 0;
$error  = '';
$correo = '';
$aviso  = isset($_GET['registrado']) ? 'Administrador creado. Ya puedes iniciar sesión.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $ahora  = time();
    $correo = trim((string)($_POST['correo'] ?? ''));
    $clave  = (string)($_POST['contrasena'] ?? '');

    if (!empty($_SESSION['admin_bloqueo']) && $_SESSION['admin_bloqueo'] > $ahora) {
        $error = 'Demasiados intentos. Espera ' . ($_SESSION['admin_bloqueo'] - $ahora) . ' segundos.';
    } elseif ($correo === '' || $clave === '') {
        $error = 'Ingresa tu correo y tu contraseña.';
    } else {
        $st = $conexion->prepare(
            "SELECT id, nombre, apellido, correo, contrasena, direccion
             FROM usuario WHERE correo = ? AND rol = 'admin' LIMIT 1"
        );
        $st->bind_param('s', $correo);
        $st->execute();
        $admin = $st->get_result()->fetch_assoc();

                $valido = false;
        if ($admin) {
            $guardada = (string)$admin['contrasena'];
            if (password_get_info($guardada)['algo'] !== null) {
                $valido = password_verify($clave, $guardada);
            } elseif (preg_match('/^[a-f0-9]{32}$/i', $guardada)) {
                $valido = hash_equals(strtolower($guardada), md5($clave));
            } else {
                // Texto plano antiguo: se acepta y se convierte a MD5
                $valido = hash_equals($guardada, $clave);
                if ($valido) {
                    $nuevo = md5($clave);
                    $up = $conexion->prepare("UPDATE usuario SET contrasena = ? WHERE id = ?");
                    $up->bind_param('si', $nuevo, $admin['id']);
                    $up->execute();
                }
            }
        } else {
            md5($clave);
        }

        if ($valido) {
            session_regenerate_id(true);
            unset($_SESSION['admin_intentos'], $_SESSION['admin_bloqueo']);
            $_SESSION['usuario'] = [
                'id'        => (int)$admin['id'],
                'nombre'    => $admin['nombre'],
                'apellido'  => $admin['apellido'],
                'correo'    => $admin['correo'],
                'direccion' => $admin['direccion'],
                'rol'       => 'admin',
            ];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ../administrador/dashboard/dashboard.php');
            exit;
        }

        $_SESSION['admin_intentos'] = ($_SESSION['admin_intentos'] ?? 0) + 1;
        if ($_SESSION['admin_intentos'] >= 5) {
            $_SESSION['admin_bloqueo']  = $ahora + 60;
            $_SESSION['admin_intentos'] = 0;
            $error = 'Demasiados intentos fallidos. Espera 60 segundos.';
        } else {
            $error = 'Credenciales de administrador incorrectas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acceso de administración · Denuncia Digital</title>
  <link rel="stylesheet" href="../assets_css/style_acceso_administrador.css">
</head>
<body>
  <a class="esquina" href="login.php">← Acceso de usuarios</a>

  <main class="acceso">
    <form class="tarjeta" method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

      <h1>Administración</h1>
      <p class="sub">Acceso exclusivo para el administrador.</p>

      <?php if ($aviso): ?><p class="aviso ok"><?= e($aviso) ?></p><?php endif; ?>
      <?php if ($error): ?><p class="aviso error"><?= e($error) ?></p><?php endif; ?>

      <label for="correo">Correo</label>
      <input type="email" id="correo" name="correo" value="<?= e($correo) ?>" autocomplete="username" required>

      <label for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="contrasena" autocomplete="current-password" required>

      <button type="submit">Ingresar como administrador</button>

      <?php if (!$hayAdmin): ?>
        <p class="pie">Aún no hay administrador. <a href="registro_administrador.php">Registrar administrador</a></p>
      <?php endif; ?>
    </form>
  </main>
</body>
</html>