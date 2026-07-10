<?php
ob_start(); // Iniciar buffer de salida
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Limpiar cualquier salida previa
ob_clean();

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Para requests POST, obtener la acción del body JSON
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE && isset($input['action'])) {
        $action = $input['action'];
    }
}

try {
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        case 'check':
            checkAuth();
            break;
        case 'register':
            handleRegister();
            break;
        case 'forgot-password':
            handleForgotPassword();
            break;
        case 'validate-session':
            validateSession();
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Acción no válida',
                'available_actions' => ['login', 'logout', 'check', 'register', 'forgot-password', 'validate-session']
            ]);
    }
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error interno del servidor',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}

function handleLogin() {
    global $method;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        return;
    }
    
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $remember_me = $input['remember_me'] ?? false;
    
    // Validación de entrada
    if (empty($username) || empty($password)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Usuario y contraseña son requeridos',
            'error_code' => 'MISSING_CREDENTIALS'
        ]);
        return;
    }
    
    if (strlen($username) < 3) {
        echo json_encode([
            'success' => false, 
            'message' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'error_code' => 'INVALID_USERNAME'
        ]);
        return;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Buscar usuario por username o email
        $stmt = $conn->prepare("
            SELECT id, username, email, password, full_name, role, created_at, updated_at
            FROM users 
            WHERE (username = ? OR email = ?) AND deleted_at IS NULL
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Registrar intento de login fallido
            error_log("Failed login attempt for user: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
            echo json_encode([
                'success' => false, 
                'message' => 'Credenciales incorrectas',
                'error_code' => 'INVALID_CREDENTIALS'
            ]);
            return;
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            error_log("Failed password verification for user: " . $user['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
            echo json_encode([
                'success' => false, 
                'message' => 'Credenciales incorrectas',
                'error_code' => 'INVALID_CREDENTIALS'
            ]);
            return;
        }
        
        // Login exitoso - establecer sesión
        session_regenerate_id(true); // Prevenir session fixation
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Si remember me está activo, extender la sesión
        if ($remember_me) {
            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60); // 30 días
            session_set_cookie_params(30 * 24 * 60 * 60);
        }
        
        // Actualizar último login
        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Registrar login exitoso
        error_log("Successful login for user: " . $user['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Inicio de sesión exitoso',
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'member_since' => date('Y-m-d', strtotime($user['created_at']))
            ],
            'session_info' => [
                'login_time' => $_SESSION['login_time'],
                'expires_in' => $remember_me ? 30 * 24 * 60 * 60 : ini_get('session.gc_maxlifetime')
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error interno del servidor durante el login',
            'error_code' => 'LOGIN_ERROR'
        ]);
    }
}

function handleLogout() {
    try {
        $user_info = '';
        if (isLoggedIn()) {
            $user_info = $_SESSION['username'];
            error_log("User logout: " . $user_info . " from IP: " . $_SERVER['REMOTE_ADDR']);
        }
        
        // Limpiar todas las variables de sesión
        $_SESSION = array();
        
        // Eliminar cookie de sesión
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-42000, '/');
        }
        
        // Destruir la sesión
        session_destroy();
        
        // Si es una petición GET (desde logout link), redirigir
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || isset($_GET['action'])) {
            header('Location: ../pages/login.php?message=logged_out');
            exit();
        } else {
            // Si es una petición AJAX, devolver JSON
            echo json_encode([
                'success' => true, 
                'message' => 'Sesión cerrada correctamente',
                'redirect' => 'login.php'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error al cerrar sesión',
            'error_code' => 'LOGOUT_ERROR'
        ]);
    }
}

function checkAuth() {
    try {
        if (isLoggedIn()) {
            // Verificar si la sesión ha expirado
            $session_lifetime = ini_get('session.gc_maxlifetime');
            if (isset($_SESSION['last_activity']) && 
                (time() - $_SESSION['last_activity']) > $session_lifetime) {
                
                session_destroy();
                echo json_encode([
                    'success' => true,
                    'authenticated' => false,
                    'message' => 'Sesión expirada',
                    'error_code' => 'SESSION_EXPIRED'
                ]);
                return;
            }
            
            // Actualizar última actividad
            $_SESSION['last_activity'] = time();
            
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => (int)$_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'full_name' => $_SESSION['user_name'],
                    'role' => $_SESSION['user_role']
                ],
                'session_info' => [
                    'login_time' => $_SESSION['login_time'] ?? null,
                    'last_activity' => $_SESSION['last_activity'],
                    'time_remaining' => $session_lifetime - (time() - ($_SESSION['last_activity'] ?? time()))
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'authenticated' => false,
                'message' => 'No hay sesión activa'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Auth check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al verificar autenticación',
            'error_code' => 'AUTH_CHECK_ERROR'
        ]);
    }
}

function handleRegister() {
    global $method;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        return;
    }
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    
    // Validaciones
    $errors = [];
    
    if (empty($username)) $errors[] = 'El nombre de usuario es requerido';
    elseif (strlen($username) < 3) $errors[] = 'El nombre de usuario debe tener al menos 3 caracteres';
    elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) $errors[] = 'El nombre de usuario solo puede contener letras, números, puntos, guiones y guiones bajos';
    
    if (empty($email)) $errors[] = 'El email es requerido';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no es válido';
    
    if (empty($full_name)) $errors[] = 'El nombre completo es requerido';
    elseif (strlen($full_name) < 2) $errors[] = 'El nombre completo debe tener al menos 2 caracteres';
    
    if (empty($password)) $errors[] = 'La contraseña es requerida';
    elseif (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
    elseif ($password !== $confirm_password) $errors[] = 'Las contraseñas no coinciden';
    
    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errores de validación',
            'errors' => $errors,
            'error_code' => 'VALIDATION_ERROR'
        ]);
        return;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        // Verificar si el usuario o email ya existen
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            echo json_encode([
                'success' => false, 
                'message' => 'El nombre de usuario o email ya están en uso',
                'error_code' => 'USER_EXISTS'
            ]);
            return;
        }
        
        // Hash de la contraseña
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insertar nuevo usuario
        $stmt = $conn->prepare("
            INSERT INTO users (username, email, full_name, password, role)
            VALUES (?, ?, ?, ?, 'member')
        ");
        
        if ($stmt->execute([$username, $email, $full_name, $hashed_password])) {
            $user_id = $conn->lastInsertId();
            
            error_log("New user registered: " . $username . " from IP: " . $_SERVER['REMOTE_ADDR']);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Usuario registrado exitosamente',
                'user_id' => (int)$user_id
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error al crear el usuario',
                'error_code' => 'CREATE_USER_ERROR'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error interno durante el registro',
            'error_code' => 'REGISTRATION_ERROR'
        ]);
    }
}

function handleForgotPassword() {
    global $method;
    
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        return;
    }
    
    $email = trim($input['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Email válido es requerido',
            'error_code' => 'INVALID_EMAIL'
        ]);
        return;
    }
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id, username, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generar token de reset (en una implementación real, esto se guardaría en la DB)
            $reset_token = bin2hex(random_bytes(32));
            
            error_log("Password reset requested for user: " . $user['username'] . " from IP: " . $_SERVER['REMOTE_ADDR']);
            
            // Aquí normalmente enviarías un email con el token
            // Por ahora, solo registramos la solicitud
            echo json_encode([
                'success' => true, 
                'message' => 'Si el email existe en nuestro sistema, recibirás instrucciones para restablecer tu contraseña',
                'debug_info' => [
                    'user_found' => true,
                    'reset_token' => $reset_token // Solo para desarrollo
                ]
            ]);
        } else {
            // Por seguridad, devolver el mismo mensaje aunque el usuario no exista
            echo json_encode([
                'success' => true, 
                'message' => 'Si el email existe en nuestro sistema, recibirás instrucciones para restablecer tu contraseña',
                'debug_info' => [
                    'user_found' => false
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'message' => 'Error al procesar la solicitud',
            'error_code' => 'FORGOT_PASSWORD_ERROR'
        ]);
    }
}

function validateSession() {
    try {
        if (!isLoggedIn()) {
            echo json_encode([
                'success' => true,
                'valid' => false,
                'message' => 'Sesión no válida'
            ]);
            return;
        }
        
        // Verificar integridad de la sesión
        $required_fields = ['user_id', 'username', 'user_name', 'user_role'];
        foreach ($required_fields as $field) {
            if (!isset($_SESSION[$field])) {
                session_destroy();
                echo json_encode([
                    'success' => true,
                    'valid' => false,
                    'message' => 'Sesión corrupta',
                    'error_code' => 'CORRUPTED_SESSION'
                ]);
                return;
            }
        }
        
        // Validar que el usuario aún existe en la base de datos
        $db = new Database();
        $conn = $db->connect();
        
        $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            session_destroy();
            echo json_encode([
                'success' => true,
                'valid' => false,
                'message' => 'Usuario no encontrado',
                'error_code' => 'USER_NOT_FOUND'
            ]);
            return;
        }
        
        // Verificar que el rol no ha cambiado
        if ($user['role'] !== $_SESSION['user_role']) {
            $_SESSION['user_role'] = $user['role'];
        }
        
        echo json_encode([
            'success' => true,
            'valid' => true,
            'message' => 'Sesión válida',
            'session_time_remaining' => ini_get('session.gc_maxlifetime') - (time() - ($_SESSION['last_activity'] ?? time()))
        ]);
        
    } catch (Exception $e) {
        error_log("Session validation error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error al validar sesión',
            'error_code' => 'SESSION_VALIDATION_ERROR'
        ]);
    }
}
?>