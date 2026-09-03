<?php

namespace App\Services;

use App\Interfaces\RepositoryInterface;
use App\Interfaces\ServiceInterface;

abstract class BaseService implements ServiceInterface
{
    public function __construct(protected RepositoryInterface $repository)
    {
    }

    public function all(array $filters = [], array $relations = [])
    {
        return $this->repository->all(['*'], $relations);
    }

    public function find(int|string $id, array $relations = [])
    {
        return $this->repository->find($id, $relations);
    }

    public function create(array $data)
    {
        return $this->repository->create($data);
    }

    public function update(int|string $id, array $data)
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}
