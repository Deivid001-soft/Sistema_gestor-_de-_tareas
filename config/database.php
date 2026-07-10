<?php
class Database {
    private $host = 'mysql-1df33cee-gestor-tareas02222.f.aivencloud.com: 25368';
    private $db_name = 'defaultdb';
    private $username = 'avnadmin';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            // En producción, no mostrar el error real
            die("Error de conexión a la base de datos");
        }
        
        return $this->conn;
    }
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Funciones de utilidad
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function isAdmin() {
    return getUserRole() === 'admin';
}
?>
