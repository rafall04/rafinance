<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Ledger\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk_path' => 'lampiran/'.Str::ulid().'.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(20_000, 400_000),
            'sha256' => hash('sha256', (string) Str::ulid()),
        ];
    }
}
