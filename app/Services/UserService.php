<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use Throwable;


class UserService extends BaseService
{
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array $data
     * @return Model|array|Collection|Builder|null
     * @throws Throwable
     */
    public function createModel(array $data): array|Builder|Collection|Model|BaseModel|null
    {
        $data['password'] = bcrypt($data['password']);
        return parent::createModel($data);
    }

    /**
     * @param array $data
     * @param int $id
     * @return Model|array|Collection|Builder[]|Builder[]|null
     * @throws Throwable
     */
    public function updateModel(array $data, int $id): array|Builder|Collection|Model|BaseModel|null
    {
        $data['password'] = bcrypt($data['password']);
        return parent::updateModel($data, $id);
    }
}
