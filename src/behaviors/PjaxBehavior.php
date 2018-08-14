<?php
/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/

namespace twoamint\Yii2\GridView\behaviors;

use twoamint\Yii2\GridView\GridView;
use Yii;
use yii\base\Behavior;
use yii\base\WidgetEvent;
use yii\widgets\Pjax;

class PjaxBehavior extends Behavior
{
    /** @var GridView */
    public $owner;

    public $pjaxOptions = [
        'timeout' => 30000,
        'options' => [
            'class' => 'grid-pjax-behavior'
        ]
    ];

    public $checkExecutionOnPjax = true;

    public $checkExecutionPjaxId = null;

    public $loaderUrl = null;

    /** @var null|Pjax */
    protected $pjax = null;

    public function events()
    {
        return [
            GridView::EVENT_BEFORE_INIT => 'initializePjax',
            GridView::EVENT_AFTER_RUN => 'finalizePjax'
        ];
    }

    public function getPjaxId()
    {
        if (!$this->isPjaxEnabled()) {
            return '';
        }

        return $this->checkExecutionPjaxId !== null ? $this->checkExecutionPjaxId : $this->pjax->options['id'];
    }

    public function initializePjax(WidgetEvent $event)
    {
        if (!$this->isPjaxEnabled()) {
            $event->isValid = true;
            return;
        }

        $this->pjax = Pjax::begin($this->pjaxOptions);

        if ($this->shouldHandlePjax()) {
            if (!isset($this->owner->options['id'])) {
                $this->owner->options['id'] = $this->owner->getId();
            }
            $event->isValid = !$this->checkExecutionOnPjax;
        }
    }

    public function isPjaxEnabled()
    {
        return !Yii::$app->getRequest()->getIsConsoleRequest();
    }

    public function shouldHandlePjax()
    {
        if (!$this->isPjaxEnabled()) {
            return false;
        }

        $headers = Yii::$app->getRequest()->getHeaders();
        $isPjax = $headers->get('X-Pjax');
        $isPjaxForThisGrid = $isPjax && (explode(' ', $headers->get('X-Pjax-Container'))[0] === '#' . $this->getPjaxId());

        return $isPjax && !$isPjaxForThisGrid;
    }

    public function finalizePjax()
    {
        if (!$this->isPjaxEnabled()) {
            return;
        }

        Pjax::end();
    }
}