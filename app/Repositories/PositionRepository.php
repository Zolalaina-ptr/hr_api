<?php

namespace App\Repositories;

use App\Models\Position;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class PositionRepository
{
    /**
     * Get all positions
     */
    public function getAll(): Collection
    {
        return Position::with(['department', 'requirements', 'employees'])
            ->get();
    }

    /**
     * Get paginated positions
     */
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        $query = Position::with(['department', 'requirements']);

        if (isset($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (isset($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($perPage)->through(fn ($p) => $p)->withQueryString();
    }

    /**
     * Find position by ID
     */
    public function findById(int $id): ?Position
    {
        return Position::with(['department', 'requirements', 'employees'])
            ->find($id);
    }

    /**
     * Find position by code
     */
    public function findByCode(string $code): ?Position
    {
        return Position::where('code', $code)->first();
    }

    /**
     * Create position
     */
    public function create(array $data): Position
    {
        return Position::create($data);
    }

    /**
     * Update position
     */
    public function update(Position $position, array $data): Position
    {
        $position->update($data);
        return $position->fresh(['department', 'requirements']);
    }

    /**
     * Delete position
     */
    public function delete(Position $position): bool
    {
        return $position->delete();
    }

    /**
     * Get positions by level
     */
    public function getByLevel(string $level): Collection
    {
        return Position::where('level', $level)
            ->with(['department', 'requirements'])
            ->get();
    }

    /**
     * Get positions by department
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return Position::where('department_id', $departmentId)
            ->with(['requirements', 'employees'])
            ->get();
    }

    /**
     * Get positions with available slots
     */
    public function getAvailable(): Collection
    {
        return Position::with(['department', 'requirements'])
            ->withCount('employees')
            ->where('is_active', true)
            ->get()
            ->filter(fn ($position) => $position->employees_count === 0);
    }

    /**
     * Get positions in salary range
     */
    public function getBySalaryRange(float $minSalary, float $maxSalary): Collection
    {
        return Position::where('min_salary', '<=', $maxSalary)
            ->where('max_salary', '>=', $minSalary)
            ->with(['department', 'requirements'])
            ->get();
    }

    /**
     * Check if position exists
     */
    public function exists(int $id): bool
    {
        return Position::where('id', $id)->exists();
    }

    /**
     * Get positions count
     */
    public function count(): int
    {
        return Position::count();
    }

    /**
     * Get active positions count
     */
    public function activeCount(): int
    {
        return Position::where('is_active', true)->count();
    }

    /**
     * Get average salary
     */
    public function getAverageSalary(): float
    {
        return (float) (Position::query()
            ->selectRaw('AVG((min_salary + max_salary) / 2) as avg_salary')
            ->value('avg_salary') ?? 0);
    }

    public function getLevels(): array
    {
        return ['junior', 'mid', 'senior', 'lead', 'manager', 'director'];
    }
}
