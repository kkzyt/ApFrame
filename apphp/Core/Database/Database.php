<?php
namespace apphp\Core\database;

interface Database
{
    public function __construct();
    public function query($sql, array $params = []);
    public function exec($sql, array $params = []);
    public function selectSpecificField($field, $table, $where = null, $limit = null, $order = null);
    public function insert(array $fileValue, $table);
    public function update(array $fileValue, $table, $where = null);
    public function delete($table, $where = null);
    public function close();
}
