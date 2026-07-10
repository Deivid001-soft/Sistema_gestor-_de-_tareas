<?php
require_once '../config/database.php';
require_once '../includes/auth.php'; // donde tienes requireLogin() e isAdmin()

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->connect();

    // Leer datos (JSON o GET)
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $input['action'] ?? '';

    switch ($action) {
        case 'list':
            $stmt = $conn->prepare("
                SELECT p.*, 
                       u.full_name as creator_name,
                       COUNT(t.id) as task_count,
                       SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                FROM projects p
                LEFT JOIN users u ON p.created_by = u.id
                LEFT JOIN tasks t ON p.id = t.project_id
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute();
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'projects' => $projects]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($project) {
                echo json_encode(['success' => true, 'project' => $project]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Proyecto no encontrado']);
            }
            break;

        case 'create':
            if (!isAdmin()) throw new Exception("Acceso denegado");
            
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $status = $input['status'] ?? 'active';
            $created_by = $_SESSION['user_id'] ?? 1; // Ajusta según tu auth

            $stmt = $conn->prepare("INSERT INTO projects (name, description, status, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $status, $created_by]);

            echo json_encode(['success' => true, 'message' => 'Proyecto creado correctamente']);
            break;

        case 'update':
            if (!isAdmin()) throw new Exception("Acceso denegado");

            $id = (int) ($input['project_id'] ?? 0);
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $status = $input['status'] ?? 'active';

            $stmt = $conn->prepare("UPDATE projects SET name=?, description=?, status=? WHERE id=?");
            $stmt->execute([$name, $description, $status, $id]);

            echo json_encode(['success' => true, 'message' => 'Proyecto actualizado correctamente']);
            break;

        case 'delete':
            if (!isAdmin()) throw new Exception("Acceso denegado");

            $id = (int) ($input['project_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM projects WHERE id=?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Proyecto eliminado correctamente']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
