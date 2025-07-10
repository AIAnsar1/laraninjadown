<?php


namespace App\Contracts;


interface IBaseService
{
    /**
     * @param array $data
     * @return mixed
     */
    public function paginatedList(array $data): mixed;

    /**
     * @param array $data
     * @return mixed
     */
    public function createModel(array $data): mixed;

    /**
     * @param array $data
     * @param int $id
     * @return mixed
     */
    public function updateModel(array $data, int $id): mixed;

    /**
     * @param int $id
     * @return mixed
     */
    public function deleteModel(int $id): mixed;

    /**
     * @param $id
     * @return mixed
     */
    public function getModelById(int $id): mixed;
}
