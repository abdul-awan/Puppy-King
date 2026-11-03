<?php

namespace App\Concerns;

use App\Models\ActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Records created/updated/deleted events (and custom events) in the activity log.
 */
trait LogsActivity
{
    /**
     * Boot the trait.
     */
    public static function bootLogsActivity(): void
    {
        static::created(function (self $model): void {
            $model->logActivity('created');
        });

        static::updated(function (self $model): void {
            $fields = array_values(array_diff(
                array_keys($model->getChanges()),
                $model->activityIgnoredAttributes(),
            ));

            if ($fields === []) {
                return;
            }

            $old = [];
            $new = [];

            foreach ($fields as $field) {
                $old[$field] = $model->activityValue($model->getOriginal($field));
                $new[$field] = $model->activityValue($model->getAttribute($field));
            }

            $model->logActivity('updated', null, ['fields' => $fields, 'old' => $old, 'new' => $new]);
        });

        static::deleted(function (self $model): void {
            $model->logActivity('deleted');
        });
    }

    /**
     * Get the activity log entries for the record, newest first.
     *
     * @return MorphMany<ActivityLog, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest('id');
    }

    /**
     * Write an entry to the activity log for this record.
     *
     * @param  array<string, mixed>  $properties
     */
    public function logActivity(string $event, ?string $description = null, array $properties = []): ActivityLog
    {
        return ActivityLog::create([
            'team_id' => $this->getAttribute('team_id'),
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'causer_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
        ]);
    }

    /**
     * Attributes whose changes are not worth logging.
     *
     * @return array<int, string>
     */
    public function activityIgnoredAttributes(): array
    {
        return ['created_at', 'updated_at', 'deleted_at', 'sort', 'sort_order', 'public_token', 'custom_fields'];
    }

    /**
     * Convert an attribute value into a short loggable value.
     */
    protected function activityValue(mixed $value): string|int|float|bool|null
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value), is_float($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i'),
            is_string($value) => mb_strlen($value) > 120 ? mb_substr($value, 0, 117).'...' : $value,
            default => null,
        };
    }
}
