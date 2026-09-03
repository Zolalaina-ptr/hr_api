<?php

namespace App\Traits;

trait FilterableTrait
{
    protected function applyFilters($query, array $filters = [], array $allowed = []): mixed
    {
        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($allowed !== [] && ! in_array($column, $allowed, true)) {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);
                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }
}
