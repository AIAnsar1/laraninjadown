<?php

namespace App\Http\Controllers;

use App\Constants\UsersRoles;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Throwable;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\StoreRequest\StoreUserRequest;
use App\Http\Requests\UpdateRequest\UpdateUserRequest;
use App\Dtos\ApiResponse;


class UserController extends Controller
{
    /**
     * @var UserService
     */
    private UserService $service;

    /**
     * @param UserService $service
     */
    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @throws Throwable
     */
    public function index(Request $request)
    {
        return UserResource::collection( $this->service->paginatedList( $request->all() ) );
    }

    /**
     * @param StoreUserRequest $request
     * @return array|Builder|Collection|User
     * @throws Throwable
     */
    public function store(StoreUserRequest $request): array |Builder|Collection|User
    {
        return $this->service->createModel($request->validated());

    }

    /**
     * @param $
     * @return UserResource
     * @throws Throwable
     */
    public function show(int $usersId)
    {
        return new UserResource( $this->service->getModelById( $usersId ));
    }

    /**
     * @param UpdateUserRequest $request
     * @param int $usersId
     * @return array|User|Collection|Builder
     * @throws Throwable
     */
    public function update(UpdateUserRequest $request, int $usersId): array |User|Collection|Builder
    {
        return $this->service->updateModel($request->validated(), $usersId);

    }

    /**
     * @param int $usersId
     * @return array|Builder|Collection|User
     * @throws Throwable
     */
    public function destroy(int $usersId): array |Builder|Collection|User
    {
        return $this->service->deleteModel($usersId);
    }



    public function roles(): JsonResponse
    {
        $role = new UsersRoles();
        return ApiResponse::success($role->getRoleList());
    }
}