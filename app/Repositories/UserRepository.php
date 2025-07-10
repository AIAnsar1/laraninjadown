<?php

namespace App\Repositories;

use App\Models\{BaseModel, User, Roles, UserRoles};
use Illuminate\Database\Eloquent\{Builder, Collection, Model};
use App\Constants\GeneralStatus;
use Throwable;

class UserRepository extends BaseRepository
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function create(array $data): array|Collection|Builder|BaseModel|null
    {
        $model = $this->getModel();
        $model->fill($data);
        $model->save();

        if (isset($data['roles']))
        {
            foreach ($data['roles'] as $role)
            {
                UserRoles::create([
                    'user_id' => $model->id,
                    'role_code' => $role['role_code'],
                    'status' => $role['status'] ? GeneralStatus::STATUS_ACTIVE : GeneralStatus::STATUS_NOT_ACTIVE,
                ]);
            }
        }

        return $model;
    }

    public function update(array $data, int $id): array|BaseModel|Builder|Collection|Model|null
    {
        $model = $this->findById($id);
        $model->fill($data);
        $model->save();

        if (isset($data['roles']))
        {
            foreach($data['roles'] as $role)
            {
                UserRoles::create([
                    'user_id' => $model->id,
                    'role_code' => $role['role_code'],
                    'status' => $role['status'] ? GeneralStatus::STATUS_ACTIVE : GeneralStatus::STATUS_NOT_ACTIVE,
                ]);
            }
        }
        return $model;
    }

    /**
     * @throws Throwable
     */
    public function findByEmail(string $email)
    {
        $model = $this->getModel();
        return $model::query()->where('email', '=', $email)->first();
    }
    /**
     * @throws Throwable
     */
    public function findByEmailOrName(string $emailOrName): model|Null
    {
        $model = $this->getModel();

        return $model::query()->where('email', '=', $emailOrName)->orWhere('name', '=', $emailOrName)->first();
    }

    /**
     * @param string $email
     * @return string
     * @throws Throwable
     */
    public function createToken(string $email): string
    {
        $model = $this->findByEmailOrName($email);

        return $model->createToken('auth_token')->accessToken;
    }

    /**
     * @param string|User $email
     * @return int
     * @throws Throwable
     */
    public function removeToken(string|User $email): int
    {
        if (is_string($email))
        {
            $model = $this->findByEmail($email);
        } else {
            $model = $email;
        }
        return $model->tokens()->delete();
    }
}