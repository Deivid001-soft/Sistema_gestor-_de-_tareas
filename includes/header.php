<?php
if (!isset($page_title)) $page_title = 'Task Manager';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestión de Tareas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-tasks"></i> TaskManager
                </div>
                <nav>
                    <ul class="nav-menu">
                        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="tasks.php"><i class="fas fa-list-check"></i> Tareas</a></li>
                        <?php if (isAdmin()): ?>
                        <li><a href="projects.php"><i class="fas fa-project-diagram"></i> Proyectos</a></li>
                        <li><a href="users.php"><i class="fas fa-users"></i> Usuarios</a></li>
                        <?php endif; ?>
                        <li><a href="profile.php"><i class="fas fa-user"></i> Perfil</a></li>
                    </ul>
                </nav>
                <div class="user-info">
                    <span>Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></span>
                    <button class="btn btn-sm" onclick="logout()" style="background-color: rgba(255,255,255,0.2); color: white;">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesion
                    </button>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <main class="main-content">
        <div class="container">