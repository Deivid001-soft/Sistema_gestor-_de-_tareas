<?php
class Database {
    private $host = 'mysql-1df33cee-gestor-tareas02222.f.aivencloud.com';
    private $port = '25368';
    private $db_name = 'defaultdb';
    private $username = 'avnadmin';
    // Dejamos la propiedad vacía aquí por seguridad
    private $password; 
    private $conn;

    public function connect() {
        $this->conn = null;
        
        // Leemos la contraseña desde las variables de entorno del servidor.
        // Si estás en tu computadora local, puedes poner tu contraseña local a la derecha del ?:
        $this->password = getenv('DB_PASSWORD') ?: ''; 

        try {
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
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
