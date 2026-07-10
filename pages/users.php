<?php
ob_start();
require_once '../config/database.php';
requireLogin();

// Solo administradores pueden acceder
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Gestión de Usuarios';

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1 class="card-title"><i class="fas fa-users"></i> Gestión de Usuarios</h1>
        <button class="btn btn-primary" onclick="openUserModal()">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </button>
    </div>
    
    <!-- Filtros y búsqueda -->
    <div class="filters" style="display: grid; grid-template-columns: 1fr 200px; gap: 1rem; margin-bottom: 1.5rem;">
        <div class="form-group">
            <input type="text" id="userSearch" class="form-control" placeholder="Buscar usuarios...">
        </div>
        <div class="form-group">
            <select id="roleFilter" class="form-control">
                <option value="">Todos los roles</option>
                <option value="admin">Administrador</option>
                <option value="member">Miembro</option>
            </select>
        </div>
    </div>
    
    <!-- Tabla de usuarios -->
    <div class="table-container" style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                    <th style="padding: 1rem; text-align: left;">Usuario</th>
                    <th style="padding: 1rem; text-align: left;">Email</th>
                    <th style="padding: 1rem; text-align: left;">Rol</th>
                    <th style="padding: 1rem; text-align: left;">Tareas</th>
                    <th style="padding: 1rem; text-align: left;">Creado</th>
                    <th style="padding: 1rem; text-align: left;">Acciones</th>
                </tr>
            </thead>
            <tbody id="usersContainer">
                <tr>
                    <td colspan="6" style="padding: 2rem; text-align: center; color: #6b7280;">
                        Cargando usuarios...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para crear/editar usuarios -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="userModalTitle">Nuevo Usuario</h2>
            <span class="close close-modal">&times;</span>
        </div>
        
        <form id="userForm">
            <input type="hidden" name="user_id" value="">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="user_username" class="form-label">Usuario *</label>
                    <input type="text" id="user_username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="user_email" class="form-label">Email *</label>
                    <input type="email" id="user_email" name="email" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="user_full_name" class="form-label">Nombre Completo *</label>
                <input type="text" id="user_full_name" name="full_name" class="form-control" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="user_role" class="form-label">Rol</label>
                    <select id="user_role" name="role" class="form-control">
                        <option value="member">Miembro</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label for="user_password" class="form-label">Contraseña *</label>
                    <input type="password" id="user_password" name="password" class="form-control" required>
                    <small style="color: #6b7280;">Mínimo 6 caracteres</small>
                </div>
            </div>
            
            <div class="form-group" id="newPasswordGroup" style="display: none;">
                <label for="new_password" class="form-label">Nueva Contraseña</label>
                <input type="password" id="new_password" name="new_password" class="form-control">
                <small style="color: #6b7280;">Déjalo vacío si no quieres cambiar la contraseña</small>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn" onclick="closeUserModal()" style="background-color: #6b7280; color: white;">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    Guardar Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.table {
    background-color: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table th {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.table tr:hover {
    background-color: #f9fafb;
}
</style>

<script>
console.log('🚀 Starting Users Page - Simplified Version');

// Variables globales
let usersData = [];

// Función principal para cargar usuarios
async function loadUsers() {
    console.log('📡 Loading users from API...');
    
    const container = document.getElementById('usersContainer');
    if (!container) {
        console.error('❌ usersContainer not found!');
        return;
    }
    
    try {
        const response = await fetch('../api/users.php?action=list');
        console.log('📡 API Response Status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        console.log('📊 API Data:', data);
        
        if (data.success && data.users) {
            usersData = data.users;
            console.log(`✅ Found ${data.users.length} users`);
            renderUsers(data.users);
        } else {
            throw new Error(data.message || 'API returned success: false');
        }
        
    } catch (error) {
        console.error('❌ Error loading users:', error);
        container.innerHTML = `
            <tr>
                <td colspan="6" style="padding: 2rem; text-align: center; color: #dc2626;">
                    Error: ${error.message}
                </td>
            </tr>
        `;
    }
}

// Función para renderizar usuarios
function renderUsers(users) {
    console.log('🎨 Rendering users:', users);
    
    const container = document.getElementById('usersContainer');
    if (!container) {
        console.error('❌ usersContainer not found in renderUsers!');
        return;
    }
    
    if (!users || users.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="6" style="padding: 2rem; text-align: center; color: #6b7280;">
                    No hay usuarios para mostrar
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    
    users.forEach((user, index) => {
        const roleText = user.role === 'admin' ? 'Administrador' : 'Miembro';
        const roleColor = user.role === 'admin' ? '#dc2626' : '#059669';
        const taskCount = user.total_tasks || 0;
        const completedTasks = user.completed_tasks || 0;
        
        html += `
            <tr style="border-bottom: 1px solid #e5e7eb;" data-user-id="${user.id}" data-user-role="${user.role}">
                <td style="padding: 1rem;">
                    <div>
                        <div style="font-weight: 600; color: #1f2937;">${escapeHtml(user.full_name)}</div>
                        <div style="font-size: 0.875rem; color: #6b7280;">@${escapeHtml(user.username)}</div>
                    </div>
                </td>
                <td style="padding: 1rem;">
                    <div style="color: #374151;">${escapeHtml(user.email)}</div>
                </td>
                <td style="padding: 1rem;">
                    <span style="background-color: ${roleColor}; color: white; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;">
                        ${roleText}
                    </span>
                </td>
                <td style="padding: 1rem;">
                    <div style="font-size: 0.875rem; color: #374151;">
                        Total: ${taskCount}<br>
                        Completadas: ${completedTasks}
                    </div>
                </td>
                <td style="padding: 1rem;">
                    <div style="font-size: 0.875rem; color: #6b7280;">
                        ${new Date(user.created_at).toLocaleDateString('es-ES')}
                    </div>
                </td>
                <td style="padding: 1rem;">
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    container.innerHTML = html;
    console.log('✅ Users rendered successfully');
}

// Función para escapar HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Funciones de modal
function openUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Reset form
        const form = document.getElementById('userForm');
        if (form) {
            form.reset();
            form.querySelector('[name="user_id"]').value = '';
            
            // Restaurar vista de nuevo usuario
            const passwordGroup = document.getElementById('passwordGroup');
            const newPasswordGroup = document.getElementById('newPasswordGroup');
            const passwordField = document.getElementById('user_password');
            const modalTitle = document.getElementById('userModalTitle');
            
            if (modalTitle) modalTitle.textContent = 'Nuevo Usuario';
            if (passwordGroup) passwordGroup.style.display = 'block';
            if (newPasswordGroup) newPasswordGroup.style.display = 'none';
            if (passwordField) passwordField.setAttribute('required', 'required');
        }
    }
}

// Función de editar usuario
async function editUser(userId) {
    try {
        const response = await fetch(`../api/users.php?action=get&id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            populateUserForm(data.user);
            openUserModal();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al cargar usuario: ' + error.message);
    }
}

// Función de eliminar usuario
async function deleteUser(userId) {
    if (!confirm('¿Estás seguro de que quieres eliminar este usuario?')) {
        return;
    }

    try {
        const response = await fetch('../api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', user_id: userId })
        });

        const data = await response.json();
        if (data.success) {
            alert('Usuario eliminado correctamente');
            loadUsers();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al eliminar usuario: ' + error.message);
    }
}

// Función para poblar el formulario
function populateUserForm(user) {
    const form = document.getElementById('userForm');
    if (!form) return;

    form.querySelector('[name="user_id"]').value = user.id;
    form.querySelector('[name="username"]').value = user.username;
    form.querySelector('[name="email"]').value = user.email;
    form.querySelector('[name="full_name"]').value = user.full_name;
    form.querySelector('[name="role"]').value = user.role;
    
    // Ocultar campo password requerido y mostrar campo opcional
    const passwordGroup = document.getElementById('passwordGroup');
    const newPasswordGroup = document.getElementById('newPasswordGroup');
    const passwordField = document.getElementById('user_password');
    
    if (passwordGroup) passwordGroup.style.display = 'none';
    if (newPasswordGroup) newPasswordGroup.style.display = 'block';
    if (passwordField) passwordField.removeAttribute('required');
    
    document.getElementById('userModalTitle').textContent = 'Editar Usuario';
}

// Función para enviar formulario
async function handleUserSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const userData = Object.fromEntries(formData.entries());
    const action = userData.user_id && userData.user_id !== '' ? 'update' : 'create';

    if (action === 'create' && (!userData.password || userData.password.length < 6)) {
        alert('La contraseña debe tener al menos 6 caracteres');
        return;
    }

    try {
        const response = await fetch('../api/users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...userData })
        });

        const data = await response.json();
        if (data.success) {
            alert(data.message);
            closeUserModal();
            loadUsers();
            e.target.reset();
        } else {
            if (data.errors && data.errors.length > 0) {
                alert(data.errors.join(', '));
            } else {
                alert(data.message);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al procesar usuario: ' + error.message);
    }
}

// Función de filtrado
function filterUsers(searchTerm) {
    const users = document.querySelectorAll('#usersContainer tr');
    users.forEach(user => {
        const name = user.cells[0]?.textContent.toLowerCase() || '';
        const email = user.cells[1]?.textContent.toLowerCase() || '';
        const isVisible = name.includes(searchTerm.toLowerCase()) || 
                         email.includes(searchTerm.toLowerCase());
        user.style.display = isVisible ? '' : 'none';
    });
}

function filterUsersByRole(role) {
    const users = document.querySelectorAll('#usersContainer tr');
    users.forEach(user => {
        const userRole = user.dataset.userRole;
        user.style.display = (role === '' || userRole === role) ? '' : 'none';
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 DOM loaded, setting up event listeners...');
    
    // Cargar usuarios inmediatamente
    loadUsers();
    
    // Formulario
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', handleUserSubmit);
    }
    
    // Búsqueda
    const searchInput = document.getElementById('userSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => filterUsers(e.target.value));
    }
    
    // Filtro de rol
    const roleFilter = document.getElementById('roleFilter');
    if (roleFilter) {
        roleFilter.addEventListener('change', (e) => filterUsersByRole(e.target.value));
    }
    
    // Cerrar modal
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal') || e.target.classList.contains('close-modal')) {
            closeUserModal();
        }
    });
});

// Ejecutar inmediatamente si ya está cargado
if (document.readyState !== 'loading') {
    console.log('📋 DOM already ready, loading immediately...');
    setTimeout(loadUsers, 100);
}
</script>

<?php 
ob_end_flush();
include '../includes/footer.php'; 
?>