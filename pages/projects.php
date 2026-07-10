<?php
ob_start();
require_once '../config/database.php';
requireLogin();

// Solo administradores pueden acceder
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Gestión de Proyectos';

// Mapa de estados PHP (debe coincidir con JS)
$statusMap = [
    'active' => 'Activo',
    'completed' => 'Completado',
    'on_hold' => 'En Pausa'
];

try {
    $db = new Database();
    $conn = $db->connect();

    // Obtener usuarios (no usado en este formulario)
    $stmt = $conn->prepare("SELECT id, full_name FROM users ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();

    // Cargar proyectos
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

} catch (Exception $e) {
    $error_message = 'Error al cargar datos: ' . $e->getMessage();
    $projects = [];
}

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title"><i class="fas fa-project-diagram"></i> Gestión de Proyectos</h1>
        <button class="btn btn-primary" onclick="openProjectModal()">
            <i class="fas fa-plus"></i> Nuevo Proyecto
        </button>
    </div>
    
    <!-- Filtros y búsqueda -->
    <div class="filters" style="display: grid; grid-template-columns: 1fr 200px; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="form-group">
            <input type="text" id="projectSearch" class="form-control" placeholder="Buscar proyectos...">
        </div>
        <div class="form-group">
            <select id="statusFilter" class="form-control">
                <option value="">Todos los estados</option>
                <option value="active">Activo</option>
                <option value="completed">Completado</option>
                <option value="on_hold">En Pausa</option>
            </select>
        </div>
    </div>
    
    <!-- Contenedor de proyectos -->
    <div class="projects-grid" id="projectsContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1rem;">
        <?php if (empty($projects)): ?>
            <p class="text-center">No hay proyectos para mostrar</p>
        <?php else: ?>
            <?php foreach ($projects as $project): ?>
                <div class="card" data-project-id="<?= htmlspecialchars($project['id']) ?>">
                    <div class="card-header">
                        <h3 style="margin: 0; color: #2c3e50;"><?= htmlspecialchars($project['name']) ?></h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-primary" onclick="projectManager.editProject(<?= $project['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="projectManager.deleteProject(<?= $project['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div style="padding: 1rem;">
                        <p style="color: #6b7280; margin-bottom: 1rem;"><?= htmlspecialchars($project['description'] ?: 'Sin descripción') ?></p>
                        
                        <div style="margin-bottom: 1rem;">
                            <span class="status-badge status-<?= htmlspecialchars($project['status']) ?>">
                                <?= $statusMap[$project['status']] ?? htmlspecialchars($project['status']) ?>
                            </span>
                        </div>
                        
                        <?php 
                        $taskCount = $project['task_count'] ?? 0;
                        $completedTasks = $project['completed_tasks'] ?? 0;
                        $progress = $taskCount > 0 ? round(($completedTasks / $taskCount) * 100) : 0;
                        ?>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-size: 0.875rem; color: #6b7280;">Progreso</span>
                                <span style="font-size: 0.875rem; font-weight: bold;"><?= $progress ?>%</span>
                            </div>
                            <div style="background-color: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background-color: #10b981; height: 100%; width: <?= $progress ?>%; transition: width 0.3s;"></div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.875rem; color: #6b7280;">
                            <div><strong>Total tareas:</strong> <?= $taskCount ?></div>
                            <div><strong>Completadas:</strong> <?= $completedTasks ?></div>
                            <div><strong>Creado:</strong> <?= date('d/m/Y', strtotime($project['created_at'])) ?></div>
                            <div><strong>Creador:</strong> <?= htmlspecialchars($project['creator_name'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para crear/editar proyectos -->
<div id="projectModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="projectModalTitle">Nuevo Proyecto</h2>
            <span class="close close-modal" style="cursor:pointer;">&times;</span>
        </div>
        
        <form id="projectForm">
            <input type="hidden" name="project_id" value="">
            
            <div class="form-group">
                <label for="project_name" class="form-label">Nombre del Proyecto *</label>
                <input type="text" id="project_name" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="project_description" class="form-label">Descripción</label>
                <textarea id="project_description" name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label for="project_status" class="form-label">Estado</label>
                <select id="project_status" name="status" class="form-control">
                    <option value="active">Activo</option>
                    <option value="completed">Completado</option>
                    <option value="on_hold">En Pausa</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn" onclick="closeProjectModal()" style="background-color: #6b7280; color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Guardar Proyecto
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Mapa de estados
const statusMap = {
    'active': 'Activo',
    'completed': 'Completado',
    'on_hold': 'En Pausa'
};

// Sistema simple de alertas
function showAlert(message, type = 'info') {
    let alertContainer = document.getElementById('alertContainer');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alertContainer';
        alertContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        document.body.appendChild(alertContainer);
    }
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.style.cssText = 'padding: 1rem; margin-bottom: 1rem; border-radius: 0.5rem; color: white;';
    alertDiv.innerHTML = `
        ${message}
        <button onclick="this.parentElement.remove()" style="float: right; background: none; border: none; color: white; font-size: 1.2rem; cursor: pointer;">&times;</button>
    `;
    if (type === 'success') alertDiv.style.backgroundColor = '#10b981';
    else if (type === 'error') alertDiv.style.backgroundColor = '#ef4444';
    else alertDiv.style.backgroundColor = '#3b82f6';
    alertContainer.appendChild(alertDiv);
    setTimeout(() => {
        if (alertDiv.parentElement) alertDiv.remove();
    }, 5000);
}

// Gestión de proyectos
class ProjectManager {
    constructor() {
        this.init();
    }
    init() {
        this.bindEvents();
        this.loadProjects();
    }
    bindEvents() {
        const projectForm = document.getElementById('projectForm');
        if (projectForm) {
            projectForm.addEventListener('submit', (e) => this.handleProjectSubmit(e));
        }
        // Los listeners de búsqueda y filtro están en la parte global
    }
    async loadProjects() {
        try {
            const response = await fetch('../api/projects.php?action=list');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.success) {
                this.renderProjects(data.projects);
                this.filterProjects(); // Para mantener filtro tras recarga AJAX
            } else {
                console.warn('API no disponible, usando datos del servidor');
                showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error loading projects:', error);
            // Solo mostrar error si no hay tarjetas
            if (document.querySelectorAll('#projectsContainer .card').length === 0) {
                showAlert('Error al cargar proyectos', 'error');
            }
        }
    }
    renderProjects(projects) {
        const container = document.getElementById('projectsContainer');
        if (!container) return;
        container.innerHTML = '';
        if (projects.length === 0) {
            container.innerHTML = '<p class="text-center">No hay proyectos para mostrar</p>';
            return;
        }
        projects.forEach(project => {
            const projectCard = this.createProjectCard(project);
            container.appendChild(projectCard);
        });
    }
    createProjectCard(project) {
        const card = document.createElement('div');
        card.className = 'card';
        card.dataset.projectId = project.id;
        const statusText = statusMap[project.status] || project.status;
        const taskCount = project.task_count || 0;
        const completedTasks = project.completed_tasks || 0;
        const progress = taskCount > 0 ? Math.round((completedTasks / taskCount) * 100) : 0;
        card.innerHTML = `
            <div class="card-header">
                <h3 style="margin: 0; color: #2c3e50;">${this.escapeHtml(project.name)}</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-sm btn-primary" onclick="projectManager.editProject(${project.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="projectManager.deleteProject(${project.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div style="padding: 1rem;">
                <p style="color: #6b7280; margin-bottom: 1rem;">${this.escapeHtml(project.description || 'Sin descripción')}</p>
                <div style="margin-bottom: 1rem;">
                    <span class="status-badge status-${project.status}">${statusText}</span>
                </div>
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.875rem; color: #6b7280;">Progreso</span>
                        <span style="font-size: 0.875rem; font-weight: bold;">${progress}%</span>
                    </div>
                    <div style="background-color: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="background-color: #10b981; height: 100%; width: ${progress}%; transition: width 0.3s;"></div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.875rem; color: #6b7280;">
                    <div><strong>Total tareas:</strong> ${taskCount}</div>
                    <div><strong>Completadas:</strong> ${completedTasks}</div>
                    <div><strong>Creado:</strong> ${new Date(project.created_at).toLocaleDateString('es-ES')}</div>
                    <div><strong>Creador:</strong> ${this.escapeHtml(project.creator_name || 'N/A')}</div>
                </div>
            </div>
        `;
        return card;
    }
    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }
    async handleProjectSubmit(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const projectData = Object.fromEntries(formData.entries());
        const action = projectData.project_id && projectData.project_id !== '' ? 'update' : 'create';
        try {
            const response = await fetch('../api/projects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...projectData })
            });
            const data = await response.json();
            if (data.success) {
                showAlert(data.message, 'success');
                this.closeModal();
                this.loadProjects();
                e.target.reset();
            } else {
                showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error al procesar proyecto', 'error');
        }
    }
    async editProject(projectId) {
        try {
            const response = await fetch(`../api/projects.php?action=get&id=${projectId}`);
            const data = await response.json();
            if (data.success) {
                this.populateProjectForm(data.project);
                this.openModal();
            } else {
                showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error al cargar proyecto', 'error');
        }
    }
    async deleteProject(projectId) {
        if (!confirm('¿Estás seguro de que quieres eliminar este proyecto? Se eliminarán también todas sus tareas.')) {
            return;
        }
        try {
            const response = await fetch('../api/projects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', project_id: projectId })
            });
            const data = await response.json();
            if (data.success) {
                showAlert(data.message, 'success');
                this.loadProjects();
            } else {
                showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Error al eliminar proyecto', 'error');
        }
    }
    populateProjectForm(project) {
        const form = document.getElementById('projectForm');
        if (!form) return;
        form.querySelector('[name="project_id"]').value = project.id;
        form.querySelector('[name="name"]').value = project.name;
        form.querySelector('[name="description"]').value = project.description || '';
        form.querySelector('[name="status"]').value = project.status;
        document.getElementById('projectModalTitle').textContent = 'Editar Proyecto';
    }
    openModal() {
        const modal = document.getElementById('projectModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }
    closeModal() {
        const modal = document.getElementById('projectModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            const form = document.getElementById('projectForm');
            if (form) {
                form.reset();
                form.querySelector('[name="project_id"]').value = '';
                document.getElementById('projectModalTitle').textContent = 'Nuevo Proyecto';
            }
        }
    }
    filterProjects() {
        const searchTerm = document.getElementById('projectSearch').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const projects = document.querySelectorAll('#projectsContainer .card');
        projects.forEach(project => {
            const name = project.querySelector('h3').textContent.toLowerCase();
            const description = project.querySelector('p').textContent.toLowerCase();
            const statusBadge = project.querySelector('.status-badge').className;
            const matchesSearch = name.includes(searchTerm) || description.includes(searchTerm);
            const matchesStatus = !status || statusBadge.includes(status);
            project.style.display = (matchesSearch && matchesStatus) ? 'block' : 'none';
        });
    }
}

// Inicialización global del manager
window.projectManager = new ProjectManager();

// Función global para el botón "Nuevo Proyecto"
function openProjectModal() {
    projectManager.openModal();
}

// Función global para el botón cancelar
function closeProjectModal() {
    projectManager.closeModal();
}

// Listener para cerrar modal con la X
document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.onclick = () => projectManager.closeModal();
    });
    // Listeners de búsqueda/filtro
    document.getElementById('projectSearch').addEventListener('input', () => projectManager.filterProjects());
    document.getElementById('statusFilter').addEventListener('change', () => projectManager.filterProjects());
});
</script>
<?php 
// Limpiar buffer de salida antes del footer
ob_end_flush();
include '../includes/footer.php'; 
?>