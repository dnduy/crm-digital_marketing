<?php

namespace Core;

/**
 * Base Model Class
 * Provides common database functionality for all models
 */
abstract class Model
{
    protected \PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find a record by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $this->hideFields($result) : null;
    }

    /**
     * Find all records
     */
    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table}");
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map([$this, 'hideFields'], $results);
    }

    /**
     * Find records with conditions
     */
    public function where(array $conditions): array
    {
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $whereClause = implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$whereClause}");
        $stmt->execute($params);
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map([$this, 'hideFields'], $results);
    }

    /**
     * Create a new record
     */
    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Update a record
     */
    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        $fields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a record
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        if (empty($conditions)) {
            $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->table}");
            return (int)$stmt->fetchColumn();
        }
        
        $where = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            $where[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $whereClause = implode(' AND ', $where);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Execute raw query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Filter data by fillable fields
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Hide sensitive fields
     */
    protected function hideFields(array $data): array
    {
        if (empty($this->hidden)) {
            return $data;
        }
        
        return array_diff_key($data, array_flip($this->hidden));
    }
}