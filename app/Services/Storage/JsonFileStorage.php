<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;

class JsonFileStorage implements JsonStorageInterface
{
    public function __construct(
        private readonly Filesystem $disk,
        private readonly string $filename,
    ) {
    }

    public function read(): array
    {
        if (! $this->disk->exists($this->filename)) {
            return [];
        }

        $contents = $this->disk->get($this->filename);

        return json_decode($contents, true) ?? [];
    }

    public function write(array $data): void
    {
        $this->disk->put(
            $this->filename,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
