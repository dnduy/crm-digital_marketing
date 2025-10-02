<?php

namespace Core\Database;

use PDO;
use PDOStatement;

/**
 * Modern Query Builder
 * Fluent interface for building SQL queries
 */
class QueryBuilder
{
    private PDO $connection;
    private string $table = '';
    private array $select = ['*'];
    private array $joins = [];
    private array $where = [];
    private array $having = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $bindings = [];

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set table name
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Set select columns
     */
    public function select(array $columns = ['*']): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * Add where clause
     */
    public function where(string $column, string $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add OR where clause
     */
    public function orWhere(string $column, string $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add WHERE IN clause
     */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $this->where[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add WHERE LIKE clause
     */
    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', $value);
    }

    /**
     * Add WHERE BETWEEN clause
     */
    public function whereBetween(string $column, array $values): self
    {
        $this->where[] = [
            'type' => 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    /**
     * Add JOIN clause
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add RIGHT JOIN clause
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [$column, strtoupper($direction)];
        return $this;
    }

    /**
     * Add GROUP BY clause
     */
    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }

    /**
     * Add HAVING clause
     */
    public function having(string $column, string $operator, $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Set LIMIT
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set OFFSET
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Paginate results
     */
    public function paginate(int $page, int $perPage): self
    {
        $this->limit = $perPage;
        $this->offset = ($page - 1) * $perPage;
        return $this;
    }

    /**
     * Get all results
     */
    public function get(): array
    {
        $sql = $this->buildSelectSql();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?array
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Get count
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        
        $sql = $this->buildSelectSql();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->select = $originalSelect;
        
        return (int)($result['count'] ?? 0);
    }

    /**
     * Insert data
     */
    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders})";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int)$this->connection->lastInsertId();
    }

    /**
     * Update data
     */
    public function update(array $data): int
    {
        $setParts = [];
        $bindings = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $bindings[] = $value;
        }
        
        $bindings = array_merge($bindings, $this->bindings);
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->rowCount();
    }

    /**
     * Delete data
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);
        
        return $stmt->rowCount();
    }

    /**
     * Execute raw SQL
     */
    public function raw(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /**
     * Build SELECT SQL
     */
    private function buildSelectSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;
        
        // JOINs
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        
        // WHERE
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $sql .= ' HAVING ' . $this->buildHavingClause();
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $orderParts = array_map(function ($order) {
                return "{$order[0]} {$order[1]}";
            }, $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orderParts);
        }
        
        // LIMIT & OFFSET
        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause(): string
    {
        $conditions = [];
        
        foreach ($this->where as $index => $condition) {
            $boolean = $index === 0 ? '' : strtoupper($condition['boolean']) . ' ';
            
            switch ($condition['type']) {
                case 'basic':
                    $conditions[] = $boolean . "{$condition['column']} {$condition['operator']} ?";
                    break;
                case 'in':
                    $placeholders = str_repeat('?,', count($condition['values']) - 1) . '?';
                    $conditions[] = $boolean . "{$condition['column']} IN ({$placeholders})";
                    break;
                case 'between':
                    $conditions[] = $boolean . "{$condition['column']} BETWEEN ? AND ?";
                    break;
            }
        }
        
        return implode(' ', $conditions);
    }

    /**
     * Build HAVING clause
     */
    private function buildHavingClause(): string
    {
        $conditions = [];
        
        foreach ($this->having as $index => $condition) {
            $boolean = $index === 0 ? '' : strtoupper($condition['boolean']) . ' ';
            $conditions[] = $boolean . "{$condition['column']} {$condition['operator']} ?";
        }
        
        return implode(' ', $conditions);
    }

    /**
     * Reset builder state
     */
    public function reset(): self
    {
        $this->table = '';
        $this->select = ['*'];
        $this->joins = [];
        $this->where = [];
        $this->having = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->bindings = [];
        
        return $this;
    }
}