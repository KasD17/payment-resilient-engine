<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'actor',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the owning auditable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an audit log entry for a given model.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        Model $model,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $actor = 'system'
    ): self {
        return self::create([
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => (string) $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'actor' => $actor,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
