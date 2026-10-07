<?php
namespace apphp\Core\database;
require_once __DIR__ . '/PdoDriver.php';

class PostgreSql implements Database
{
    use PdoDriver;
    function __construct()
    {
        $this->conn = new \PDO('pgsql:host=' . PGSQL_HOST . ';port=' . PGSQL_PORT . ';dbname=' . PGSQL_DATABASE,
            PGSQL_USER, PGSQL_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false]);
        $this->query("SELECT set_config('client_encoding', ?, false)", [PGSQL_CHARSET]);
    }
}
