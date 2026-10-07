<?php
namespace apphp\Core;

abstract class Model implements \ArrayAccess
{
    protected $model;
    protected $table;
    protected $primary_key;
    protected $connection;
    protected $sql;
    protected $sql_param = [];
    protected $specific_field;
    protected $limit;
    protected $where = [];
    protected $data = [];

    function __construct()
    {
        $driver = APP_SERVICE['database'][NOW_USE_DB] ?? null;
        if (!$driver) { throw new \InvalidArgumentException('Unknown database driver'); }
        $this->connection = new $driver();
        if ($this->table === null) { $parts = explode('\\', static::class); $this->table = end($parts); }
        if ($this->primary_key === null) { $this->primary_key = 'id'; }
    }

    function __set($key, $value) { $this->sql_param[$key] = $value; }
    function __get($key) { return array_key_exists($key, $this->sql_param) ? $this->sql_param[$key] : ($this->data[$key] ?? null); }
    function __isset($key) { return isset($this->sql_param[$key]) || (!array_key_exists($key, $this->sql_param) && isset($this->data[$key])); }
    function __unset($key) { unset($this->sql_param[$key], $this->data[$key]); }

    protected function specificFieldToString() { return $this->specific_field === null ? '*' : implode(',', $this->specific_field); }

    /** Keep the existing list-of-rows return format; hydrate attributes for a single result. */
    public function find($id)
    {
        try {
            $this->data = [];
            $this->where($this->primary_key, $id);
            $rows = $this->connection->selectSpecificField('*', $this->table, $this->where, 1);
            $this->data = $rows[0] ?? [];
            return $rows;
        } finally { $this->resetQuery(); }
    }
    public function get()
    {
        try {
            $this->data = [];
            $rows = $this->connection->selectSpecificField($this->specificFieldToString(), $this->table, $this->where, $this->limit);
            $this->data = count($rows) === 1 ? $rows[0] : [];
            return $rows;
        } finally { $this->resetQuery(); }
    }
    public function getAll()
    {
        try {
            $this->data = [];
            $rows = $this->connection->selectSpecificField($this->specificFieldToString(), $this->table);
            $this->data = count($rows) === 1 ? $rows[0] : [];
            return $rows;
        } finally { $this->resetQuery(); }
    }
    protected function requireValues()
    {
        if (!$this->sql_param) { throw new \InvalidArgumentException('No model fields to save'); }
    }
    public function create()
    {
        try {
            $this->requireValues();
            $result = $this->connection->insert($this->sql_param, $this->table);
            if ($result) { $this->data = $this->sql_param; }
            return $result;
        } finally { $this->resetQuery(); $this->sql_param = []; }
    }
    public function update()
    {
        try {
            $this->requireValues();
            if (!$this->where) { throw new \InvalidArgumentException('Model update requires conditions'); }
            // Updating an arbitrary result set does not hydrate a particular record.
            $result = $this->connection->update($this->sql_param, $this->table, $this->where);
            if ($result) { $this->data = []; }
            return $result;
        } finally { $this->resetQuery(); $this->sql_param = []; }
    }
    public function remove()
    {
        try {
            if (!$this->where) { throw new \InvalidArgumentException('Model delete requires conditions'); }
            $result = $this->connection->delete($this->table, $this->where);
            if ($result) { $this->data = []; }
            return $result;
        } finally { $this->resetQuery(); }
    }
    public function where($column, $value) { $this->where[] = ['column'=>$column, 'value'=>$value, 'boolean'=>'AND']; return $this; }
    public function orWhere($column, $value) { $this->where[] = ['column'=>$column, 'value'=>$value, 'boolean'=>'OR']; return $this; }
    public function limit($limit) { $this->limit = $limit; return $this; }
    protected function resetQuery() { $this->where = []; $this->limit = null; $this->specific_field = null; $this->sql = null; }
    protected function emptySql() { $this->resetQuery(); $this->sql_param = []; }

    protected function related($model, $foreign_key, $local_key = null)
    {
        if (!is_string($model) || !is_subclass_of($model, self::class)) { throw new \InvalidArgumentException('Related class must extend Model'); }
        $key = $local_key ?? $this->primary_key;
        $value = array_key_exists($key, $this->sql_param) ? $this->sql_param[$key] : ($this->data[$key] ?? null);
        if ($value === null) { throw new \LogicException('Load or set the local key before querying a relation'); }
        $related = new $model();
        return $related->where($foreign_key, $value);
    }
    /** Returns one related row or null. */
    protected function hasOne($model, $foreign_key, $local_key = null)
    {
        $rows = $this->related($model, $foreign_key, $local_key)->limit(1)->get();
        return $rows[0] ?? null;
    }
    /** Returns a list of related rows. */
    protected function hasMany($model, $foreign_key, $local_key = null) { return $this->related($model, $foreign_key, $local_key)->get(); }

    public function offsetExists($offset): bool { return $this->__isset($offset); }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return $this->__get($offset); }
    public function offsetSet($offset, $value): void
    {
        if (!is_string($offset) || $offset === '') { throw new \InvalidArgumentException('Model fields require a nonempty string key'); }
        $this->__set($offset, $value);
    }
    public function offsetUnset($offset): void { $this->__unset($offset); }
}
