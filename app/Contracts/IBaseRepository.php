<?php

namespace App\Contracts;


interface IBaseRepository
{
    /**
     * @param $data
     * @return mixed
     */
    public function paginatedList(array $data): mixed;

    /**
     * @param $data
     * @return mixed
     */
    public function create(array $data): mixed;

    /**
     * @param $data
     * @param $id
     * @return mixed
     */
    public function update(array $data, int $id): mixed;

    /**
     * @param $id
     * @return mixed
     */
    public function delete(int $id): mixed;

    /**
     * @param $id
     * @return mixed
     */
    public function findById(int $id): mixed;
}
