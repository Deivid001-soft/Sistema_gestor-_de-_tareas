class TaskManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadTasks();
    }

    bindEvents() {
        // Modal events
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close-modal') || e.target.classList.contains('modal')) {
                this.closeModal();
            }
        });

        // Form submissions
        const taskForm = document.getElementById('taskForm');
        if (taskForm) {
            taskForm.addEventListener('submit', (e) => this.handleTaskSubmit(e));
        }

        // Task status updates
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('task-status-select')) {
                this.updateTaskStatus(e.target.dataset.taskId, e.target.value);
            }
        });

        // Search and filters
        const searchInput = document.getElementById('taskSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.filterTasks(e.target.value));
        }

        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => this.filterTasksByStatus(e.target.value));
        }
    }

    // Modal management
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    closeModal() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto';
    }

    // Task operations
    async loadTasks() {
        try {
            // Detectar la ruta correcta basada en la ubicación actual
            const apiPath = window.location.pathname.includes('/pages/') ? '../api/tasks.php' : 'api/tasks.php';
            
            const response = await fetch(apiPath + '?action=list');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Response is not JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            }

            const data = await response.json();
            
            if (data.success) {
                this.renderTasks(data.tasks);
                this.updateDashboardStats(data.tasks);
            } else {
                console.error('API Error:', data.message);
                this.showAlert(data.message || 'Error al cargar las tareas', 'error');
            }
        } catch (error) {
            console.error('Error loading tasks:', error);
            this.showAlert('Error al cargar las tareas: ' + error.message, 'error');
            
            // Mostrar mensaje en el contenedor si existe
            const container = document.getElementById('tasksContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-error">Error al cargar las tareas. Por favor, recarga la página.</div>';
            }
        }
    }

    renderTasks(tasks) {
        const container = document.getElementById('tasksContainer');
        if (!container) return;

        container.innerHTML = '';

        if (tasks.length === 0) {
            container.innerHTML = '<p class="text-center">No hay tareas para mostrar</p>';
            return;
        }

        tasks.forEach(task => {
            const taskCard = this.createTaskCard(task);
            container.appendChild(taskCard);
        });
    }

    createTaskCard(task) {
        const card = document.createElement('div');
        card.className = `task-card priority-${task.priority}`;
        card.dataset.taskId = task.id;

        const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString('es-ES') : 'Sin fecha';
        const isOverdue = task.due_date && new Date(task.due_date) < new Date() && task.status !== 'completed';

        card.innerHTML = `
            <div class="task-header">
                <h3 class="task-title">${this.escapeHtml(task.title)}</h3>
                <div class="task-actions">
                    <button class="btn btn-sm btn-primary" onclick="taskManager.editTask(${task.id})">
                        Editar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="taskManager.deleteTask(${task.id})">
                        Eliminar
                    </button>
                </div>
            </div>
            <p class="task-description">${this.escapeHtml(task.description || 'Sin descripción')}</p>
            <div class="task-meta">
                <div class="task-info">
                    <span class="status-badge status-${task.status}">${this.getStatusText(task.status)}</span>
                    <span class="priority-badge priority-${task.priority}">${this.getPriorityText(task.priority)}</span>
                </div>
                <div class="task-details">
                    <p><strong>Asignado a:</strong> ${task.assigned_name || 'No asignado'}</p>
                    <p class="${isOverdue ? 'text-danger' : ''}"><strong>Fecha límite:</strong> ${dueDate}</p>
                    <p><strong>Proyecto:</strong> ${task.project_name || 'Sin proyecto'}</p>
                </div>
            </div>
            <div class="task-status-update">
                <select class="form-control task-status-select" data-task-id="${task.id}">
                    <option value="pending" ${task.status === 'pending' ? 'selected' : ''}>Pendiente</option>
                    <option value="in_progress" ${task.status === 'in_progress' ? 'selected' : ''}>En Progreso</option>
                    <option value="completed" ${task.status === 'completed' ? 'selected' : ''}>Completada</option>
                    <option value="cancelled" ${task.status === 'cancelled' ? 'selected' : ''}>Cancelada</option>
                </select>
            </div>
        `;

        return card;
    }

    async handleTaskSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const taskData = Object.fromEntries(formData.entries());
        
        // Agregar la acción basada en si existe task_id
        const action = taskData.task_id && taskData.task_id !== '' ? 'update' : 'create';
        
        // Preparar el payload
        const payload = {
            action: action,
            ...taskData
        };
        
        // Limpiar valores vacíos
        Object.keys(payload).forEach(key => {
            if (payload[key] === '' && key !== 'description') {
                delete payload[key];
            }
        });

        console.log('Sending payload:', payload); // Para debugging

        try {
            // Detectar la ruta correcta
            const apiPath = window.location.pathname.includes('/pages/') ? '../api/tasks.php' : 'api/tasks.php';
            
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            // Verificar si la respuesta es válida
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Response is not JSON:', text);
                throw new Error('La respuesta del servidor no es JSON válido');
            }

            const data = await response.json();
            
            if (data.success) {
                this.showAlert(data.message, 'success');
                this.closeModal();
                this.loadTasks();
                e.target.reset();
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error al procesar la tarea: ' + error.message, 'error');
        }
    }

    async updateTaskStatus(taskId, newStatus) {
        try {
            const apiPath = window.location.pathname.includes('/pages/') ? '../api/tasks.php' : 'api/tasks.php';
            
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'updateStatus',
                    task_id: taskId,
                    status: newStatus
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Estado actualizado correctamente', 'success');
                this.loadTasks();
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error al actualizar el estado: ' + error.message, 'error');
        }
    }

    async deleteTask(taskId) {
        if (!confirm('¿Estás seguro de que quieres eliminar esta tarea?')) {
            return;
        }

        try {
            const apiPath = window.location.pathname.includes('/pages/') ? '../api/tasks.php' : 'api/tasks.php';
            
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    task_id: taskId
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Tarea eliminada correctamente', 'success');
                this.loadTasks();
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error al eliminar la tarea: ' + error.message, 'error');
        }
    }

    async editTask(taskId) {
        try {
            const apiPath = window.location.pathname.includes('/pages/') ? '../api/tasks.php' : 'api/tasks.php';
            
            const response = await fetch(`${apiPath}?action=get&id=${taskId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                this.populateTaskForm(data.task);
                this.openModal('taskModal');
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showAlert('Error al cargar la tarea: ' + error.message, 'error');
        }
    }

    populateTaskForm(task) {
        const form = document.getElementById('taskForm');
        if (!form) return;

        form.querySelector('[name="task_id"]').value = task.id;
        form.querySelector('[name="title"]').value = task.title;
        form.querySelector('[name="description"]').value = task.description || '';
        form.querySelector('[name="status"]').value = task.status;
        form.querySelector('[name="priority"]').value = task.priority;
        form.querySelector('[name="project_id"]').value = task.project_id || '';
        form.querySelector('[name="assigned_to"]').value = task.assigned_to || '';
        form.querySelector('[name="due_date"]').value = task.due_date || '';
    }

    // Filter functions
    filterTasks(searchTerm) {
        const tasks = document.querySelectorAll('.task-card');
        tasks.forEach(task => {
            const title = task.querySelector('.task-title').textContent.toLowerCase();
            const description = task.querySelector('.task-description').textContent.toLowerCase();
            const isVisible = title.includes(searchTerm.toLowerCase()) || 
                             description.includes(searchTerm.toLowerCase());
            task.style.display = isVisible ? 'block' : 'none';
        });
    }

    filterTasksByStatus(status) {
        const tasks = document.querySelectorAll('.task-card');
        tasks.forEach(task => {
            if (status === '' || task.querySelector('.task-status-select').value === status) {
                task.style.display = 'block';
            } else {
                task.style.display = 'none';
            }
        });
    }

    // Dashboard statistics
    updateDashboardStats(tasks) {
        const stats = {
            total: tasks.length,
            pending: tasks.filter(t => t.status === 'pending').length,
            in_progress: tasks.filter(t => t.status === 'in_progress').length,
            completed: tasks.filter(t => t.status === 'completed').length,
            overdue: tasks.filter(t => t.due_date && new Date(t.due_date) < new Date() && t.status !== 'completed').length
        };

        Object.keys(stats).forEach(key => {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    }

    // Utility functions
    getStatusText(status) {
        const statusMap = {
            'pending': 'Pendiente',
            'in_progress': 'En Progreso',
            'completed': 'Completada',
            'cancelled': 'Cancelada'
        };
        return statusMap[status] || status;
    }

    getPriorityText(priority) {
        const priorityMap = {
            'low': 'Baja',
            'medium': 'Media',
            'high': 'Alta',
            'urgent': 'Urgente'
        };
        return priorityMap[priority] || priority;
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alertContainer') || this.createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        
        alertContainer.appendChild(alert);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    createAlertContainer() {
        const container = document.createElement('div');
        container.id = 'alertContainer';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
        return container;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.taskManager = new TaskManager();
});

// Additional utility functions
function openTaskModal() {
    // Reset form
    const form = document.getElementById('taskForm');
    if (form) {
        form.reset();
        form.querySelector('[name="task_id"]').value = '';
    }
    taskManager.openModal('taskModal');
}

function logout() {
    if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
        // Hacer petición AJAX para logout
        fetch('../api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'logout'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'login.php';
            } else {
                alert('Error al cerrar sesión: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Fallback: redirigir directamente
            window.location.href = '../api/auth.php?action=logout';
        });
    }
}