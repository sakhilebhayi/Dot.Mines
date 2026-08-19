<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Trait PhpStanEloquentStubs
 *
 * Provides PHPStan with method stubs for common Eloquent builder methods.
 * This helps static analysis tools understand Eloquent's dynamic query building methods.
 *
 * All actual functionality is provided by Illuminate\Database\Eloquent\Model
 * This trait is purely for PHPStan type hinting.
 *
 * Usage: Add this trait to any Eloquent Model class
 *
 * @example
 *     class User extends Model {
 *         use PhpStanEloquentStubs;
 *     }
 */
trait PhpStanEloquentStubs
{
    /**
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return Builder|$this
     */
    public static function where($column, $operator = null, $value = null) {}

    /**
     * @param  string  $column
     * @param  array  $values
     * @return Builder|$this
     */
    public static function whereIn($column, $values) {}

    /**
     * @param  string  $column
     * @param  array  $values
     * @return Builder|$this
     */
    public static function whereNotIn($column, $values) {}

    /**
     * @param  mixed  $id
     * @param  array  $columns
     * @return static|null
     */
    public static function find($id, $columns = ['*']) {}

    /**
     * @param  mixed  $id
     * @param  array  $columns
     * @return static
     */
    public static function findOrFail($id, $columns = ['*']) {}

    /**
     * @param  array  $columns
     * @return Collection|static[]
     */
    public static function get($columns = ['*']) {}

    /**
     * @param  array  $columns
     * @return static|null
     */
    public static function first($columns = ['*']) {}

    /**
     * @param  array  $columns
     * @return static
     */
    public static function firstOrFail($columns = ['*']) {}

    /**
     * @param  array  $columns
     * @return Collection|static[]
     */
    public static function all($columns = ['*']) {}

    /**
     * @return bool
     */
    public static function exists() {}

    /**
     * @return int
     */
    public static function count() {}

    /**
     * @param  string|array  $value
     * @param  string|null  $key
     * @return Collection
     */
    public static function pluck($value, $key = null) {}

    /**
     * @param  array|string  ...$columns
     * @return Builder|$this
     */
    public static function select(...$columns) {}

    /**
     * @param  string  $column
     * @param  string  $direction
     * @return Builder|$this
     */
    public static function orderBy($column, $direction = 'asc') {}

    /**
     * @param  string  $column
     * @return Builder|$this
     */
    public static function latest($column = 'created_at') {}

    /**
     * @param  string  $column
     * @return Builder|$this
     */
    public static function oldest($column = 'created_at') {}

    /**
     * @param  int  $value
     * @return Builder|$this
     */
    public static function limit($value) {}

    /**
     * @param  int  $value
     * @return Builder|$this
     */
    public static function offset($value) {}

    /**
     * @param  int  $value
     * @return Builder|$this
     */
    public static function take($value) {}

    /**
     * @param  int  $value
     * @return Builder|$this
     */
    public static function skip($value) {}

    /**
     * @param  string  $table
     * @param  string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return Builder|$this
     */
    public static function join($table, $first, $operator = null, $second = null) {}

    /**
     * @param  string  $table
     * @param  string  $first
     * @param  string|null  $operator
     * @param  string|null  $second
     * @return Builder|$this
     */
    public static function leftJoin($table, $first, $operator = null, $second = null) {}

    /**
     * @return Builder|$this
     */
    public static function distinct() {}

    /**
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return LengthAwarePaginator
     */
    public static function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null) {}

    /**
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters) {}

    /**
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters) {}
}
