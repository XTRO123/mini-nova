<?php

namespace Mini\Database;

use Mini\Database\Query\Expression;
use Mini\Database\Query\Builder as QueryBuilder;
use Mini\Database\Model;
use Mini\Support\Arr;

use Closure;


class Builder
{
    /**
     * The base Query Builder instance.
     *
     * @var \Mini\Database\Query\Builder
     */
    protected $query;

    /**
     * The model being queried.
     *
     * @var \Mini\Database\Model
     */
    protected $model;


    /**
     * Create a new Model Query Builder instance.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return mixed|static|null
     */
    public function find($id, $columns = array('*'))
    {
        if (is_array($id)) {
            return $this->findMany($id, $columns);
        }

        $query = $this->query->where($this->model->getKeyName(), '=', $id);

        return $query->first($columns);
    }

    /**
     * Find a model by its primary key.
     *
     * @param  array  $ids
     * @param  array  $columns
     * @return array|null|static
     */
    public function findMany($ids, $columns = array('*'))
    {
        if (empty($ids)) return null;

        $query = $this->query->whereIn($this->model->getKeyName(), $ids);

        return $query->get($columns);
    }

    /**
     * Get a paginator for the "select" statement.
     *
     * @param  int    $perPage
     * @param  array  $columns
     * @return \Mini\Pagination\Paginator
     */
    public function paginate($perPage = null, $columns = array('*'))
    {
        // Get the Pagination Factory instance.
        $paginator = $this->query->getConnection()->getPaginator();

        $perPage = $perPage ?: $this->model->getPerPage();

        if (isset($this->query->groups)) {
            return $this->groupedPaginate($paginator, $perPage, $columns);
        } else {
            return $this->ungroupedPaginate($paginator, $perPage, $columns);
        }
    }

    /**
     * Get a paginator for a grouped statement.
     *
     * @param  \Mini\Pagination\Environment  $paginator
     * @param  int    $perPage
     * @param  array  $columns
     * @return \Mini\Pagination\Paginator
     */
    protected function groupedPaginate($paginator, $perPage, $columns)
    {
        $results = $this->get($columns);

        return $this->query->buildRawPaginator($paginator, $results, $perPage);
    }

    /**
     * Get a paginator for an ungrouped statement.
     *
     * @param  \Mini\Pagination\Environment  $paginator
     * @param  int    $perPage
     * @param  array  $columns
     * @return \Mini\Pagination\Paginator
     */
    protected function ungroupedPaginate($paginator, $perPage, $columns)
    {
        $total = $this->query->getPaginationCount();

        $page = $paginator->getCurrentPage($total);

        $query = $this->query->forPage($page, $perPage);

        // Retrieve the results from database.
        $results = $query->get($columns);

        return $paginator->make($results, $total, $perPage);
    }

    /**
     * Get a Paginator only supporting simple next and previous links.
     *
     * This is more efficient on larger data-sets, etc.
     *
     * @param  int    $perPage
     * @param  array  $columns
     * @return \Mini\Pagination\Paginator
     */
    public function simplePaginate($perPage = null, $columns = array('*'))
    {
        // Get the Pagination Factory instance.
        $paginator = $this->connection->getPaginator();

        $perPage = $perPage ?: $this->model->getPerPage();

        $page = $paginator->getCurrentPage();

        $query = $this->skip(($page - 1) * $perPage)->take($perPage + 1);

        // Retrieve the results from database.
        $results = $query->get($columns);

        return $paginator->make($results, $perPage);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        return $this->query->insert($this->addTimestamps($values));
    }

    /**
     * Insert a new Record and get the value of the primary key.
     *
     * @param  array   $values
     * @return int
     */
    public function insertGetId(array $values)
    {
        return $this->query->insertGetId($this->addTimestamps($values));
    }

    /**
     * Add the "created at" and "updated at" columns to an array of values.
     *
     * @param  array  $values
     * @return array
     */
    protected function addTimestamps(array $values)
    {
        if (! $this->model->usesTimestamps()) return $values;

        $columns = array(
            $this->model->getCreatedAtColumn(),
            $this->model->getUpdatedAtColumn(),
        );

        $timestamp = $this->model->freshTimestampString();

        foreach ($columns as $column) {
            if (is_null($value = Arr::get($values, $column))) {
                Arr::set($values, $column, $timestamp);
            }
        }

        return $values;
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        return $this->query->update($this->addUpdatedAtColumn($values));
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array())
    {
        $extra = $this->addUpdatedAtColumn($extra);

        return $this->query->decrement($column, $amount, $extra);
    }

    /**
     * Add the "updated at" column to an array of values.
     *
     * @param  array  $values
     * @return array
     */
    protected function addUpdatedAtColumn(array $values)
    {
        if (! $this->model->usesTimestamps()) return $values;

        $column = $this->model->getUpdatedAtColumn();

        return Arr::add($values, $column, $this->model->freshTimestampString());
    }

    /**
     * Get the underlying query builder instance.
     *
     * @return \Mini\Database\Query\Builder|static
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     *
     * @param  \Mini\Database\Query\Builder  $query
     * @return void
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Mini\Database\ORM\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     *
     * @param  \Mini\Database\ORM\Model  $model
     * @return \Mini\Database\ORM\Builder
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array(array($this->query, $method), $parameters);

        if ($result === $this->query) return $this;

        return $result;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     *
     * @return void
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

}
