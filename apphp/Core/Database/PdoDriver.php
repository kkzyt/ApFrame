<?php
namespace apphp\Core\database;
require_once __DIR__ . '/SqlBuilder.php';

trait PdoDriver
{
    use SqlBuilder;
    protected $conn;

    public function query($sql, array $params = [])
    {
        $statement = $this->conn->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    protected function fetchRows($sql, array $params)
    {
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exec($sql, array $params = []) { $this->query($sql, $params); return true; }
    public function close() { $this->conn = null; return true; }
}
