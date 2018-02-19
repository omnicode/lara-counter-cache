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
    protected $_counterData = [];

    /**
     * @var _queryCounter
     */
    protected $_queryCounter;

    /**
     * @var _relationCounter
     */
    protected $_relationCounter;

    /**
     * @var int size
     */
    protected $_counterSize = 0;

    /**
     * @var
     */
    protected $_customResult = true;

    /**
     *
     */
    protected static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            self::runCounter($model);
        });
        static::updated(function ($model) {
            self::runCounter($model);
        });
        static::deleted(function ($model) {
            self::runCounter($model);
        });
    }

    /**
     * @param $model
     */
    public static function runCounter($model)
    {
        if (method_exists($model, 'addCounter')) {
            $model->_counterData = $model->addCounter();
            $model->_generateQueryCounter();
        }
    }

    /**
     * @throws \Exception
     */
    protected function _generateQueryCounter()
    {
        foreach ($this->_counterData as $index => $datum) {
            $table = is_numeric($index) ? $datum : $index;
            $this->_loadRelation($table);


            if (is_numeric($index)) {
                $item = snake_case(class_basename(get_class()));
                $item .= '_count';
                $this->_counterCaching($item, []);
                continue;
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
    protected function _loadRelation($table)
    {
        $key = $this->getKey();
        $new = $this->withTrashed()->find($key);
        $this->_relationCounter = $new->load($table)->{$table};
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function _setQueryCounter()
    {
        $this->_queryCounter = $this->newQueryWithoutScopes();
        $this->_queryCounter->where($this->_relationCounter->getForeignKey(), $this->_relationCounter->getKey());
        if (method_exists($this, 'trashed') && $this->trashed()) {
            $this->_queryCounter->where($this->getDeletedAtColumn(), null);
        }
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     * @return bool
     * @throws \Exception
     */
    protected function _counterCaching($name, $attr)
    {
        if (empty($this->_relationCounter)) {
            return false;
        }

        $this->_setQueryCounter();
        $this->_counterLogic($attr);
        $count = $this->_getQueryCounter();

        if ($this->_relationCounter->{$name} !== $count) {
            $this->_relationCounter->update([$name => $count]);
        }

        $this->_counterSize = 0;
        $this->_queryCounter = null;

    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function _getQueryCounter()
    {
        if ($this->_customResult && $this->_queryCounter instanceof Builder) {
            $queryCount = $this->_queryCounter->count();
            return $this->_counterSize > 0 ? $this->_counterSize : $queryCount;
        }

        throw new \Exception('The query counter must be an instance of ' . Builder::class);
    }

    /**
     * @param $attr
     */
    protected function _counterLogic($attr)
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
    protected function _counterWithConditions(&$attr)
    {
        if (!empty($attr['conditions'])) {
            foreach ($attr['conditions'] as $column => $value) {
                if (!is_numeric($column)) {
                    if (is_array($value)) {
                        $this->_queryCounter->whereIn($column, $value);
                    } else {
                        $this->_queryCounter->where($column, $value);
                    }
                }
            }
            unset($attr['conditions']);
        }
    }

    /**
     * @param $attr
     */
    protected function _counterWithMethods(&$attr)
    {
        if (!empty($attr['methods']) && is_array($attr['methods'])) {
            foreach ($attr['methods'] as $method => $args) {
                if (is_string($method) && method_exists($this->_queryCounter, $method)) {
                    $this->_queryCounter->{$method}(...$args);
                }
            }
            unset($attr['methods']);
        }
    }

    /**
     * @param $attr
     */
    protected function _counterWithClosure(&$attr)
    {
        if ($attr instanceof \Closure) {
            $func = $attr;
            $result = $func($this->_queryCounter);
            if (!empty($result)) {
                if (is_numeric($result)) {
                    $this->_counterSize = $result;
                } else {
                    $this->_customResult = (bool)$result;
                }
            }
            $attr = [];
        }
    }
}