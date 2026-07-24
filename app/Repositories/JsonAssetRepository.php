<?php

namespace App\Repositories;

use App\Domain\Contracts\AssetRepositoryInterface;
use App\Services\Storage\JsonStorageInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JsonAssetRepository implements AssetRepositoryInterface
{
    /**
     * Maps a domain asset type to its top-level JSON collection key.
     */
    private const COLLECTIONS = [
        'solar' => 'solar_plants',
        'wind' => 'wind_plants',
        'battery' => 'batteries',
        'consumption' => 'consumption_points',
    ];

    public function __construct(
        private readonly JsonStorageInterface $storage,
    ) {
    }

    public function findAll(?string $type = null): array
    {
        $data = $this->storage->read();

        if ($type !== null) {
            return $data[$this->collectionFor($type)] ?? [];
        }

        $all = [];
        foreach (self::COLLECTIONS as $collectionType => $key) {
            foreach ($data[$key] ?? [] as $record) {
                $all[] = $record + ['type' => $collectionType];
            }
        }

        return $all;
    }

    public function find(string $id): ?array
    {
        $data = $this->storage->read();

        foreach (self::COLLECTIONS as $type => $key) {
            foreach ($data[$key] ?? [] as $record) {
                if ($record['id'] === $id) {
                    return $record + ['type' => $type];
                }
            }
        }

        return null;
    }

    public function save(array $data): array
    {
        $type = $data['type'] ?? null;
        $key = $this->collectionFor($type);
        $record = collect($data)->except('type')->all();

        $store = $this->storage->read();
        $records = $store[$key] ?? [];

        $index = null;
        if (isset($record['id'])) {
            foreach ($records as $i => $existing) {
                if ($existing['id'] === $record['id']) {
                    $index = $i;
                    break;
                }
            }
        } else {
            $record['id'] = (string) Str::uuid();
        }

        if ($index !== null) {
            $records[$index] = $record;
        } else {
            $records[] = $record;
        }

        $store[$key] = array_values($records);
        $this->storage->write($store);

        return $record + ['type' => $type];
    }

    public function delete(string $id): void
    {
        $store = $this->storage->read();

        foreach (self::COLLECTIONS as $key) {
            $records = $store[$key] ?? [];
            $filtered = array_values(array_filter($records, fn ($r) => $r['id'] !== $id));

            if (count($filtered) !== count($records)) {
                $store[$key] = $filtered;
                $this->storage->write($store);

                return;
            }
        }
    }

    private function collectionFor(?string $type): string
    {
        if ($type === null || ! isset(self::COLLECTIONS[$type])) {
            throw new InvalidArgumentException("Unknown asset type: {$type}");
        }

        return self::COLLECTIONS[$type];
    }
}
