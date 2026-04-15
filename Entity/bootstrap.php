<?php
// bootstrap.php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

require_once __DIR__ . "/../vendor/autoload.php";

$config = ORMSetup::createAttributeMetadataConfig( 
    paths: [__DIR__ . '/../src/Model'],
    isDevMode: true,
);

$config->setProxyDir(__DIR__ . '/../var/cache/doctrine/proxies');

$config->setProxyNamespace('App\Proxies');

$connection = DriverManager::getConnection([
    'driver'   => 'pdo_mysql',
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'uooudb',
    'user'     => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
], $config);

$entityManager = new EntityManager($connection, $config);

return $entityManager;