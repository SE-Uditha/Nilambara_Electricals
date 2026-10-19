<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Shared Eloquent guards for retained business records.
 *
 * Query-builder updates/deletes, raw SQL, and eventless operations bypass these
 * guards. They are not a replacement for database constraints or the future
 * transactional workflows that authorize cancellations and reversals.
 */
abstract class BusinessModel extends Model
{
    /** @var list<string> */
    protected array $immutableFields = ['id'];

    protected bool $appendOnly = false;

    protected bool $preserveRecord = false;

    /** @var list<string> */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (BusinessModel $model): void {
            if ($model->appendOnly) {
                throw new LogicException($model::class.' records are append-only. Record a linked reversal instead.');
            }

            foreach ($model->immutableFields as $field) {
                if ($model->isDirty($field)) {
                    throw new LogicException($model::class.' field ['.$field.'] cannot be changed after creation.');
                }
            }
        });

        static::deleting(function (BusinessModel $model): void {
            if ($model->appendOnly || $model->preserveRecord) {
                throw new LogicException($model::class.' records must be retained. Use a documented cancellation or reversal.');
            }
        });
    }
}
