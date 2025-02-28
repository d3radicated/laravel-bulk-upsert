<?php

namespace Lapaliv\BulkUpsert\Features;

use Illuminate\Database\Eloquent\Model;

class GetDeletedAtColumnFeature
{
    public function handle(Model $model): ?string
    {
        return method_exists($model, 'getDeletedAtColumn')
            ? $model->getDeletedAtColumn()
            : null;
    }
}
