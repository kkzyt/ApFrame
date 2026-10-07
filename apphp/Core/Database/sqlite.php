<?php
namespace apphp\Core\database;
require_once __DIR__ . '/PdoDriver.php';

class Sqlite implements Database
{
    use PdoDriver;
    function __construct()
    {
        $this->conn = new \PDO('sqlite:' . RESOURCE_PATH . 'database/' . SQLITE_FILE,
            null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }
}
