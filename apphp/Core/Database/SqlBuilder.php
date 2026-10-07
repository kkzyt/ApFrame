<?php
namespace apphp\Core\database;

/** Values are always bound; identifiers and limits are validated separately. */
trait SqlBuilder
{
    protected function identifier($name)
    {
        if (!is_string($name) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier');
        }
        $quote = $this instanceof MySql ? '`' : '"';
        return $quote . $name . $quote;
    }

    protected function conditions($where, &$params)
    {
        if ($where === null || $where === []) { return ''; }
        if (!is_array($where)) {
            throw new \InvalidArgumentException('Use structured conditions instead of raw WHERE strings');
        }
        if (isset($where['column']) && array_key_exists('value', $where)) {
            $where = [['column' => $where['column'], 'value' => $where['value'], 'boolean' => 'AND']];
        }
        $parts = [];
        foreach ($where as $condition) {
            if (!is_array($condition) || !array_key_exists('value', $condition)) {
                throw new \InvalidArgumentException('Invalid SQL condition');
            }
            $column = $this->identifier($condition['column'] ?? null);
            $boolean = $condition['boolean'] ?? 'AND';
            if (!in_array($boolean, ['AND', 'OR'], true)) {
                throw new \InvalidArgumentException('Invalid condition operator');
            }
            $value = $condition['value'];
            if ($value === null) { $part = $column . ' IS NULL'; }
            else { $this->value($value); $part = $column . ' = ?'; $params[] = $value; }
            $parts[] = ($parts ? $boolean . ' ' : '') . $part;
        }
        return ' WHERE ' . implode(' ', $parts);
    }

    protected function value($value)
    {
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('SQL values must be scalar or null');
        }
    }

    public function selectSpecificField($field, $table, $where = null, $limit = null, $order = null)
    {
        $fields = $field === '*' ? '*' : implode(', ', array_map([$this, 'identifier'], array_map('trim', explode(',', $field))));
        $params = [];
        $sql = 'SELECT ' . $fields . ' FROM ' . $this->identifier($table) . $this->conditions($where, $params);
        if ($order !== null) {
            if (!is_array($order)) { throw new \InvalidArgumentException('Order must map columns to ASC or DESC'); }
            $parts = [];
            foreach ($order as $column => $direction) {
                $direction = strtoupper($direction);
                if (!in_array($direction, ['ASC', 'DESC'], true)) { throw new \InvalidArgumentException('Invalid sort direction'); }
                $parts[] = $this->identifier($column) . ' ' . $direction;
            }
            if ($parts) { $sql .= ' ORDER BY ' . implode(', ', $parts); }
        }
        if ($limit !== null) {
            if (!(is_int($limit) || is_string($limit)) || !preg_match('/\A\d+\z/', (string) $limit)) {
                throw new \InvalidArgumentException('Invalid limit');
            }
            if ($this instanceof MsSql) { $sql = preg_replace('/\ASELECT /', 'SELECT TOP ' . (int) $limit . ' ', $sql); }
            else { $sql .= ' LIMIT ' . (int) $limit; }
        }
        return $this->fetchRows($sql, $params);
    }

    public function insert(array $fileValue, $table)
    {
        if (!$fileValue) { throw new \InvalidArgumentException('No values to insert'); }
        foreach ($fileValue as $value) { $this->value($value); }
        $columns = implode(', ', array_map([$this, 'identifier'], array_keys($fileValue)));
        $marks = implode(', ', array_fill(0, count($fileValue), '?'));
        return $this->exec('INSERT INTO ' . $this->identifier($table) . ' (' . $columns . ') VALUES (' . $marks . ')', array_values($fileValue));
    }

    public function update(array $fileValue, $table, $where = null)
    {
        if (!$fileValue) { throw new \InvalidArgumentException('No values to update'); }
        $sets = [];
        foreach ($fileValue as $column => $value) { $this->value($value); $sets[] = $this->identifier($column) . ' = ?'; }
        $params = array_values($fileValue);
        $conditions = $this->conditions($where, $params);
        if ($conditions === '') { throw new \InvalidArgumentException('UPDATE requires conditions'); }
        return $this->exec('UPDATE ' . $this->identifier($table) . ' SET ' . implode(', ', $sets) . $conditions, $params);
    }

    public function delete($table, $where = null)
    {
        $params = [];
        $conditions = $this->conditions($where, $params);
        if ($conditions === '') { throw new \InvalidArgumentException('DELETE requires conditions'); }
        return $this->exec('DELETE FROM ' . $this->identifier($table) . $conditions, $params);
    }
}
