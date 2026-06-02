<?php

class Database
{
    private static ?mysqli $instance = null;

    public static function getInstance(): mysqli
    {
        if (self::$instance === null) {
            $cfg = require APP_PATH . '/config/database.php';
            self::$instance = new mysqli(
                $cfg['host'],
                $cfg['username'],
                $cfg['password'],
                $cfg['dbname']
            );
            if (self::$instance->connect_error) {
                die('Kết nối thất bại: ' . self::$instance->connect_error);
            }
            self::$instance->set_charset($cfg['charset']);
        }
        return self::$instance;
    }
}

/** Biến $conn tương thích code cũ */
$conn = Database::getInstance();
