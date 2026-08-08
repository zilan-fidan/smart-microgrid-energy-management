<?php

namespace App\Domain\Contracts;

interface AssetRepositoryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(?string $type = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Create or update a record. $data must contain a 'type' key
     * (solar|wind|battery|consumption) identifying its collection.
     *
     * @return array<string, mixed> the persisted record, including its id
     */
    public function save(array $data): array;

    public function delete(string $id): void;
}
