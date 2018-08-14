<?php
/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/

namespace twoamint\Yii2\GridView;


use Yii;
use yii\base\Event;
use yii\helpers\ArrayHelper;

class GridEvent extends Event
{
    protected $_results = [];

    public function init()
    {
        $this->pushResult(null, $this->data);
    }

    public function pushResult($behavior, $data)
    {
        $this->_results[] = ['behavior' => $behavior, 'result' => $data];
    }

    public function getAllResults()
    {
        return $this->_results;
    }

    public function getLastResult($param = null, $default = null)
    {
        if (empty($this->_results)) {
             return null;
        }

        $result = $this->_results[count($this->_results) - 1]['result'];

        if ($param === null) {
            return $result;
        }

        return ArrayHelper::getValue($result, $param, $default);
    }

    public function getLastBehavior()
    {
        if (empty($this->_results)) {
            return null;
        }

        return $this->_results[count($this->_results) - 1]['behavior'];
    }

    public function resetData($data)
    {
        $this->handled = false;
        $this->_results = [];
        Yii::configure($this, $data);
        $this->init();
    }
}