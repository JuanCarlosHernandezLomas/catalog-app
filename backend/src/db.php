<?php

function db() : PDO {
  $host = "127.0.0.1";
  $port = "3306";
  $db   = "catalogo";
  $user = "root";
  $pass = ""; // XAMPP por defecto

  $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  return $pdo;
}
