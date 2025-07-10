<?php

namespace App\Traits;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\{Builder, Model};
use Throwable;

trait RepositoryHelper
{
    /**
     * @return Builder|BaseModel
     * @throws Throwable
     */
    protected function query(): BUilder|BaseModel
    {
        $query = $this->getModel()->query();
        return $query->orderByDesc('id');
    }

    /**
     * @return Model
     * @throws Throwable
     */
    protected function getModel(): Model
    {
        return $this->modelClass;
    }
}