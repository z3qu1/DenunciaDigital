<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';
requerir_admin();

$miId = (int)$_SESSION['usuario']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre    = trim($_POST['nombre'] ?? '');
        $apellido  = trim($_POST['apellido'] ?? '');
        $correo    = trim($_POST['correo'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $clave     = (string)($_POST['contrasena'] ?? '');
        $rol = 'usuario';

        if ($nombre === '' || $apellido === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($clave) < 8) {
            $_SESSION['flash'] = ['error', 'Revisa los datos: correo válido y contraseña de mínimo 8 caracteres.'];
        } else {
            $chk = $conexion->prepare("SELECT id FROM usuario WHERE correo = ?");
            $chk->bind_param('s', $correo);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $_SESSION['flash'] = ['error', 'Ya existe un usuario con ese correo.'];
            } else {
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $ins = $conexion->prepare(
                    "INSERT INTO usuario (nombre, apellido, correo, contrasena, direccion, rol) VALUES (?, ?, ?, ?, ?, ?)"
                );
                $ins->bind_param('ssssss', $nombre, $apellido, $correo, $hash, $direccion, $rol);
                $_SESSION['flash'] = $ins->execute()
                    ? ['ok', 'Usuario creado correctamente.']
                    : ['error', 'No se pudo crear el usuario.'];
            }
        }
    }

    if ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $miId) {
            $_SESSION['flash'] = ['error', 'No puedes eliminar tu propia cuenta.'];
        } elseif ($id > 0) {
            $del = $conexion->prepare("DELETE FROM usuario WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $_SESSION['flash'] = ['ok', 'Usuario eliminado.'];
        }
    }

    header('Location: admin_usuarios.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$usuarios = $conexion->query("SELECT id, nombre, apellido, correo, rol FROM usuario ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios · Denuncia Digital</title>
  <link rel="stylesheet" href="../assets_css/style_admin.css">
  <link rel="stylesheet" href="../assets_css/style_logout.css">
</head>
<body>
  <main class="panel">
    <nav class="navegacion">
      <a href="admin_reportes.php">Reportes</a>
      <a href="admin_usuarios.php" class="activo">Usuarios</a>
      <a href="../dashboard/dashboard.html">Panel de control</a>
      <a href="../auth/logout.php" class="salir">Cerrar sesión</a>
    </nav>

    <h1>Gestión de usuarios</h1>

    <?php if ($flash): ?>
      <p class="aviso <?= e($flash[0]) ?>"><?= e($flash[1]) ?></p>
    <?php endif; ?>

    <section class="tarjeta">
      <h2>Agregar usuario</h2>
      <form method="post" class="form-usuario">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="accion" value="crear">
        <input type="text" name="nombre" placeholder="Nombre" required>
        <input type="text" name="apellido" placeholder="Apellido" required>
        <input type="email" name="correo" placeholder="Correo" required>
        <input type="password" name="contrasena" placeholder="Contraseña (mín. 8)" minlength="8" required>
        <input type="text" name="direccion" placeholder="Dirección">
        
          
        <button type="submit">Crear</button>
      </form>
    </section>

    <section class="tarjeta">
      <h2>Usuarios registrados</h2>
      <div class="tabla-scroll">
        <table>
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th><th></th></tr>
          </thead>
          <tbody>
            <?php while ($u = $usuarios->fetch_assoc()): ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= e($u['nombre'] . ' ' . $u['apellido']) ?></td>
                <td><?= e($u['correo']) ?></td>
                <td><span class="rol <?= e($u['rol']) ?>"><?= e($u['rol']) ?></span></td>
                <td>
                  <?php if ((int)$u['id'] !== $miId): ?>
                    <form method="post" onsubmit="return confirm('¿Eliminar a este usuario?');">
                      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="btn-borrar">Eliminar</button>
                    </form>
                  <?php else: ?>
                    <small>(tú)</small>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script src="../assets_js/script_logout.js"></script>
</body>
</html>