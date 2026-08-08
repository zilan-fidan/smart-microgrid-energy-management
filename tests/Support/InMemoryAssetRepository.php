<?php

namespace Tests\Support;

use App\Domain\Contracts\AssetRepositoryInterface;
use InvalidArgumentException;

/**
 * In-memory stand-in for JsonAssetRepository, mirroring its exact
 * collection/save/delete semantics without touching the filesystem.
 * Tracks call counts so tests can assert a code path is read-only.
 */
class InMemoryAssetRepository implements AssetRepositoryInterface
{
    private const COLLECTIONS = [
        'solar' => 'solar_plants',
        'wind' => 'wind_plants',
        'battery' => 'batteries',
        'consumption' => 'consumption_points',
    ];

    public int $saveCallCount = 0;

    public int $deleteCallCount = 0;

    public function __construct(private array $data = [])
    {
    }

    public function findAll(?string $type = null): array
    {
        if ($type !== null) {
            return $this->data[$this->collectionFor($type)] ?? [];
        }

        $all = [];
        foreach (self::COLLECTIONS as $collectionType => $key) {
            foreach ($this->data[$key] ?? [] as $record) {
                $all[] = $record + ['type' => $collectionType];
            }
        }

        return $all;
    }

    public function find(string $id): ?array
    {
        foreach (self::COLLECTIONS as $type => $key) {
            foreach ($this->data[$key] ?? [] as $record) {
                if ($record['id'] === $id) {
                    return $record + ['type' => $type];
                }
            }
        }

        return null;
    }

    public function save(array $data): array
    {
        $this->saveCallCount++;

        $type = $data['type'] ?? null;
        $key = $this->collectionFor($type);
        $record = collect($data)->except('type')->all();

        $records = $this->data[$key] ?? [];

        $index = null;
        if (! empty($record['id'])) {
            foreach ($records as $i => $existing) {
                if ($existing['id'] === $record['id']) {
                    $index = $i;
                    break;
                }
            }
        } else {
            $record['id'] = uniqid('test-', more_entropy: true);
        }

        if ($index !== null) {
            $records[$index] = $record;
        } else {
            $records[] = $record;
        }

        $this->data[$key] = array_values($records);

        return $record + ['type' => $type];
    }

    public function delete(string $id): void
    {
        $this->deleteCallCount++;

        foreach (self::COLLECTIONS as $key) {
            $records = $this->data[$key] ?? [];
            $filtered = array_values(array_filter($records, fn ($r) => $r['id'] !== $id));

            if (count($filtered) !== count($records)) {
                $this->data[$key] = $filtered;

                return;
            }
        }
    }

    /**
     * Full snapshot of the underlying store, for before/after equality checks.
     */
    public function snapshot(): array
    {
        return $this->data;
    }

    private function collectionFor(?string $type): string
    {
        if ($type === null || ! isset(self::COLLECTIONS[$type])) {
            throw new InvalidArgumentException("Unknown asset type: {$type}");
        }

        return self::COLLECTIONS[$type];
    }
}
