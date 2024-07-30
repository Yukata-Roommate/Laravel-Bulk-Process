<?php

namespace YukataRm\Laravel\BulkProcess;

use YukataRm\Laravel\BulkProcess\Interface\BulkProcessInterface;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Base Bulk Process
 * 
 * @package YukataRm\Laravel\BulkProcess
 */
abstract class BaseBulkProcess implements BulkProcessInterface
{
    /*----------------------------------------*
     * Constructor
     *----------------------------------------*/

    /**
     * data for bulk process
     *
     * @var \Illuminate\Support\Collection
     */
    protected SupportCollection $data;

    /**
     * invalid data
     * 
     * @var \Illuminate\Support\Collection
     */
    protected SupportCollection $invalidData;

    /**
     * constructor
     *
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     */
    function __construct(array|SupportCollection|EloquentCollection|Arrayable $data)
    {
        $collection = match (true) {
            is_array($data)                     => collect($data),
            $data instanceof EloquentCollection => $data->toBase(),
            $data instanceof SupportCollection  => $data,
            $data instanceof Arrayable          => collect($data->toArray()),

            default => null,
        };

        if (!$collection instanceof SupportCollection) throw new \InvalidArgumentException("invalid data type: " . gettype($data));

        if ($collection->isEmpty()) throw new \InvalidArgumentException("data required");

        $this->invalidData = $collection->reject(function ($item) {
            return !$this->validate($item);
        });

        $collection = $collection->diff($this->invalidData);

        if ($collection->isEmpty()) throw new \InvalidArgumentException("all data is invalid");

        $formatted = $collection->map(function ($item) {
            return $this->format($item);
        });

        $this->data = $formatted;
    }

    /**
     * validate data
     * 
     * @param mixed $item
     * @return bool
     */
    abstract protected function validate(mixed $item): bool;

    /**
     * format data
     * 
     * @param mixed $item
     * @return array
     */
    abstract protected function format(mixed $item): array;

    /**
     * get data
     *
     * @return \Illuminate\Support\Collection
     */
    public function data(): SupportCollection
    {
        return $this->data;
    }

    /**
     * get data as array
     * 
     * @return array
     */
    public function dataArray(): array
    {
        return $this->data->toArray();
    }

    /**
     * get data count
     * 
     * @return int
     */
    public function dataCount(): int
    {
        return $this->data->count();
    }

    /**
     * get invalid data
     *
     * @return \Illuminate\Support\Collection
     */
    public function invalidData(): SupportCollection
    {
        return $this->invalidData;
    }

    /**
     * get invalid data as array
     * 
     * @return array
     */
    public function invalidDataArray(): array
    {
        return $this->invalidData->toArray();
    }

    /**
     * get invalid data count
     * 
     * @return int
     */
    public function invalidDataCount(): int
    {
        return $this->invalidData->count();
    }

    /*----------------------------------------*
     * Property
     *----------------------------------------*/

    /**
     * limit of bulk process
     * 
     * @var int
     */
    protected int $limit = 1000;

    /**
     * class name of Model
     * 
     * @var string
     */
    protected string $modelClass;

    /**
     * get limit
     *
     * @return int
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * set limit
     * 
     * @param int $limit
     * @return static
     */
    public function setLimit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * get class name of Model
     *
     * @return string
     */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * set class name of Model
     * 
     * @param string $modelClass
     * @return static
     */
    public function setModelClass(string $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * get Model instance
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function model(): Model
    {
        return new $this->modelClass;
    }

    /**
     * set class name of Model from instance
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return static
     */
    public function setModel(Model $model): static
    {
        $this->modelClass = get_class($model);

        return $this;
    }

    /*----------------------------------------*
     * Bulk Process
     *----------------------------------------*/

    /**
     * get Builder
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    public function queryBuilder(): Builder
    {
        return DB::table($this->model()->getTable());
    }

    /**
     * truncate table
     * 
     * @return static
     */
    public function truncateTable(): static
    {
        $this->queryBuilder()->truncate();

        return $this;
    }

    /**
     * execute bulk process
     * 
     * @param \Closure $callback
     * @return static
     */
    public function bulkProcess(\Closure $callback): static
    {
        $this->data()->chunk($this->limit())->each(function (SupportCollection $chunk) use ($callback) {
            $callback($chunk);
        });

        return $this;
    }

    /**
     * execute bulk insert
     * 
     * @param bool $isTruncate
     * @return static
     */
    public function bulkInsert(bool $isTruncate = false): static
    {
        if ($isTruncate) $this->truncateTable();

        $this->bulkProcess(function (SupportCollection $chunk) {
            $this->queryBuilder()->insert($chunk->toArray());
        });

        return $this;
    }

    /**
     * execute bulk upsert
     * 
     * @param array|string $uniqueBy
     * @return static
     */
    public function bulkUpsert(array|string $uniqueBy): static
    {
        $this->bulkProcess(function (SupportCollection $chunk) use ($uniqueBy) {
            $this->queryBuilder()->upsert($chunk->toArray(), $uniqueBy);
        });

        return $this;
    }

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
    public static function insert(array|SupportCollection|EloquentCollection|Arrayable $data, bool $isTruncate = false): void
    {
        $instance = new static($data);

        $instance->bulkInsert($isTruncate);
    }

    /**
     * execute bulk upsert
     * 
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @param array|string $uniqueBy
     * @return void
     */
    public static function upsert(array|SupportCollection|EloquentCollection|Arrayable $data, array|string $uniqueBy): void
    {
        $instance = new static($data);

        $instance->bulkUpsert($uniqueBy);
    }
}
