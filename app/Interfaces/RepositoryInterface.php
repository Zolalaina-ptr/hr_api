<?php

namespace App\Interfaces;

interface RepositoryInterface
{
    public function all(array $columns = ['*'], array $relations = []);

    public function find(int|string $id, array $relations = []);

    public function create(array $data);

    public function update(int|string $id, array $data);

    public function delete(int|string $id): bool;
}
