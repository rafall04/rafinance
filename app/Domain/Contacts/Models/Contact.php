<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 */
class Contact extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'phone',
        'note',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
