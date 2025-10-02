<?php

namespace Core\Database;

use PDO;

/**
 * Base Repository Class
 * Provides common database operations using Query Builder
 */
abstract class Repository
{
    protected PDO $db;
    protected QueryBuilder $query;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $casts = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->query = new QueryBuilder($db);
    }

    /**
     * Find record by ID
     */
    public function find(int $id): ?array
    {
        $result = $this->query->table($this->table)->find($id);
        return $result ? $this->castAttributes($result) : null;
    }

    /**
     * Find record by column
     */
    public function findBy(string $column, $value): ?array
    {
        $result = $this->query->table($this->table)->where($column, $value)->first();
        return $result ? $this->castAttributes($result) : null;
    }

    /**
     * Get all records
     */
    public function all(): array
    {
        $results = $this->query->table($this->table)->get();
        return array_map([$this, 'castAttributes'], $results);
    }

    /**
     * Get records with pagination
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $total = $this->count();
        $results = $this->query->table($this->table)
            ->paginate($page, $perPage)
            ->get();

        return [
            'data' => array_map([$this, 'castAttributes'], $results),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }

    /**
     * Create new record
     */
    public function create(array $data): int
    {
        $filteredData = $this->filterFillable($data);
        $filteredData['created_at'] = date('Y-m-d H:i:s');
        $filteredData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->query->table($this->table)->insert($filteredData);
    }

    /**
     * Update record by ID
     */
    public function update(int $id, array $data): bool
    {
        $filteredData = $this->filterFillable($data);
        $filteredData['updated_at'] = date('Y-m-d H:i:s');
        
        $affected = $this->query->table($this->table)
            ->where($this->primaryKey, $id)
            ->update($filteredData);
            
        return $affected > 0;
    }

    /**
     * Delete record by ID
     */
    public function delete(int $id): bool
    {
        $affected = $this->query->table($this->table)
            ->where($this->primaryKey, $id)
            ->delete();
            
        return $affected > 0;
    }

    /**
     * Count records
     */
    public function count(): int
    {
        return $this->query->table($this->table)->count();
    }

    /**
     * Check if record exists
     */
    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * Get fresh query builder instance
     */
    public function query(): QueryBuilder
    {
        return $this->query->reset()->table($this->table);
    }

    /**
     * Search records
     */
    public function search(string $term, array $columns = []): array
    {
        if (empty($columns)) {
            $columns = $this->getSearchableColumns();
        }

        $query = $this->query->table($this->table);
        
        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $query->whereLike($column, "%{$term}%");
            } else {
                $query->orWhere($column, 'LIKE', "%{$term}%");
            }
        }

        $results = $query->get();
        return array_map([$this, 'castAttributes'], $results);
    }

    /**
     * Get records where column is in array of values
     */
    public function whereIn(string $column, array $values): array
    {
        $results = $this->query->table($this->table)
            ->whereIn($column, $values)
            ->get();
            
        return array_map([$this, 'castAttributes'], $results);
    }

    /**
     * Get records where column is between two values
     */
    public function whereBetween(string $column, $start, $end): array
    {
        $results = $this->query->table($this->table)
            ->whereBetween($column, [$start, $end])
            ->get();
            
        return array_map([$this, 'castAttributes'], $results);
    }

    /**
     * Bulk insert records
     */
    public function bulkInsert(array $records): bool
    {
        if (empty($records)) {
            return true;
        }

        $this->db->beginTransaction();

        try {
            foreach ($records as $record) {
                $this->create($record);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk update records
     */
    public function bulkUpdate(array $conditions, array $data): int
    {
        $query = $this->query->table($this->table);
        
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        
        $filteredData = $this->filterFillable($data);
        $filteredData['updated_at'] = date('Y-m-d H:i:s');
        
        return $query->update($filteredData);
    }

    /**
     * Get records with relations (basic join)
     */
    public function with(string $relation): QueryBuilder
    {
        $query = $this->query->table($this->table);
        
        // This is a simplified implementation
        // In a full ORM, this would handle more complex relationships
        switch ($relation) {
            case 'user':
                $query->leftJoin('users', $this->table . '.user_id', '=', 'users.id');
                break;
            case 'campaign':
                $query->leftJoin('campaigns', $this->table . '.campaign_id', '=', 'campaigns.id');
                break;
        }
        
        return $query;
    }

    /**
     * Execute raw SQL with bindings
     */
    public function raw(string $sql, array $bindings = []): array
    {
        $stmt = $this->query->raw($sql, $bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Transaction wrapper
     */
    public function transaction(callable $callback)
    {
        $this->db->beginTransaction();

        try {
            $result = $callback($this);
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Filter data to only fillable fields
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Cast attributes to appropriate types
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($this->casts as $key => $type) {
            if (!isset($attributes[$key])) {
                continue;
            }

            switch ($type) {
                case 'int':
                case 'integer':
                    $attributes[$key] = (int)$attributes[$key];
                    break;
                case 'float':
                case 'double':
                    $attributes[$key] = (float)$attributes[$key];
                    break;
                case 'bool':
                case 'boolean':
                    $attributes[$key] = (bool)$attributes[$key];
                    break;
                case 'array':
                case 'json':
                    $attributes[$key] = json_decode($attributes[$key], true);
                    break;
                case 'datetime':
                    if ($attributes[$key]) {
                        $attributes[$key] = new \DateTime($attributes[$key]);
                    }
                    break;
            }
        }

        return $attributes;
    }

    /**
     * Get searchable columns (override in child classes)
     */
    protected function getSearchableColumns(): array
    {
        return ['name', 'title', 'description'];
    }
}