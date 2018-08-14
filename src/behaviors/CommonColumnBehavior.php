<?php
/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/

namespace twoamint\Yii2\GridView\behaviors;


use twoamint\Yii2\GridView\GridEvent;
use twoamint\Yii2\GridView\GridView;
use yii\base\Behavior;
use yii\base\Exception;
use yii\helpers\ArrayHelper;

class CommonColumnBehavior extends Behavior
{
    public $attributeParam = 'attribute';

    /**
     * @var array
     * Type map config:
     *
     * [
     *    'type' => [
     *        'class' => 'ColumnClass',
     *        // ...more config
     *    ]
     * ]
     *
     * Then in grid view you can set for a column:
     *
     * ['type', 'attribute', 'config1' => 'config2']
     *
     * You can define this in DI.
     */
    public $typeMap = [];

    public function events()
    {
        return [
            GridView::EVENT_BEFORE_INIT_COLUMNS => 'processCommonColumns'
        ];
    }

    public function processCommonColumns(GridEvent $event)
    {
        $columns = $event->getLastResult();

        foreach ($columns as $index => $column) {
            if (!is_array($column) || empty($column[0])) {
                continue;
            }

            $type = ArrayHelper::remove($column, 0, '');
            $attribute = ArrayHelper::remove($column, 1, '');

            if (empty($this->typeMap[$type])) {
                throw new Exception("Common column type '{$type}' cannot be resolved.");
            }

            $columns[$index] = ArrayHelper::merge($this->typeMap[$type], $column);

            if (!empty($attribute)) {
                $columns[$index][$this->attributeParam] = $attribute;
            }
        }

        $event->pushResult($this, $columns);
    }
}