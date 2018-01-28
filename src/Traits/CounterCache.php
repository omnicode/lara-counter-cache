<?php

namespace LaraCounterCache\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait CounterCache
 * @package LaraCounterCache\Traits
 */
trait CounterCache
{
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
    private $counterSize = 0;

    /**
     * @var
     */
    private $customResult = true;

    /**
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->counterData = $model->addCounter();
                $model->generateQueryCounter();
            }
        });
        static::updated(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->counterData = $model->addCounter();
                $model->generateQueryCounter();
            }
        });
        static::deleted(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->counterData = $model->addCounter();
                $model->generateQueryCounter();
            }
        });
    }

    /**
     * @throws \Exception
     */
    private function generateQueryCounter()
    {
        foreach ($this->counterData as $index => $datum) {
            $table = is_numeric($index) ? $datum : $index;
            $this->_loadRelation($table);


            if (is_numeric($index)) {
                $item = snake_case(class_basename(get_class()));
                $item .= '_count';
                $this->_counterCaching($item, []);
            }

            if (!is_array($datum)) {
                $datum = [$datum];
            }

            foreach ($datum as $key => $item) {

                if (is_numeric($key)) {
                    $this->_counterCaching($item, []);
                } else {
                    $this->_counterCaching($key, $item);
                }
            }
        }
    }


    /**
     * @param $table
     */
    private function _loadRelation($table)
    {
        $key = $this->getKey();
        $new = $this->withTrashed()->find($key);
        $this->relationCounter = $new->load($table)->$table;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function _setQueryCounter()
    {
        $this->queryCounter = $this->newQueryWithoutScopes();
        $this->queryCounter->where($this->relationCounter->getForeignKey(), $this->relationCounter->getKey());

        if (method_exists($this, 'trashed') && $this->trashed()) {
            $this->queryCounter->where($this->getDeletedAtColumn(), null);
        }
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     * @return bool
     * @throws \Exception
     */
    private function _counterCaching($name, $attr)
    {
        if (empty($this->relationCounter)) {
            return false;
        }

        $this->_setQueryCounter();
        $this->_runCounter($attr);
        $count = $this->_getQueryCounter();

        if ($this->relationCounter->$name !== $count) {
            $this->relationCounter->update([$name => $count]);
        }

        $this->counterSize = 0;
        $this->queryCounter = null;

    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function _getQueryCounter()
    {
        if ($this->customResult && $this->queryCounter instanceof Builder) {
            $queryCount = $this->queryCounter->count();
            return $this->counterSize > 0 ? $this->counterSize : $queryCount;
        }

        throw new \Exception('The query counter must be an instance of ' . Builder::class);
    }

    /**
     * @param $attr
     */
    private function _runCounter($attr)
    {
        if (!empty($attr)) {
            $this->_counterWithClosure($attr);
            $this->_counterWithMethods($attr);
            $this->_counterWithConditions($attr);
        }
    }

    /**
     * @param $attr
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
            $result = $func($this->queryCounter);
            if (!empty($result)) {
                if (is_numeric($result)) {
                    $this->counterSize = $result;
                } else {
                    $this->customResult = (bool)$result;
                }
            }
            $attr = [];
        }
    }

    /**
     * @param $attributes
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