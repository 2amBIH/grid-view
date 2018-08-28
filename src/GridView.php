<?php
/**
 * by Aleksandar Panic
 * Company: 2amigOS!
 *
 **/

namespace dvamigos\Yii2\GridView;

use Closure;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\base\WidgetEvent;
use yii\grid\Column;
use yii\grid\DataColumn;
use yii\helpers\Html;
use yii\helpers\Json;

class GridView extends \yii\grid\GridView
{
    const EVENT_BEFORE_INIT = 'grid.beforeInit';
    const EVENT_AFTER_INIT = 'grid.afterInit';

    const EVENT_BEFORE_RENDER_ITEMS = 'grid.beforeRenderItems';
    const EVENT_AFTER_RENDER_ITEMS = 'grid.afterRenderItems';

    const EVENT_BEFORE_INIT_COLUMNS = 'grid.beforeInitColumns';
    const EVENT_AFTER_INIT_COLUMNS = 'grid.afterInitColumns';

    const EVENT_AFTER_RENDER_TABLE_HEADER = 'grid.afterRenderTableHeader';
    const EVENT_BEFORE_RENDER_TABLE_HEADER = 'grid.beforeRenderTableHeader';

    const EVENT_BEFORE_RENDER_TABLE_BODY = 'grid.beforeRenderTableBody';
    const EVENT_AFTER_RENDER_TABLE_BODY = 'grid.afterRenderTableBody';

    const EVENT_BEFORE_RENDER_TABLE_FOOTER = 'grid.beforeRenderTableFooter';
    const EVENT_AFTER_RENDER_TABLE_FOOTER = 'grid.afterRenderTableFooter';

    const EVENT_BEFORE_RENDER_SUMMARY = 'grid.beforeRenderSummary';
    const EVENT_AFTER_RENDER_SUMMARY = 'grid.afterRenderSummary';

    const EVENT_BEFORE_RENDER_PAGER = 'grid.beforeRenderPager';
    const EVENT_AFTER_RENDER_PAGER = 'grid.afterRenderPager';

    const EVENT_BEFORE_RENDER_ROW = 'grid.beforeRenderRow';
    const EVENT_AFTER_RENDER_ROW = 'grid.afterRenderRow';

    const EVENT_BEFORE_RENDER_ERRORS = 'grid.beforeRenderRow';
    const EVENT_AFTER_RENDER_ERRORS = 'grid.afterRenderRow';

    const EVENT_GET_CUSTOM_SECTIONS = 'grid.getCustomSections';

    const EVENT_BEFORE_RENDER_CUSTOM_SECTION = 'grid.beforeRenderCustomSection';
    const EVENT_AFTER_RENDER_CUSTOM_SECTION = 'grid.afterRenderCustomSection';

    public $itemsContentGlue = "\n";
    public $tableBodyGlue = "\n";

    public $tableBodyTag = 'tbody';
    public $tableBodyOptions = [];

    public $tableHeaderTag = 'thead';
    public $tableHeaderGlue = "\n";
    public $tableHeaderOptions = [];

    public $tableFooterTag = 'tfoot';
    public $tableFooterGlue = "\n";
    public $tableFooterRowTag = 'tr';
    public $tableFooterOptions = [];

    public $tableCellGlue = '';

    public $tableRowTag = 'tr';
    public $tableCellTag = 'td';
    public $itemsContainerTag = 'table';

    public $captionTag = 'caption';
    public $colGroupTag = 'colgroup';
    public $colTag = 'col';
    public $colGroupGlue = "\n";
    public $pagerGlue = '';
    public $errorsGlue = '';
    public $summaryGlue = '';

    public $bufferOutput = false;

    protected $stopExecution = false;

    protected $customSections = null;

    public function init()
    {
        $event = new WidgetEvent();
        $this->trigger(self::EVENT_BEFORE_INIT, $event);

        if (!$event->isValid) {
            $this->stopExecution = true;
        } else {
            parent::init();
            $this->trigger(self::EVENT_AFTER_INIT);
        }
    }

    public function initColumns()
    {
        $columns = $this->triggerEventProcessor(self::EVENT_BEFORE_INIT_COLUMNS, ['data' => $this->columns])->getLastResult();

        if (empty($columns)) {
            $this->guessColumns();
        }

        foreach ($columns as $i => $column) {
            if (is_string($column)) {
                $column = $this->createDataColumn($column);
            } else if (is_array($column)) {
                $column = $this->createDataColumnObject($column);
            }

            if (!$column->visible) {
                unset($columns[$i]);
                continue;
            }

            $columns[$i] = $column;
        }

        $this->columns = $this->triggerEventProcessor(self::EVENT_AFTER_INIT_COLUMNS, ['data' => $columns])->getLastResult();
    }

    protected function createDataColumn($text)
    {
        if (!preg_match('/^([^:]+)(:(\w*))?(:(.*))?$/', $text, $matches)) {
            throw new InvalidConfigException('The column must be specified in the format of "attribute", "attribute:format" or "attribute:format:label"');
        }

        return $this->createDataColumnObject([
            'attribute' => $matches[1],
            'format' => isset($matches[3]) ? $matches[3] : 'text',
            'label' => isset($matches[5]) ? $matches[5] : null,
        ]);
    }

    public function createDataColumnObject($config)
    {
        return Yii::createObject(array_merge([
            'class' => $this->dataColumnClass ?: DataColumn::class,
            'grid' => $this,
        ], $config));
    }

    public function run()
    {
        if ($this->stopExecution) {
            return '';
        }

        if ($this->bufferOutput) {
            ob_start();
            parent::run();
            return ob_get_clean();
        }

        return parent::run();
    }

    public function setBehaviors($behaviors)
    {
        $this->attachBehaviors($behaviors);
    }

    /**
     * Renders the data models for the grid view.
     */
    public function renderItems()
    {
        $contents = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_ITEMS, ['data' => $this->getRenderItemContents()])->getLastResult();
        $content = implode($this->itemsContentGlue, $contents);

        $content = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_ITEMS, ['data' => $content])->getLastResult();
        return Html::tag($this->itemsContainerTag, $content, $this->tableOptions);
    }

    protected function getRenderItemContents()
    {
        $caption = $this->renderCaption();
        $columnGroup = $this->renderColumnGroup();
        $tableHeader = $this->showHeader ? $this->renderTableHeader() : false;
        $tableBody = $this->renderTableBody();
        $tableFooter = $this->showFooter ? $this->renderTableFooter() : false;

        return [
            'caption' => $caption,
            'columnGroup' => $columnGroup,
            'tableHeader' => $tableHeader,
            'tableFooter' => $tableFooter,
            'tableBody' => $tableBody,
        ];
    }

    /**
     * Renders the table body.
     * @return string the rendering result.
     */
    public function renderTableBody()
    {
        $models = array_values($this->dataProvider->getModels());
        $keys = $this->dataProvider->getKeys();

        $rows = [];

        foreach ($models as $index => $model) {
            $key = $keys[$index];
            if ($this->beforeRow !== null) {
                $row = call_user_func($this->beforeRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }

            $rows[] = $this->renderTableRow($model, $key, $index);

            if ($this->afterRow !== null) {
                $row = call_user_func($this->afterRow, $model, $key, $index, $this);
                if (!empty($row)) {
                    $rows[] = $row;
                }
            }
        }

        $rows = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_TABLE_BODY, ['data' => $rows])->getLastResult();

        if (empty($rows) && $this->emptyText !== false) {
            $colspan = count($this->columns);
            $contents = Html::tag($this->tableRowTag, Html::tag($this->tableCellTag, $this->renderEmpty(), ['colspan' => $colspan]));
        } else {
            $contents = implode($this->tableBodyGlue, $rows);
        }

        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_TABLE_BODY, ['data' => $contents])->getLastResult();

        return Html::tag($this->tableBodyTag, $contents, $this->tableBodyOptions);
    }


    /**
     * Renders the table header.
     * @return string the rendering result.
     */
    public function renderTableHeader()
    {
        $columns = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_TABLE_HEADER, ['data' => $this->columns])->getLastResult();

        $cells = [];
        foreach ($columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderHeaderCell();
        }

        $cellContents = implode('', $cells);

        $contents = [];

        $contents['header'] = Html::tag($this->tableRowTag, $cellContents, $this->headerRowOptions);

        if ($this->filterPosition === self::FILTER_POS_HEADER) {
            $contents = ['filter' => $this->renderFilters()] + $contents;
        } elseif ($this->filterPosition === self::FILTER_POS_BODY) {
            $contents['filter'] = $this->renderFilters();
        }

        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_TABLE_HEADER, ['data' => $contents])->getLastResult();

        return Html::tag($this->tableHeaderTag, implode($this->tableHeaderGlue, $contents), $this->tableHeaderOptions);
    }

    /**
     * Renders the table footer.
     * @return string the rendering result.
     */
    public function renderTableFooter()
    {
        $columns = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_TABLE_FOOTER, ['data' => $this->columns])->getLastResult();

        $cells = [];
        foreach ($columns as $column) {
            /* @var $column Column */
            $cells[] = $column->renderFooterCell();
        }

        $contents = [];

        $contents['footer'] = Html::tag($this->tableFooterRowTag, implode('', $cells), $this->footerRowOptions);

        if ($this->filterPosition === self::FILTER_POS_FOOTER) {
            $contents['filters'] = $this->renderFilters();
        }

        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_TABLE_FOOTER, ['data' => $contents])->getLastResult();

        return Html::tag($this->tableFooterTag, implode($this->tableFooterGlue, $contents), $this->tableFooterOptions);
    }

    /**
     * Renders a table row with the given data model and key.
     * @param mixed $model the data model to be rendered
     * @param mixed $key the key associated with the data model
     * @param int $index the zero-based index of the data model among the model array returned by [[dataProvider]].
     * @return string the rendering result
     */
    public function renderTableRow($model, $key, $index)
    {
        $cells = [];
        /* @var $column Column */
        foreach ($this->columns as $column) {
            $cells[] = $column->renderDataCell($model, $key, $index);
        }
        if ($this->rowOptions instanceof Closure) {
            $options = call_user_func($this->rowOptions, $model, $key, $index, $this);
        } else {
            $options = $this->rowOptions;
        }
        $options['data-key'] = is_array($key) ? Json::encode($key) : (string)$key;

        $data = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_ROW, ['data' => [
            'model' => $model,
            'key' => $key,
            'index' => $index,
            'cells' => $cells,
            'options' => $options
        ]])->getLastResult();

        $contents = Html::tag($this->tableRowTag, implode($this->tableCellGlue, $data['cells']), $data['options']);

        return $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_ROW, ['data' => $contents])->getLastResult();
    }

    /**
     * Renders the column group HTML.
     * @return bool|string the column group HTML or `false` if no column group should be rendered.
     */
    public function renderColumnGroup()
    {
        $requireColumnGroup = false;
        foreach ($this->columns as $column) {
            /* @var $column Column */
            if (!empty($column->options)) {
                $requireColumnGroup = true;
                break;
            }
        }
        if ($requireColumnGroup) {
            $cols = [];
            foreach ($this->columns as $column) {
                $cols[] = Html::tag($this->colTag, '', $column->options);
            }

            return Html::tag($this->colGroupTag, implode($this->colGroupGlue, $cols));
        } else {
            return false;
        }
    }

    /**
     * Renders the caption element.
     * @return bool|string the rendered caption element or `false` if no caption element should be rendered.
     */
    public function renderCaption()
    {
        if (!empty($this->caption)) {
            return Html::tag($this->captionTag, $this->caption, $this->captionOptions);
        } else {
            return false;
        }
    }

    public function renderSummary()
    {
        $contents = [];
        $contents['before'] = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_SUMMARY, ['data' => ''])->getLastResult();
        $contents['content'] = parent::renderSummary();
        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_SUMMARY, ['data' => $contents])->getLastResult();

        return implode($this->summaryGlue, $contents);
    }

    public function renderPager()
    {
        $contents = [];
        $contents['before'] = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_PAGER, ['data' => ''])->getLastResult();
        $contents['content'] = parent::renderPager();
        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_PAGER, ['data' => $contents])->getLastResult();

        return implode($this->pagerGlue, $contents);
    }


    public function renderErrors()
    {
        $contents = [];
        $contents['before'] = $this->triggerEventProcessor(self::EVENT_BEFORE_RENDER_ERRORS, ['data' => ''])->getLastResult();

        if ($this->filterModel instanceof Model && $this->filterModel->hasErrors()) {
            $contents['content'] = Html::errorSummary($this->filterModel, $this->filterErrorSummaryOptions);
        }

        $contents = $this->triggerEventProcessor(self::EVENT_AFTER_RENDER_PAGER, ['data' => $contents])->getLastResult();

        return implode($this->errorsGlue, $contents);
    }

    public function renderSection($name)
    {
        $result = parent::renderSection($name);

        if ($result === false) {
            if ($this->customSections === null) {
                $this->initCustomSections();
            }

            if (!empty($this->customSections[$name]) && is_callable($this->customSections[$name])) {
                $result = $this->customSections[$name]();
            }
        }

        return $result;
    }

    protected function initCustomSections()
    {
        $this->customSections = $this->triggerEventProcessor(self::EVENT_GET_CUSTOM_SECTIONS, ['data' => []])
            ->getLastResult();
    }

    /**
     * Triggers event processor which calls all behaviors with this events and returns event with results.
     *
     * @param $eventName string Event Name
     * @param $data array Data for that grid event. @see GridEvent
     * @return GridEvent
     */
    protected function triggerEventProcessor($eventName, $data)
    {
        $gridEvent = new GridEvent($data);
        $this->trigger($eventName, $gridEvent);
        return $gridEvent;
    }

    /**
     * Executes widget and returns output.
     * Useful when calling this grid view with begin().
     *
     * @return string Widget output.
     * @throws \Exception
     */
    public function executeWidget()
    {
        ob_start();
        ob_implicit_flush(false);

        try {
            static::end();
        } catch (\Exception $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $e;
        }

        return ob_get_clean();
    }
}