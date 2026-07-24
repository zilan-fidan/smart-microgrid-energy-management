<?php

namespace App\Services\Storage;

interface JsonStorageInterface
{
    /**
     * Read the entire JSON document as an associative array.
     */
    public function read(): array;

    /**
     * Overwrite the entire JSON document.
     */
    public function write(array $data): void;
}
