<?php

namespace App\Traits;

trait SearchableTrait
{
    protected function applySearch($query, ?string $search, array $columns = []): mixed
    {
        if (empty($search) || empty($columns)) {
            return $query;
        }

        $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'like', "%{$search}%");
                    continue;
                }

                $q->orWhere($column, 'like', "%{$search}%");
            }
        });

        return $query;
    }
}
