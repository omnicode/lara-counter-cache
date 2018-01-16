<?php

namespace LaraCounterCache\Traits;

use Illuminate\Database\Query\Builder;

/**
 * Trait CounterCache
 * @package LaraCounterCache\Traits
 */
trait CounterCache
{
    /**
     * @var bool
     */
    public $ignorCounterCache = false;

    /**
     * @var array
     */
    private $counterData = [];

    /**
     * @var queryCounter
     */
    private $queryCounter;

    /**
     * @var relationCounter
     */
    private $relationCounter;

    /**
     * @var int size
     */
    private $counterSize = 1;

    /**
     * @var type
     */
    private $counterType;

    /**
     * @var default type
     */
    private $counterDefaultType;

    /**
     * @var columns
     */
    private $counterColumns;

    /**
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->counterData = $model->addCounter();
                $model->generateQueryCounter('increment');
            }
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->counterData = $model->addCounter();
                $model->generateQueryCounter('decrement');
            }
        });

    }

    /**
     * @param $type
     */
    private function generateQueryCounter($type)
    {
        foreach ($this->counterData as $index => $datum) {
            $table = is_numeric($index) ? $datum : $index;
            $this->loadRelation($table);

            if (is_numeric($index)) {
                $item = snake_case(class_basename(get_class()));
                $item .= '_count';
                $this->setQueryCounter();
                $this->counterCaching($item, [], $type);
                continue;
            }

            if (!is_array($datum)) {
                $datum = [$datum];
            }

            foreach ($datum as $key => $item) {
                $this->setQueryCounter();
                if (is_numeric($key)) {
                    $this->counterCaching($item, [], $type);
                } else {
                    $this->counterCaching($key, $item, $type);
                }
            }
        }
    }

    /**
     * @param $table
     */
    private function loadRelation($table)
    {
        $this->relationCounter = $this->load($table)->$table;
    }

    /**
     * @return mixed
     */
    private function setQueryCounter()
    {
        if (count($this->relationCounter) === 0) {
            return;
        }

        $this->queryCounter = $this->newQueryWithoutScopes();
        $this->queryCounter->where($this->getKeyName(), $this->getKey());
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     * @return mixed
     */
    private function counterCaching($name, $attr, $type)
    {
        if (empty($this->relationCounter)) {
            return false;
        }

        if (!empty($attr) && is_array($attr)) {
            $this->_runCounter($attr);
        } else {
            $attr = [];
        }

        if ($this->_isCheckQuery()) {
            $this->relationCounter->{$type}($name, $this->counterSize, $attr);
        }
        $this->queryCounter = null;
        $this->counterSize = 1;
    }

    /**
     * @return bool|queryCounter
     */
    private function _isCheckQuery()
    {
        if ($this->queryCounter instanceof Builder) {
            return $this->queryCounter->count() > 0;
        }

        throw new \Exception('The query counter must be an instance of '.Builder::class);
    }

    /**
     * @param $attr
     */
    private function _runCounter(&$attr)
    {
        $this->_counterWithClosure($attr);
        $this->_counterWithMethods($attr);
        $this->_counterWithConditions($attr);
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     */
    private function _counterWithConditions(&$attr)
    {
        if (!empty($attr['conditions'])) {
            $this->_addCondition($attr['conditions']);
            unset($attr['conditions']);
        }
    }

    /**
     * @param $attr
     */
    private function _counterWithMethods(&$attr)
    {
        if (!empty($attr['methods']) && is_array($attr['methods'])) {
            foreach ($attr['methods'] as $method => $args) {
                if (is_string($method) && method_exists($this->queryCounter, $method)) {
                    $this->queryCounter->{$method}(...$args);
                }
            }
            unset($attr['methods']);
        }
    }

    /**
     * @param $attr
     */
    private function _counterWithClosure(&$attr)
    {
        if ($attr instanceof \Closure) {
            $func = $attr;
            $func($this->queryCounter, $this->counterSize);
            $attr = [];
        }
    }

    /**
     * @param $column
     * @param $value
     * @param string $cmp
     */
    private function _addCondition($attributes)
    {
        foreach ($attributes as $column => $value) {
            if (!is_numeric($column)) {
                if (is_array($value)) {
                    $this->queryCounter->whereIn($column, $value);
                } else {
                    $this->queryCounter->where($column, $value);
                }
            }
        }
    }

}