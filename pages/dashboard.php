<?php
require_once '../config/database.php';
requireLogin();

$page_title = 'Dashboard';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Estadísticas generales
    $stats = [];
    
    // Total de tareas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tasks");
    $stmt->execute();
    $stats['total_tasks'] = $stmt->fetch()['total'];
    
    // Tareas por estado
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        GROUP BY status
    ");
    $stmt->execute();
    $status_counts = $stmt->fetchAll();
    
    $stats['pending'] = 0;
    $stats['in_progress'] = 0;
    $stats['completed'] = 0;
    $stats['cancelled'] = 0;
    
    foreach ($status_counts as $row) {
        $stats[$row['status']] = $row['count'];
    }
    
    // Tareas vencidas
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE due_date < CURDATE() 
        AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute();
    $stats['overdue'] = $stmt->fetch()['count'];
    
    // Tareas asignadas al usuario actual
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE assigned_to = ? 
        AND status NOT IN ('completed', 'cancelled')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['my_tasks'] = $stmt->fetch()['count'];
    
    // Proyectos activos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
    $stmt->execute();
    $stats['active_projects'] = $stmt->fetch()['count'];
    
    // Tareas recientes
    $stmt = $conn->prepare("
        SELECT t.*, p.name as project_name, u.full_name as assigned_name
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_tasks = $stmt->fetchAll();
    
    // Tareas próximas a vencer
    $stmt = $conn->prepare("
        SELECT t.*, p.name as project_name, u.full_name as assigned_name
        FROM tasks t
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND t.status NOT IN ('completed', 'cancelled')
        ORDER BY t.due_date ASC
        LIMIT 10
    ");
    $stmt->execute();
    $upcoming_tasks = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'Error al cargar el dashboard: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="dashboard-grid">
    <div class="card stat-card">
        <div class="stat-number" id="stat-total"><?php echo $stats['total_tasks']; ?></div>
        <div class="stat-label">Total de Tareas</div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-number" id="stat-pending" style="color: #f59e0b;"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">Pendientes</div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-number" id="stat-in_progress" style="color: #3b82f6;"><?php echo $stats['in_progress']; ?></div>
        <div class="stat-label">En Progreso</div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-number" id="stat-completed" style="color: #10b981;"><?php echo $stats['completed']; ?></div>
        <div class="stat-label">Completadas</div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-number" id="stat-overdue" style="color: #ef4444;"><?php echo $stats['overdue']; ?></div>
        <div class="stat-label">Vencidas</div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-number" style="color: #8b5cf6;"><?php echo $stats['my_tasks']; ?></div>
        <div class="stat-label">Mis Tareas Activas</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 2rem;">
    <!-- Tareas Recientes -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-clock"></i> Tareas Recientes</h2>
            <a href="tasks.php" class="btn btn-sm btn-primary">Ver Todas</a>
        </div>
        
        <div class="recent-tasks">
            <?php if (empty($recent_tasks)): ?>
                <p class="text-center" style="color: #6b7280;">No hay tareas recientes</p>
            <?php else: ?>
                <?php foreach ($recent_tasks as $task): ?>
                    <div class="task-item" style="padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h4 style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($task['title']); ?></h4>
                                <p style="margin: 0.25rem 0; color: #6b7280; font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($task['project_name'] ?? 'Sin proyecto'); ?>
                                </p>
                                <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">
                                    Asignado a: <?php echo htmlspecialchars($task['assigned_name'] ?? 'No asignado'); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                <?php 
                                $status_map = [
                                    'pending' => 'Pendiente',
                                    'in_progress' => 'En Progreso',
                                    'completed' => 'Completada',
                                    'cancelled' => 'Cancelada'
                                ];
                                echo $status_map[$task['status']] ?? $task['status'];
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Próximas a Vencer -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-exclamation-triangle"></i> Próximas a Vencer</h2>
        </div>
        
        <div class="upcoming-tasks">
            <?php if (empty($upcoming_tasks)): ?>
                <p class="text-center" style="color: #6b7280;">No hay tareas próximas a vencer</p>
            <?php else: ?>
                <?php foreach ($upcoming_tasks as $task): ?>
                    <div class="task-item" style="padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h4 style="margin: 0; color: #1f2937;"><?php echo htmlspecialchars($task['title']); ?></h4>
                                <p style="margin: 0.25rem 0; color: #6b7280; font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($task['project_name'] ?? 'Sin proyecto'); ?>
                                </p>
                                <p style="margin: 0; color: #9ca3af; font-size: 0.8rem;">
                                    Asignado a: <?php echo htmlspecialchars($task['assigned_name'] ?? 'No asignado'); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <span class="status-badge status-<?php echo $task['status']; ?>">
                                    <?php echo $status_map[$task['status']] ?? $task['status']; ?>
                                </span>
                                <p style="margin: 0.25rem 0 0 0; color: #ef4444; font-size: 0.8rem;">
                                    <?php echo date('d/m/Y', strtotime($task['due_date'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar estadísticas cada 30 segundos
    setInterval(function() {
        taskManager.loadTasks();
    }, 30000);
});
</script>

<?php include '../includes/footer.php'; ?>