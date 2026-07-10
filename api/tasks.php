<?php
// Iniciar buffer de salida para evitar problemas con headers
ob_start();
require_once '../config/database.php';

// Limpiar cualquier salida previa y establecer headers
ob_clean();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? '';
$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
        exit;
    }
    
    $action = $input['action'] ?? $action;
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    switch ($action) {
        case 'list':
            listTasks($conn);
            break;
        case 'get':
            getTask($conn);
            break;
        case 'create':
            createTask($conn, $input);
            break;
        case 'update':
            updateTask($conn, $input);
            break;
        case 'updateStatus':
            updateTaskStatus($conn, $input);
            break;
        case 'delete':
            deleteTask($conn, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Acción no válida: ' . $action,
                'available_actions' => ['list', 'get', 'create', 'update', 'updateStatus', 'delete']
            ]);
    }
} catch (Exception $e) {
    error_log("Tasks API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}

function listTasks($conn) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];
    
    try {
        // Los administradores pueden ver todas las tareas, los usuarios solo las suyas
        if ($user_role === 'admin') {
            $sql = "
                SELECT t.*, p.name as project_name, u.full_name as assigned_name, c.full_name as creator_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users c ON t.created_by = c.id
                ORDER BY t.created_at DESC
            ";
            $params = [];
        } else {
            $sql = "
                SELECT t.*, p.name as project_name, u.full_name as assigned_name, c.full_name as creator_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users c ON t.created_by = c.id
                WHERE t.assigned_to = ? OR t.created_by = ?
                ORDER BY t.created_at DESC
            ";
            $params = [$user_id, $user_id];
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'tasks' => $tasks]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener tareas: ' . $e->getMessage()]);
    }
}

function getTask($conn) {
    $task_id = $_GET['id'] ?? 0;
    
    try {
        $stmt = $conn->prepare("
            SELECT t.*, p.name as project_name, u.full_name as assigned_name
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if ($task) {
            // Verificar permisos
            if ($_SESSION['user_role'] !== 'admin' && 
                $task['assigned_to'] != $_SESSION['user_id'] && 
                $task['created_by'] != $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver esta tarea']);
                return;
            }
            
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener tarea: ' . $e->getMessage()]);
    }
}

function createTask($conn, $input) {
    try {
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $status = $input['status'] ?? 'pending';
        $priority = $input['priority'] ?? 'medium';
        $project_id = !empty($input['project_id']) ? intval($input['project_id']) : null;
        $assigned_to = !empty($input['assigned_to']) ? intval($input['assigned_to']) : null;
        $due_date = !empty($input['due_date']) ? $input['due_date'] : null;
        
        // Validaciones
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'El título es requerido']);
            return;
        }
        
        if (strlen($title) > 200) {
            echo json_encode(['success' => false, 'message' => 'El título no puede exceder 200 caracteres']);
            return;
        }
        
        $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Estado no válido']);
            return;
        }
        
        $valid_priorities = ['low', 'medium', 'high', 'urgent'];
        if (!in_array($priority, $valid_priorities)) {
            echo json_encode(['success' => false, 'message' => 'Prioridad no válida']);
            return;
        }
        
        // Verificar que el proyecto existe si se especifica
        if ($project_id) {
            $stmt = $conn->prepare("SELECT id FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
                return;
            }
        }
        
        // Verificar que el usuario asignado existe si se especifica
        if ($assigned_to) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$assigned_to]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Usuario asignado no encontrado']);
                return;
            }
        }
        
        // Validar fecha si se proporciona
        if ($due_date && !validateDate($due_date)) {
            echo json_encode(['success' => false, 'message' => 'Formato de fecha no válido']);
            return;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO tasks (title, description, status, priority, project_id, assigned_to, created_by, due_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$title, $description, $status, $priority, $project_id, $assigned_to, $_SESSION['user_id'], $due_date])) {
            $task_id = $conn->lastInsertId();
            echo json_encode([
                'success' => true, 
                'message' => 'Tarea creada exitosamente',
                'task_id' => $task_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al crear la tarea']);
        }
        
    } catch (Exception $e) {
        error_log("Create task error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al crear tarea: ' . $e->getMessage()]);
    }
}

function updateTask($conn, $input) {
    try {
        $task_id = intval($input['task_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $status = $input['status'] ?? 'pending';
        $priority = $input['priority'] ?? 'medium';
        $project_id = !empty($input['project_id']) ? intval($input['project_id']) : null;
        $assigned_to = !empty($input['assigned_to']) ? intval($input['assigned_to']) : null;
        $due_date = !empty($input['due_date']) ? $input['due_date'] : null;
        
        if (empty($title)) {
            echo json_encode(['success' => false, 'message' => 'El título es requerido']);
            return;
        }
        
        // Verificar permisos
        $stmt = $conn->prepare("SELECT created_by, assigned_to FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
            return;
        }
        
        if ($_SESSION['user_role'] !== 'admin' && 
            $task['created_by'] != $_SESSION['user_id'] && 
            $task['assigned_to'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar esta tarea']);
            return;
        }
        
        $stmt = $conn->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, status = ?, priority = ?, 
                project_id = ?, assigned_to = ?, due_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$title, $description, $status, $priority, $project_id, $assigned_to, $due_date, $task_id])) {
            echo json_encode(['success' => true, 'message' => 'Tarea actualizada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar la tarea']);
        }
        
    } catch (Exception $e) {
        error_log("Update task error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar tarea: ' . $e->getMessage()]);
    }
}

function updateTaskStatus($conn, $input) {
    try {
        $task_id = intval($input['task_id'] ?? 0);
        $status = $input['status'] ?? '';
        
        $valid_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Estado no válido']);
            return;
        }
        
        // Verificar permisos
        $stmt = $conn->prepare("SELECT created_by, assigned_to FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
            return;
        }
        
        if ($_SESSION['user_role'] !== 'admin' && 
            $task['created_by'] != $_SESSION['user_id'] && 
            $task['assigned_to'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para actualizar esta tarea']);
            return;
        }
        
        $stmt = $conn->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
        
        if ($stmt->execute([$status, $task_id])) {
            echo json_encode(['success' => true, 'message' => 'Estado actualizado exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
        }
        
    } catch (Exception $e) {
        error_log("Update task status error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()]);
    }
}

function deleteTask($conn, $input) {
    try {
        $task_id = intval($input['task_id'] ?? 0);
        
        // Verificar permisos
        $stmt = $conn->prepare("SELECT created_by, assigned_to FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
            return;
        }
        
        if ($_SESSION['user_role'] !== 'admin' && $task['created_by'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Solo el creador o un administrador puede eliminar esta tarea']);
            return;
        }
        
        // Eliminar comentarios primero
        $stmt = $conn->prepare("DELETE FROM task_comments WHERE task_id = ?");
        $stmt->execute([$task_id]);
        
        // Eliminar tarea
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        
        if ($stmt->execute([$task_id])) {
            echo json_encode(['success' => true, 'message' => 'Tarea eliminada exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar la tarea']);
        }
        
    } catch (Exception $e) {
        error_log("Delete task error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al eliminar tarea: ' . $e->getMessage()]);
    }
}

// Función auxiliar para validar fechas
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>