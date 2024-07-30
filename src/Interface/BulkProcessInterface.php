<?php

namespace YukataRm\Laravel\BulkProcess\Interface;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as SupportCollection;

/**
 * BulkProcess Interface
 * 
 * @package YukataRm\Laravel\BulkProcess\Interface
 */
interface BulkProcessInterface
{
    /*----------------------------------------*
     * Constructor
     *----------------------------------------*/

    /**
     * get data
     *
     * @return \Illuminate\Support\Collection
     */
    public function data(): SupportCollection;

    /**
     * get data as array
     * 
     * @return array
     */
    public function dataArray(): array;

    /**
     * get data count
     * 
     * @return int
     */
    public function dataCount(): int;

    /**
     * get invalid data
     *
     * @return \Illuminate\Support\Collection
     */
    public function invalidData(): SupportCollection;

    /**
     * get invalid data as array
     * 
     * @return array
     */
    public function invalidDataArray(): array;

    /**
     * get invalid data count
     * 
     * @return int
     */
    public function invalidDataCount(): int;

    /*----------------------------------------*
     * Property
     *----------------------------------------*/

    /**
     * get limit
     *
     * @return int
     */
    public function limit(): int;

    /**
     * set limit
     * 
     * @param int $limit
     * @return static
     */
    public function setLimit(int $limit): static;

    /**
     * get class name of Model
     *
     * @return string
     */
    public function modelClass(): string;

    /**
     * set class name of Model
     * 
     * @param string $modelClass
     * @return static
     */
    public function setModelClass(string $modelClass): static;

    /**
     * get Model instance
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function model(): Model;

    /**
     * set class name of Model from instance
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return static
     */
    public function setModel(Model $model): static;

    /*----------------------------------------*
     * Bulk Process
     *----------------------------------------*/

    /**
     * get Builder
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    public function queryBuilder(): Builder;

    /**
     * truncate table
     * 
     * @return static
     */
    public function truncateTable(): static;

    /**
     * execute bulk process
     * 
     * @param \Closure $callback
     * @return static
     */
    public function bulkProcess(\Closure $callback): static;

    /**
     * execute bulk insert
     * 
     * @param bool $isTruncate
     * @return static
     */
    public function bulkInsert(bool $isTruncate = false): static;

    /**
     * execute bulk upsert
     * 
     * @param array|string $uniqueBy
     * @return static
     */
    public function bulkUpsert(array|string $uniqueBy): static;

    /*----------------------------------------*
     * Static Method
     *----------------------------------------*/

    /**
     * execute bulk insert
     * 
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @param bool $isTruncate
     * @return void
     */
    public static function insert(array|SupportCollection|EloquentCollection|Arrayable $data, bool $isTruncate = false): void;

    /**
     * execute bulk upsert
     * 
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @param array|string $uniqueBy
     * @return void
     */

    public static function upsert(array|SupportCollection|EloquentCollection|Arrayable $data, array|string $uniqueBy): void;
}
