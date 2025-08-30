<?php
// =============================================
// config.php - Database Configuration
// =============================================

class Database {
    private $host = 'localhost';
    private $database = 'spicyip_gallery';
    private $username = 'your_db_username';
    private $password = 'your_db_password';
    private $charset = 'utf8mb4';
    
    public function connect() {
        try {
            $dsn = "mysql:host=$this->host;dbname=$this->database;charset=$this->charset";
            $pdo = new PDO($dsn, $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}
