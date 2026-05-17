<?php
require_once __DIR__ . '/ejecutar_migraciones_bds.php';
date_default_timezone_set('America/La_Paz');

class Database {
    private $host = 'localhost';
    private $db_name = 'wiredcom_uni3t';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO('mysql:host=' . $this->host . ';dbname=' . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo 'Error en la conexión: ' . $e->getMessage();
        }
        return $this->conn;
    }
}
?>
