<?php
// Iniciar buffer de salida para evitar problemas con headers
ob_start();
require_once '../config/database.php';
requireLogin();

$page_title = 'Mi Perfil';
$success_message = '';
$error_message = '';

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($full_name) || empty($email)) {
            $error_message = 'El nombre y email son obligatorios';
        } else {
            // Verificar si el email ya existe (excepto el actual)
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error_message = 'El email ya está en uso por otro usuario';
            } else {
                $update_fields = ['full_name = ?', 'email = ?'];
                $params = [$full_name, $email];
                
                // Si se proporcionó contraseña actual, validar cambio de contraseña
                if (!empty($current_password)) {
                    if (!password_verify($current_password, $user['password'])) {
                        $error_message = 'La contraseña actual es incorrecta';
                    } elseif (empty($new_password) || $new_password !== $confirm_password) {
                        $error_message = 'La nueva contraseña no coincide con la confirmación';
                    } elseif (strlen($new_password) < 6) {
                        $error_message = 'La nueva contraseña debe tener al menos 6 caracteres';
                    } else {
                        $update_fields[] = 'password = ?';
                        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                    }
                }
                
                if (empty($error_message)) {
                    $params[] = $_SESSION['user_id'];
                    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    
                    if ($stmt->execute($params)) {
                        $_SESSION['user_name'] = $full_name;
                        $success_message = 'Perfil actualizado correctamente';
                        
                        // Recargar datos del usuario
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $user = $stmt->fetch();
                    } else {
                        $error_message = 'Error al actualizar el perfil';
                    }
                }
            }
        }
    }
    
    // Estadísticas del usuario
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_assigned,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress
        FROM tasks 
        WHERE assigned_to = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_stats = $stmt->fetch();
    
    // Si no hay estadísticas, inicializar con ceros
    if (!$user_stats) {
        $user_stats = [
            'total_assigned' => 0,
            'completed' => 0,
            'pending' => 0,
            'in_progress' => 0
        ];
    }
    
} catch (Exception $e) {
    $error_message = 'Error al cargar el perfil: ' . $e->getMessage();
}

// Incluir header después de procesar la lógica
include '../includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title"><i class="fas fa-user"></i> Mi Perfil</h1>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username" class="form-label">Usuario</label>
                <input type="text" id="username" class="form-control" 
                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small style="color: #6b7280;">El nombre de usuario no se puede cambiar</small>
            </div>
            
            <div class="form-group">
                <label for="full_name" class="form-label">Nombre Completo *</label>
                <input type="text" id="full_name" name="full_name" class="form-control" 
                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">Email *</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="role" class="form-label">Rol</label>
                <input type="text" id="role" class="form-control" 
                       value="<?php echo ucfirst($user['role']); ?>" disabled>
            </div>
            
            <hr style="margin: 2rem 0;">
            <h3 style="margin-bottom: 1rem; color: #374151;">Cambiar Contraseña</h3>
            
            <div class="form-group">
                <label for="current_password" class="form-label">Contraseña Actual</label>
                <input type="password" id="current_password" name="current_password" class="form-control">
                <small style="color: #6b7280;">Déjalo vacío si no quieres cambiar la contraseña</small>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="new_password" class="form-label">Nueva Contraseña</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                </div>
            </div>
            
            <div style="margin-top: 2rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Actualizar Perfil
                </button>
            </div>
        </form>
    </div>
    
    <!-- Panel de estadísticas -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-bar"></i> Mis Estadísticas</h2>
        </div>
        
        <div style="text-align: center;">
            <div style="margin-bottom: 1.5rem;">
                <div class="stat-number" style="color: #667eea;"><?php echo $user_stats['total_assigned']; ?></div>
                <div class="stat-label">Tareas Asignadas</div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <div class="stat-number" style="color: #10b981;"><?php echo $user_stats['completed']; ?></div>
                <div class="stat-label">Completadas</div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <div class="stat-number" style="color: #3b82f6;"><?php echo $user_stats['in_progress']; ?></div>
                <div class="stat-label">En Progreso</div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <div class="stat-number" style="color: #f59e0b;"><?php echo $user_stats['pending']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
        </div>
        
        <hr>
        
        <div style="color: #6b7280; font-size: 0.875rem;">
            <p><strong>Miembro desde:</strong><br>
            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            
            <p><strong>Última actualización:</strong><br>
            <?php echo date('d/m/Y H:i', strtotime($user['updated_at'])); ?></p>
        </div>
    </div>
</div>

<?php 
// Limpiar buffer de salida antes del footer
ob_end_flush();
include '../includes/footer.php'; 
?>