<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrudGeneratorResource;
use Illuminate\Http\Request;
use Throwable;
use App\Models\CrudGenerator;
use App\Services\CrudGeneratorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\StoreRequest\StoreCrudGeneratorRequest;
use App\Http\Requests\UpdateRequest\UpdateCrudGeneratorRequest;


class CrudGeneratorController extends Controller
{
    /**
     * @var CrudGeneratorService
     */
    private CrudGeneratorService $service;

    /**
     * @param CrudGeneratorService $service
     */
    public function __construct(CrudGeneratorService $service)
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
        return CrudGeneratorResource::collection( $this->service->paginatedList( $request->all() ) );
    }

    /**
     * @param StoreCrudGeneratorRequest $request
     * @return array|Builder|Collection|CrudGenerator
     * @throws Throwable
     */
    public function store(StoreCrudGeneratorRequest $request): array |Builder|Collection|CrudGenerator
    {
        return $this->service->createModel($request->validated());

    }

    /**
     * @param $CrudgeneratorId
     * @return CrudGeneratorResource
     * @throws Throwable
     */
    public function show(int $CrudgeneratorId)
    {
        return new CrudGeneratorResource( $this->service->getModelById( $CrudgeneratorId ));
    }

    /**
     * @param UpdateCrudGeneratorRequest $request
     * @param int $CrudgeneratorId
     * @return array|CrudGenerator|Collection|Builder
     * @throws Throwable
     */
    public function update(UpdateCrudGeneratorRequest $request, int $CrudgeneratorId): array |CrudGenerator|Collection|Builder
    {
        return $this->service->updateModel($request->validated(), $CrudgeneratorId);

    }

    /**
     * @param int $CrudgeneratorId
     * @return array|Builder|Collection|CrudGenerator
     * @throws Throwable
     */
    public function destroy(int $CrudgeneratorId): array |Builder|Collection|CrudGenerator
    {
        return $this->service->deleteModel($CrudgeneratorId);
    }
}

