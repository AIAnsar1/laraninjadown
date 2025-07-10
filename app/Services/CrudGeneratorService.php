<?php

namespace App\Services;

use App\Repositories\CrudGeneratorRepository;

class CrudGeneratorService extends BaseService
{
    public function __construct(CrudGeneratorRepository $repository)
    {
        $this->repository = $repository;
    }
}
