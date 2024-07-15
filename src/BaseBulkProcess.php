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
 * 一括処理機能を持つ基底クラス
 * 
 * @package YukataRm\Laravel\BulkProcess
 */
abstract class BaseBulkProcess implements BulkProcessInterface
{
    /*----------------------------------------*
     * Constructor
     *----------------------------------------*/

    /**
     * 一括処理に使用するデータ
     *
     * @var \Illuminate\Support\Collection
     */
    protected SupportCollection $data;

    /**
     * バリデーションに失敗したデータ
     * 
     * @var \Illuminate\Support\Collection
     */
    protected SupportCollection $failureData;

    /**
     * BulkProcessのコンストラクタ
     *
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @throws \InvalidArgumentException
     */
    function __construct(array|SupportCollection|EloquentCollection|Arrayable $data)
    {
        // dataをCollectionに統一する
        $collection = match (true) {
            is_array($data)                     => collect($data),
            $data instanceof EloquentCollection => $data->toBase(),
            $data instanceof SupportCollection  => $data,
            $data instanceof Arrayable          => collect($data->toArray()),

            default => null,
        };

        // collectionがCollectionでない場合は例外をthrowする
        if (!$collection instanceof SupportCollection) throw new \InvalidArgumentException("Invalid data type: " . gettype($data));

        // dataが空の場合は例外をthrowする
        if ($collection->isEmpty()) throw new \InvalidArgumentException("data must not be empty");

        // dataのバリデーションを行い、失敗したデータをfailureDataに格納する
        $this->failureData = $collection->reject(function ($item) {
            return !$this->validate($item);
        });

        // collectionからfailureDataを除外する
        $collection = $collection->diff($this->failureData);

        // collectionが空の場合は例外をthrowする
        if ($collection->isEmpty()) throw new \InvalidArgumentException("data must not be empty");

        // dataの成型処理を行う
        $formatted = $collection->map(function ($item) {
            return $this->format($item);
        });

        // formattedをdataに格納する
        $this->data = $formatted;
    }

    /**
     * dataのバリデーションを行う
     * 
     * @param mixed $item
     * @return bool
     */
    abstract protected function validate(mixed $item): bool;

    /**
     * dataの成型処理を行う
     * 
     * @param mixed $item
     * @return array
     */
    abstract protected function format(mixed $item): array;

    /**
     * 一括処理に使用するデータ
     *
     * @return \Illuminate\Support\Collection
     */
    public function data(): SupportCollection
    {
        return $this->data;
    }

    /**
     * 一括処理に使用するデータの配列
     * 
     * @return array
     */
    public function dataArray(): array
    {
        return $this->data->toArray();
    }

    /**
     * 一括処理に使用するデータの件数
     * 
     * @return int
     */
    public function dataCount(): int
    {
        return $this->data->count();
    }

    /**
     * バリデーションに失敗したデータ
     *
     * @return \Illuminate\Support\Collection
     */
    public function failureData(): SupportCollection
    {
        return $this->failureData;
    }

    /**
     * バリデーションに失敗したデータの配列
     * 
     * @return array
     */
    public function failureDataArray(): array
    {
        return $this->failureData->toArray();
    }

    /**
     * バリデーションに失敗したデータの件数
     * 
     * @return int
     */
    public function failureDataCount(): int
    {
        return $this->failureData->count();
    }

    /*----------------------------------------*
     * Property
     *----------------------------------------*/

    /**
     * 一括処理を行う件数の閾値
     * 
     * @var int
     */
    protected int $limit = 1000;

    /**
     * 一括処理を行うテーブルに紐づいたModelのClass名
     * 
     * @var string
     */
    protected string $modelClass;

    /**
     * 一括処理を行う件数の閾値
     *
     * @return int
     */
    public function limit(): int
    {
        return $this->limit;
    }

    /**
     * 一括処理を行う件数の閾値を設定する
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
     * 一括処理を行うテーブルに紐づいたModelのClass名
     *
     * @return string
     */
    public function modelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * 一括処理を行うテーブルに紐づいたModelのClass名を設定する
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
     * 一括処理を行うテーブルに紐づいたModelのインスタンスを生成する
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function model(): Model
    {
        return new $this->modelClass;
    }

    /**
     * 一括処理を行うテーブルに紐づいたModelのクラス名をインスタンスから設定する
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
     * 一括処理を行うテーブルに紐づいたQueryBuilderを取得する
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    public function queryBuilder(): Builder
    {
        return DB::table($this->model()->getTable());
    }

    /**
     * 一括処理を行うテーブルをTruncateする
     * 
     * @return static
     */
    public function truncateTable(): static
    {
        $this->queryBuilder()->truncate();

        return $this;
    }

    /**
     * dataの一括処理を行う
     * 
     * @param \Closure $callback
     * @return static
     */
    public function bulkProcess(\Closure $callback): static
    {
        // dataを一括処理する
        $this->data()->chunk($this->limit())->each(function (SupportCollection $chunk) use ($callback) {
            $callback($chunk);
        });

        return $this;
    }

    /**
     * dataを一括挿入する
     * 
     * @param bool $isTruncate
     * @return static
     */
    public function bulkInsert(bool $isTruncate = false): static
    {
        // テーブルをTruncateする
        if ($isTruncate) $this->truncateTable();

        // dataを一括挿入する
        $this->bulkProcess(function (SupportCollection $chunk) {
            $this->queryBuilder()->insert($chunk->toArray());
        });

        return $this;
    }

    /**
     * uniqueByのカラムを基に、存在する場合は更新、存在しない場合は挿入する
     * 
     * @param array|string $uniqueBy
     * @return static
     */
    public function bulkUpsert(array|string $uniqueBy): static
    {
        // dataをUpsertする
        $this->bulkProcess(function (SupportCollection $chunk) use ($uniqueBy) {
            $this->queryBuilder()->upsert($chunk->toArray(), $uniqueBy);
        });

        return $this;
    }

    /*----------------------------------------*
     * Static Method
     *----------------------------------------*/

    /**
     * dataを一括挿入する
     * 
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @param bool $isTruncate
     * @return void
     */
    public static function insert(array|SupportCollection|EloquentCollection|Arrayable $data, bool $isTruncate = false): void
    {
        // BulkProcessのインスタンスを生成する
        $instance = new static($data);

        // dataを一括挿入する
        $instance->bulkInsert($isTruncate);
    }

    /**
     * uniqueByのカラムを基に、存在する場合は更新、存在しない場合は挿入する
     * 
     * @param array|\Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Support\Arrayable $data
     * @param array|string $uniqueBy
     * @return void
     */
    public static function upsert(array|SupportCollection|EloquentCollection|Arrayable $data, array|string $uniqueBy): void
    {
        // BulkProcessのインスタンスを生成する
        $instance = new static($data);

        // dataを一括挿入する
        $instance->bulkUpsert($uniqueBy);
    }
}
