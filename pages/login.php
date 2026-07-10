<?php
require_once '../config/database.php';

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$success_message = '';
$show_register = isset($_GET['mode']) && $_GET['mode'] === 'register';

// Manejo de login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'register')) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Por favor, complete todos los campos';
    } else {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = 'Credenciales incorrectas';
            }
        } catch (Exception $e) {
            $error_message = 'Error en el sistema. Intente nuevamente.';
        }
    }
}

// Manejo de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($full_name) || empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
        $errors[] = 'Por favor, complete todos los campos';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email inválido';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    }
    
    if (empty($errors)) {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            // Verificar si username o email ya existen
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'El usuario o email ya está registrado';
            } else {
                // Insertar nuevo usuario (role por defecto: 'user')
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
                $stmt->execute([$full_name, $username, $email, $hashed_password]);
                
                $success_message = 'Registro exitoso. Puedes iniciar sesión ahora.';
                $show_register = false; // Volver a mostrar login
                // Opcional: auto-login
                // $_SESSION['user_id'] = $conn->lastInsertId();
                // ... (similar al login)
                // header('Location: dashboard.php');
                // exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Error en el sistema. Intente nuevamente.';
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
        $show_register = true;
    }
}

$page_title = $show_register ? 'Registrarse' : 'Iniciar Sesión';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestión de Tareas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-section { display: none; }
        .form-section.active { display: block; }
        .toggle-link { color: #3b82f6; cursor: pointer; text-decoration: underline; }
        .toggle-link:hover { color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h1 class="login-title">
                <i class="fas fa-tasks"></i> Administrador de Tareas
            </h1>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Formulario de Login -->
            <div id="loginForm" class="form-section <?php echo !$show_register ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username" class="form-label">Usuario o Email</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </button>
                </form>
                
                <p style="text-align: center; margin-top: 1rem;">
                    ¿No tienes cuenta? <span class="toggle-link" onclick="toggleForm('register')">Regístrate</span>
                </p>
            </div>
            
            <!-- Formulario de Registro -->
            <div id="registerForm" class="form-section <?php echo $show_register ? 'active' : ''; ?>">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Nombre Completo *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="register-username" class="form-label">Usuario *</label>
                        <input type="text" id="register-username" name="username" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="register-password" class="form-label">Contraseña *</label>
                        <input type="password" id="register-password" name="password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-user-plus"></i> Registrarse
                    </button>
                </form>
                
                <p style="text-align: center; margin-top: 1rem;">
                    ¿Ya tienes cuenta? <span class="toggle-link" onclick="toggleForm('login')">Inicia Sesión</span>
                </p>
            </div>

            <?php if (!$show_register): ?>
            <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e1e8ed; text-align: center; color: #6b7280;">
                <p><strong>Credenciales de prueba:</strong></p>
                <p>Usuario: admin | Contraseña: password</p>
                <p>Usuario: juan.perez | Contraseña: password</p><br>
                <a class="salir-btn" href="../../PAGINA/index.html">salir</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleForm(mode) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            if (mode === 'register') {
                loginForm.classList.remove('active');
                registerForm.classList.add('active');
                window.history.replaceState(null, '', '?mode=register');
            } else {
                registerForm.classList.remove('active');
                loginForm.classList.add('active');
                window.history.replaceState(null, '', window.location.pathname);
            }
        }
        
        // Validación adicional en cliente para confirmar contraseña
        document.querySelectorAll('form[action*="register"]').forEach(form => {
            form.addEventListener('submit', (e) => {
                const password = form.querySelector('[name="password"]').value;
                const confirm = form.querySelector('[name="confirm_password"]').value;
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                }
            });
        });
    </script>
</body>
</html>
