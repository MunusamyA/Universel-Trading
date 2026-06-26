<?php

require_once __DIR__ . '/Env.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        Env::load(dirname(__DIR__) . '/.env');

        $host = Env::get('DB_HOST', 'localhost');
        $dbname = Env::get('DB_NAME');
        $username = Env::get('DB_USER', 'root');
        $password = Env::get('DB_PASS', '');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        if (!$dbname) {
            die('Database name is missing in .env file');
        }

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

        try {
            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function __clone()
    {
    }
}