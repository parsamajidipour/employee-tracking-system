<?php

namespace App\Models\Concerns;

use LogicException;

trait AppendOnly
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException(static::class.' is append-only: no update path exists.');
    }

    public function delete(): bool
    {
        throw new LogicException(static::class.' is append-only: no delete path exists.');
    }
}
