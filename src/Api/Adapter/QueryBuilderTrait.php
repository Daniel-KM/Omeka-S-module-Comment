<?php
namespace Comment\Api\Adapter;

use Doctrine\ORM\QueryBuilder;

/**
 * Trait to build queries.
 */
trait QueryBuilderTrait
{
    /**
     * Helper to search one or multiple values.
     *
     * @param QueryBuilder $qb
     * @param mixed $values One or multiple values.
     * @param string $column
     * @param string $target
     */
    protected function buildQueryValues(QueryBuilder $qb, $values, $column, $target)
    {
        if (is_array($values)) {
            if (count($values) == 1) {
                $this->buildQueryOneValue($qb, reset($values), $column);
            } else {
                $this->buildQueryMultipleValues($qb, $values, $column, $target);
            }
        } else {
            $this->buildQueryOneValue($qb, $values, $column);
        }
    }

    /**
     * Helper to search one value.
     *
     * @param QueryBuilder $qb
     * @param mixed $value
     * @param string $column
     */
    protected function buildQueryOneValue(QueryBuilder $qb, $value, $column)
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        if (is_null($value)) {
            $qb->andWhere($expr->isNull(
                $alias . '.' . $column
            ));
        } else {
            $qb->andWhere($expr->eq(
                $alias . '.' . $column,
                $this->createNamedParameter($qb, $value)
            ));
        }
    }

    /**
     * Helper to search multiple values ("OR").
     *
     * @param QueryBuilder $qb
     * @param array $values
     * @param string $column
     * @param string $target
     */
    protected function buildQueryMultipleValues(QueryBuilder $qb, array $values, $column, $target)
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        $hasNull = in_array(null, $values, true);
        $values = array_filter($values, function ($v) {
            return !is_null($v);
        });
        if ($values) {
            $valueAlias = $this->createAlias();
            $qb->innerJoin(
                $alias . '.' . $column,
                $valueAlias,
                'WITH',
                $hasNull
                    ? $expr->orX(
                        $expr->in(
                            $valueAlias . '.' . $target,
                            $this->createNamedParameter($qb, $values)
                        ),
                        $expr->isNull(
                            $valueAlias . '.' . $target
                        )
                    )
                    : $expr->in(
                        $valueAlias . '.' . $target,
                        $this->createNamedParameter($qb, $values)
                    )
            );
        }
        // Check no value only.
        elseif ($hasNull) {
            $qb->andWhere($expr->isNull(
                $alias . '.' . $column
            ));
        }
    }

    /**
     * Helper to search one or multiple ids.
     *
     * @internal There is no "0" for id, but "null" may be allowed.
     *
     * @param QueryBuilder $qb
     * @param mixed $values One or multiple ids.
     * @param string $column
     * @param string $target
     */
    protected function buildQueryIds(QueryBuilder $qb, $values, $column, $target = 'id')
    {
        if (is_array($values)) {
            if (count($values) == 1) {
                $this->buildQueryOneId($qb, reset($values), $column);
            } else {
                $this->buildQueryMultipleIds($qb, $values, $column, $target);
            }
        } else {
            $this->buildQueryOneId($qb, $values, $column);
        }
    }

    /**
     * Helper to search one id.
     *
     * @internal There is no "0" for id, but "null" may be allowed.
     *
     * @param QueryBuilder $qb
     * @param mixed $value
     * @param string $column
     */
    protected function buildQueryOneId(QueryBuilder $qb, $value, $column)
    {
        $value = ($value && is_numeric($value)) ? $value : null;
        $this->buildQueryOneValue($qb, $value, $column);
    }

    /**
     * Helper to search multiple ids.
     *
     * @internal There is no "0" for id, but "null" may be allowed.
     *
     * @param QueryBuilder $qb
     * @param array $values Multiple ids.
     * @param string $column
     * @param string $target
     */
    protected function buildQueryMultipleIds(QueryBuilder $qb, $values, $column, $target = 'id')
    {
        $hasEmpty = in_array(null, $values);
        $values = array_filter($values, 'is_numeric');
        if ($hasEmpty) {
            $values[] = null;
        }
        $this->buildQueryMultipleValues($qb, $values, $column, $target);
    }

    /**
     * Helper to search one or multiple values on the same entity.
     *
     * @param QueryBuilder $qb
     * @param mixed $values One or multiple values.
     * @param string $target
     */
    protected function buildQueryValuesItself(QueryBuilder $qb, $values, $target)
    {
        if (is_array($values)) {
            if (count($values) == 1) {
                $this->buildQueryOneValue($qb, reset($values), $target);
            } else {
                $this->buildQueryMultipleValuesItself($qb, $values, $target);
            }
        } else {
            $this->buildQueryOneValue($qb, $values, $target);
        }
    }

    /**
     * Helper to search multiple values ("OR") on the same entity.
     *
     * @param QueryBuilder $qb
     * @param array $values
     * @param string $target
     */
    protected function buildQueryMultipleValuesItself(QueryBuilder $qb, array $values, $target)
    {
        $isOldOmeka = \Omeka\Module::VERSION < 2;
        $alias = $isOldOmeka ? $this->getEntityClass() : 'omeka_root';
        $expr = $qb->expr();

        $hasNull = in_array(null, $values, true);
        $values = array_filter($values, function ($v) {
            return !is_null($v);
        });
        if ($values) {
            $valueAlias = $this->createAlias();
            $qb
                ->innerJoin(
                    $alias,
                    $valueAlias,
                    'WITH',
                    $expr->eq(
                        $alias . '.id',
                        $valueAlias . '.id'
                    )
                )
                ->andWhere(
                    $hasNull
                    ? $expr->orX(
                        $expr->in(
                            $valueAlias . '.' . $target,
                            $this->createNamedParameter($qb, $values)
                        ),
                        $expr->isNull(
                            $valueAlias . '.' . $target
                        )
                    )
                    : $expr->in(
                        $valueAlias . '.' . $target,
                        $this->createNamedParameter($qb, $values)
                    )
                );
        }
        // Check no value only.
        elseif ($hasNull) {
            $qb->andWhere($expr->isNull(
                $alias . '.' . $target
            ));
        }
    }

    /**
     * Helper to search one or multiple ids on the same entity.
     *
     * @internal There is no "0" for id, but "null" may be allowed.
     *
     * @param QueryBuilder $qb
     * @param mixed $values One or multiple ids.
     * @param string $target
     */
    protected function buildQueryIdsItself(QueryBuilder $qb, $values, $target = 'id')
    {
        if (is_array($values)) {
            if (count($values) == 1) {
                $this->buildQueryOneId($qb, reset($values), $target);
            } else {
                $this->buildQueryMultipleIdsItself($qb, $values, $target);
            }
        } else {
            $this->buildQueryOneId($qb, $values, $target);
        }
    }

    /**
     * Helper to search multiple ids on the same entity.
     *
     * @internal There is no "0" for id, but "null" may be allowed.
     *
     * @param QueryBuilder $qb
     * @param array $values Multiple ids.
     * @param string $target
     */
    protected function buildQueryMultipleIdsItself(QueryBuilder $qb, $values, $target = 'id')
    {
        $hasEmpty = in_array(null, $values);
        $values = array_filter($values, 'is_numeric');
        if ($hasEmpty) {
            $values[] = null;
        }
        $this->buildQueryMultipleValuesItself($qb, $values, $target);
    }
}
