<?php
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? $action;
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    switch ($action) {
        case 'list':
            listUsers($conn);
            break;
        case 'get':
            getUser($conn);
            break;
        case 'create':
            createUser($conn, $input);
            break;
        case 'update':
            updateUser($conn, $input);
            break;
        case 'delete':
            deleteUser($conn, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

function listUsers($conn) {
    $stmt = $conn->prepare("
        SELECT u.*, 
               COUNT(t.id) as total_tasks,
               SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
        FROM users u
        LEFT JOIN tasks t ON u.id = t.assigned_to
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'users' => $users]);
}

function getUser($conn) {
    $user_id = $_GET['id'] ?? 0;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // No devolver la contraseña
        unset($user['password']);
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    }
}

function createUser($conn, $input) {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'member';
    
    if (empty($username) || empty($email) || empty($full_name) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
        return;
    }
    
    if (!in_array($role, ['admin', 'member'])) {
        echo json_encode(['success' => false, 'message' => 'Rol no válido']);
        return;
    }
    
    // Verificar si el usuario o email ya existen
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario o email ya existen']);
        return;
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, full_name, password, role)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$username, $email, $full_name, $hashed_password, $role])) {
        echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al crear el usuario']);
    }
}

function updateUser($conn, $input) {
    $user_id = $input['user_id'] ?? 0;
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $full_name = trim($input['full_name'] ?? '');
    $role = $input['role'] ?? 'member';
    $new_password = $input['new_password'] ?? '';
    
    if (empty($username) || empty($email) || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'Los campos básicos son requeridos']);
        return;
    }
    
    if (!in_array($role, ['admin', 'member'])) {
        echo json_encode(['success' => false, 'message' => 'Rol no válido']);
        return;
    }
    
    // Verificar si el usuario o email ya existen (excepto el actual)
    $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario o email ya existen']);
        return;
    }
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, email = ?, full_name = ?, password = ?, role = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $params = [$username, $email, $full_name, $hashed_password, $role, $user_id];
    } else {
        $stmt = $conn->prepare("
            UPDATE users 
            SET username = ?, email = ?, full_name = ?, role = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $params = [$username, $email, $full_name, $role, $user_id];
    }
    
    if ($stmt->execute($params)) {
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario']);
    }
}

function deleteUser($conn, $input) {
    $user_id = $input['user_id'] ?? 0;
    
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo']);
        return;
    }
    
    // Verificar si el usuario tiene tareas asignadas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? OR created_by = ?");
    $stmt->execute([$user_id, $user_id]);
    $task_count = $stmt->fetch()['count'];
    
    if ($task_count > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar un usuario con tareas asignadas o creadas']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    
    if ($stmt->execute([$user_id])) {
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
    }
}
?>