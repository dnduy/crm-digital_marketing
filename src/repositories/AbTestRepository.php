<?php
// ==========================
// FILE: /repositories/AbTestRepository.php - A/B Testing Repository
// ==========================

namespace Repositories;

use Core\Database\Repository;
use PDO;

class AbTestRepository extends Repository
{
    protected string $table = 'ab_tests';
    protected bool $timestamps = false; // Disable automatic timestamps
    protected array $fillable = [
        'campaign_id', 'test_name', 'hypothesis', 'variable_tested', 
        'control_value', 'variant_value', 'sample_size', 'confidence_level',
        'test_type', 'variant_a_config', 'variant_b_config', 'variant_a_traffic',
        'variant_b_traffic', 'variant_a_conversions', 'variant_b_conversions',
        'variant_a_revenue', 'variant_b_revenue', 'status', 'winner',
        'start_date', 'end_date'
    ];

    /**
     * Create new record without automatic timestamps
     */
    public function create(array $data): int
    {
        $filteredData = $this->filterFillable($data);
        $filteredData['created_at'] = date('Y-m-d H:i:s');
        
        return $this->query->table($this->table)->insert($filteredData);
    }

    /**
     * Update record without automatic updated_at
     */
    public function update(int $id, array $data): bool
    {
        $filteredData = $this->filterFillable($data);
        
        $affected = $this->query->table($this->table)
            ->where($this->primaryKey, $id)
            ->update($filteredData);
            
        return $affected > 0;
    }

    /**
     * Get tests by campaign
     */
    public function getByCampaign(int $campaignId): array
    {
        return $this->query()
            ->where('campaign_id', $campaignId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get active tests
     */
    public function getActiveTests(): array
    {
        return $this->query()
            ->whereIn('status', ['running', 'setup'])
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get completed tests
     */
    public function getCompletedTests(): array
    {
        return $this->query()
            ->where('status', 'completed')
            ->whereNotNull('winner')
            ->orderBy('end_date', 'DESC')
            ->get();
    }

    /**
     * Start an A/B test
     */
    public function startTest(int $testId): bool
    {
        return $this->update($testId, [
            'status' => 'running',
            'start_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Stop an A/B test with winner
     */
    public function stopTest(int $testId, string $winner): bool
    {
        return $this->update($testId, [
            'status' => 'completed',
            'winner' => $winner,
            'end_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Record conversion for variant
     */
    public function recordConversion(int $testId, string $variant, float $revenue = 0): bool
    {
        $test = $this->find($testId);
        if (!$test) return false;

        $conversionsField = $variant === 'a' ? 'variant_a_conversions' : 'variant_b_conversions';
        $revenueField = $variant === 'a' ? 'variant_a_revenue' : 'variant_b_revenue';

        return $this->update($testId, [
            $conversionsField => $test[$conversionsField] + 1,
            $revenueField => $test[$revenueField] + $revenue
        ]);
    }

    /**
     * Get test statistics
     */
    public function getTestStatistics(int $testId): ?array
    {
        $test = $this->find($testId);
        if (!$test) return null;

        $variantAConversions = $test['variant_a_conversions'];
        $variantBConversions = $test['variant_b_conversions'];
        $variantATraffic = $test['variant_a_traffic'] ?: 50;
        $variantBTraffic = $test['variant_b_traffic'] ?: 50;

        // Calculate conversion rates
        $variantARate = $variantATraffic > 0 ? ($variantAConversions / $variantATraffic) * 100 : 0;
        $variantBRate = $variantBTraffic > 0 ? ($variantBConversions / $variantBTraffic) * 100 : 0;

        // Calculate improvement
        $improvement = $variantARate > 0 ? (($variantBRate - $variantARate) / $variantARate) * 100 : 0;

        // Simple statistical significance (proper A/B testing would use more complex calculations)
        $totalConversions = $variantAConversions + $variantBConversions;
        $isSignificant = $totalConversions >= 100 && abs($improvement) >= 10;

        return [
            'test' => $test,
            'variant_a_rate' => round($variantARate, 2),
            'variant_b_rate' => round($variantBRate, 2),
            'improvement' => round($improvement, 2),
            'is_significant' => $isSignificant,
            'total_conversions' => $totalConversions,
            'variant_a_revenue' => $test['variant_a_revenue'],
            'variant_b_revenue' => $test['variant_b_revenue'],
            'revenue_improvement' => $test['variant_a_revenue'] > 0 ? 
                (($test['variant_b_revenue'] - $test['variant_a_revenue']) / $test['variant_a_revenue']) * 100 : 0
        ];
    }

    /**
     * Get test performance summary
     */
    public function getPerformanceSummary(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_tests,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as active_tests,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tests,
                SUM(variant_a_conversions + variant_b_conversions) as total_conversions,
                SUM(variant_a_revenue + variant_b_revenue) as total_revenue,
                AVG(CASE WHEN status = 'completed' THEN 
                    CASE WHEN variant_a_conversions + variant_b_conversions > 0 THEN
                        ((variant_b_conversions / CAST(variant_b_traffic as FLOAT)) - 
                         (variant_a_conversions / CAST(variant_a_traffic as FLOAT))) /
                        (variant_a_conversions / CAST(variant_a_traffic as FLOAT)) * 100
                    ELSE 0 END
                ELSE NULL END) as avg_improvement
            FROM {$this->table}
        ";

        $result = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_tests' => (int)$result['total_tests'],
            'active_tests' => (int)$result['active_tests'],
            'completed_tests' => (int)$result['completed_tests'],
            'total_conversions' => (int)$result['total_conversions'],
            'total_revenue' => round((float)$result['total_revenue'], 2),
            'avg_improvement' => round((float)$result['avg_improvement'], 2)
        ];
    }

    /**
     * Search tests by name or hypothesis
     */
    public function search(string $term, array $columns = []): array
    {
        if (empty($columns)) {
            $columns = ['test_name', 'hypothesis', 'variable_tested'];
        }
        
        $query = $this->query();
        
        foreach ($columns as $i => $column) {
            if ($i === 0) {
                $query = $query->where($column, 'LIKE', "%{$term}%");
            } else {
                $query = $query->orWhere($column, 'LIKE', "%{$term}%");
            }
        }
        
        return $query->orderBy('created_at', 'DESC')->get();
    }
}