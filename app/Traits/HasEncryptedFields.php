<?php

namespace App\Traits;

use App\Services\FinanceEncryptionService;

trait HasEncryptedFields
{
    public static function bootHasEncryptedFields(): void
    {
        static::saving(function ($model) {
            $service = app(FinanceEncryptionService::class);
            foreach ($model->blindIndexFields() as $field => $indexColumn) {
                $raw = $model->getAttributes()[$field] ?? null;
                if ($raw !== null && $raw !== '') {
                    $model->{$indexColumn} = $service->blindIndex($raw);
                } else {
                    $model->{$indexColumn} = null;
                }
            }
        });
    }

    public function scopeWhereBlindIndex($query, string $field, string $value)
    {
        $service = app(FinanceEncryptionService::class);
        $indexColumn = $this->blindIndexFields()[$field] ?? null;
        if (!$indexColumn) {
            throw new \InvalidArgumentException("No blind index configured for field: {$field}");
        }

        return $query->where($indexColumn, $service->blindIndex($value));
    }

    abstract public function blindIndexFields(): array;
}
