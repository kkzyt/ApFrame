<?php
namespace apphp\Core\database;
require_once __DIR__ . '/SqlBuilder.php';

class MySql implements Database
{
    use SqlBuilder;
    private $conn;

    function __construct()
    {
        $this->conn = new \mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);
        $this->conn->set_charset(MYSQL_CHARSET);
    }

    private function statement($sql, array $params)
    {
        $statement = $this->conn->prepare($sql);
        if (!$statement) { throw new \RuntimeException($this->conn->error); }
        if ($params) {
            $types = '';
            foreach ($params as $value) {
                $types .= is_int($value) || is_bool($value) ? 'i' : (is_float($value) ? 'd' : 's');
            }
            $statement->bind_param($types, ...$params);
        }
        if (!$statement->execute()) { $error = $statement->error; $statement->close(); throw new \RuntimeException($error); }
        return $statement;
    }

    public function query($sql, array $params = [])
    {
        $statement = $this->statement($sql, $params);
        $result = $statement->get_result();
        $statement->close();
        return $result === false ? true : $result;
    }

    protected function fetchRows($sql, array $params)
    {
        $result = $this->query($sql, $params);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }

    public function exec($sql, array $params = [])
    {
        $statement = $this->statement($sql, $params);
        $statement->close();
        return true;
    }

    public function close() { return $this->conn->close(); }
}
