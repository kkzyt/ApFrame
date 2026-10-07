<?php
namespace apphp\Core\database;
require_once __DIR__ . '/PdoDriver.php';

class MsSql implements Database
{
    use PdoDriver;
    function __construct()
    {
        $this->conn = new \PDO('sqlsrv:Server=' . MSSQL_HOST . ',' . MSSQL_PORT . ';Database=' . MSSQL_DATABASE,
            MSSQL_USER, MSSQL_PASSWORD, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }
}
