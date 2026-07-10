<?php
require_once '../config/database.php';
requireLogin();

$page_title = 'Gestión de Tareas';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Obtener proyectos para el select
    $stmt = $conn->prepare("SELECT id, name FROM projects WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $projects = $stmt->fetchAll();
    
    // Obtener usuarios para asignación
    $stmt = $conn->prepare("SELECT id, full_name FROM users ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'Error al cargar datos: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title"><i class="fas fa-list-check"></i> Gestión de Tareas</h1>
        <button class="btn btn-primary" onclick="openTaskModal()">
            <i class="fas fa-plus"></i> Nueva Tarea
        </button>
    </div>
    
    <!-- Filtros y búsqueda -->
    <div class="filters" style="display: grid; grid-template-columns: 1fr 200px 200px; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="form-group">
            <input type="text" id="taskSearch" class="form-control" placeholder="Buscar tareas...">
        </div>
        <div class="form-group">
            <select id="statusFilter" class="form-control">
                <option value="">Todos los estados</option>
                <option value="pending">Pendiente</option>
                <option value="in_progress">En Progreso</option>
                <option value="completed">Completada</option>
                <option value="cancelled">Cancelada</option>
            </select>
        </div>
        <div class="form-group">
            <select id="priorityFilter" class="form-control">
                <option value="">Todas las prioridades</option>
                <option value="low">Baja</option>
                <option value="medium">Media</option>
                <option value="high">Alta</option>
                <option value="urgent">Urgente</option>
            </select>
        </div>
    </div>
    
    <!-- Contenedor de tareas -->
    <div class="task-grid" id="tasksContainer">
        <p class="text-center">Cargando tareas...</p>
    </div>
</div>

<!-- Modal para crear/editar tareas -->
<div id="taskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nueva Tarea</h2>
            <span class="close close-modal">&times;</span>
        </div>
        
        <form id="taskForm">
            <input type="hidden" name="task_id" value="">
            
            <div class="form-group">
                <label for="title" class="form-label">Título *</label>
                <input type="text" id="title" name="title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-control">
                        <option value="pending">Pendiente</option>
                        <option value="in_progress">En Progreso</option>
                        <option value="completed">Completada</option>
                        <option value="cancelled">Cancelada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="priority" class="form-label">Prioridad</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="project_id" class="form-label">Proyecto</label>
                    <select id="project_id" name="project_id" class="form-control">
                        <option value="">Sin proyecto</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="assigned_to" class="form-label">Asignar a</label>
                    <select id="assigned_to" name="assigned_to" class="form-control">
                        <option value="">No asignado</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="due_date" class="form-label">Fecha límite</label>
                <input type="date" id="due_date" name="due_date" class="form-control">
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn" onclick="taskManager.closeModal()" style="background-color: #6b7280; color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Guardar Tarea
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<script>
// Filtros adicionales
document.addEventListener('DOMContentLoaded', function() {
    const priorityFilter = document.getElementById('priorityFilter');
    if (priorityFilter) {
        priorityFilter.addEventListener('change', function(e) {
            filterTasksByPriority(e.target.value);
        });
    }
});

function filterTasksByPriority(priority) {
    const tasks = document.querySelectorAll('.task-card');
    tasks.forEach(task => {
        if (priority === '' || task.classList.contains(`priority-${priority}`)) {
            task.style.display = 'block';
        } else {
            task.style.display = 'none';
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>