## 1. Base de Datos (database.sql)

-- Crear base de datos
CREATE DATABASE task_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE task_manager;

-- Tabla de usuarios
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'member') DEFAULT 'member',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Tabla de proyectos
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'completed', 'on_hold') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    UNIQUE KEY unique_project_name (name)
);

-- Tabla de tareas
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    project_id INT,
    assigned_to INT,
    created_by INT,
    due_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_due_date (due_date)
);

-- Tabla de comentarios
CREATE TABLE task_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT,
    user_id INT,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_task_comments (task_id)
);

-- Tabla de notificaciones (opcional para futuras mejoras)
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_notifications (user_id, is_read)
);

-- Datos de ejemplo
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@taskmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin'),
('juan.perez', 'juan@taskmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Pérez', 'member'),
('maria.garcia', 'maria@taskmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'María García', 'member'),
('carlos.rodriguez', 'carlos@taskmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carlos Rodríguez', 'member'),
('ana.martinez', 'ana@taskmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ana Martínez', 'member');

INSERT INTO projects (name, description, created_by) VALUES
('Desarrollo Web', 'Proyecto de desarrollo de sitio web corporativo', 1),
('Marketing Digital', 'Campaña de marketing en redes sociales', 1),
('Sistema de Inventario', 'Desarrollo de sistema de gestión de inventario', 1),
('Aplicación Móvil', 'Desarrollo de aplicación móvil para clientes', 1);

INSERT INTO tasks (title, description, status, priority, project_id, assigned_to, created_by, due_date) VALUES
('Diseñar mockups', 'Crear diseños iniciales para la página principal', 'pending', 'high', 1, 2, 1, '2024-12-01'),
('Configurar servidor', 'Instalar y configurar servidor de desarrollo', 'in_progress', 'medium', 1, 3, 1, '2024-11-25'),
('Desarrollar frontend', 'Implementar la interfaz de usuario', 'pending', 'high', 1, 2, 1, '2024-12-15'),
('Crear base de datos', 'Diseñar y crear estructura de base de datos', 'completed', 'high', 1, 4, 1, '2024-11-20'),
('Crear contenido', 'Redactar contenido para redes sociales', 'pending', 'medium', 2, 2, 1, '2024-11-30'),
('Diseñar gráficos', 'Crear material gráfico para campaña', 'in_progress', 'medium', 2, 5, 1, '2024-12-05'),
('Análisis de requisitos', 'Analizar requisitos del sistema de inventario', 'pending', 'urgent', 3, 3, 1, '2024-11-28'),
('Prototipo móvil', 'Crear prototipo inicial de la aplicación', 'pending', 'high', 4, 4, 1, '2024-12-10');

-- Comentarios de ejemplo
INSERT INTO task_comments (task_id, user_id, comment) VALUES
(1, 2, 'He comenzado con los wireframes iniciales'),
(2, 3, 'El servidor está configurado al 80%'),
(4, 4, 'Base de datos completada y optimizada'),
(6, 5, 'Primer borrador de gráficos listo para revisión');

-- Crear índices adicionales para optimizar consultas
CREATE INDEX idx_tasks_project_status ON tasks(project_id, status);
CREATE INDEX idx_tasks_assigned_status ON tasks(assigned_to, status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_projects_status ON projects(status);

-- Vista para estadísticas rápidas
CREATE VIEW task_stats AS
SELECT 
    u.id as user_id,
    u.full_name,
    COUNT(t.id) as total_tasks,
    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN t.due_date < CURDATE() AND t.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks
FROM users u
LEFT JOIN tasks t ON u.id = t.assigned_to
GROUP BY u.id, u.full_name;