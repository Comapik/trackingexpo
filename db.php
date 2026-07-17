<?php
// Connexion MySQL (MAMP) + création auto de la base et des tables si besoin.
// Identifiants par défaut MAMP : root / root, socket ou port 8889.

date_default_timezone_set('Europe/Paris');

$DB_HOST = '127.0.0.1';
$DB_PORT = '8889';
$DB_SOCK = '/Applications/MAMP/tmp/mysql/mysql.sock';
$DB_USER = 'root';
$DB_PASS = 'root';
$DB_NAME = 'TrackingExpo';

function getPDO(): PDO
{
    global $DB_HOST, $DB_PORT, $DB_SOCK, $DB_USER, $DB_PASS, $DB_NAME;

    $dsnNoDb = is_file($DB_SOCK)
        ? "mysql:unix_socket=$DB_SOCK;charset=utf8mb4"
        : "mysql:host=$DB_HOST;port=$DB_PORT;charset=utf8mb4";

    $pdo = new PDO($dsnNoDb, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$DB_NAME`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS visits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        count INT NOT NULL DEFAULT 1,
        visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product VARCHAR(50) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        price DECIMAL(6,2) NULL,
        sold_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS day_weather (
        day DATE PRIMARY KEY,
        weather VARCHAR(20) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    return $pdo;
}

const PRODUCTS = [
    'tirage_photo' => 'Tirage photo',
    'carte_postale' => 'Carte postale',
    'magnet' => 'Magnet',
    'tot_bag' => 'Tot bag',
    'mug' => 'Mug',
    'marque_page' => 'Marque-page',
    'carreau_faience' => 'Carreaux de faïence',
    'affiche' => 'Affiche',
];

const WEATHER = [
    'pluie' => ['label' => 'Pluie', 'icon' => '🌧️'],
    'nuage' => ['label' => 'Nuage', 'icon' => '☁️'],
    'soleil' => ['label' => 'Soleil', 'icon' => '☀️'],
    'canicule' => ['label' => 'Canicule', 'icon' => '🥵'],
];
