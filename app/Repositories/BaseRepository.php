<?php

namespace App\Repositories;

use App\Interfaces\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function all(array $columns = ['*'], array $relations = [])
    {
        return $this->model->with($relations)->get($columns);
    }

    public function find(int|string $id, array $relations = [])
    {
        return $this->model->with($relations)->findOrFail($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(int|string $id, array $data)
    {
        $record = $this->find($id);

        $record->update($data);

        return $record->fresh();
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->find($id)->delete();
    }
}
