<?php
namespace thrieu\statreport;

use Yii;
use yii\base\Widget;
use yii\bootstrap\ButtonGroup;
use yii\helpers\Html;
use yii\data\ArrayDataProvider;
use yii\base\InvalidConfigException;

use yii\helpers\Json;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;

class StatReport extends Widget {
    public $htmlOptions = [];
    public $series = [];
    public $url;
    public $dataTablesOptions = [];
    public $tableOptions = [];
    public $chartOptions = [];
    public $showCaption = true;
    public $captionOptions = [];
    public $params = [];
    public $switchBtnTableLabel = '<i class="fa fa-table"></i>';
    public $switchBtnChartLabel = '<i class="fa fa-line-chart"></i>';
    public $bootstrap = true;
    public $responsive = true;
    public $highcharts;
    public $chartSeries = [];
    public $columns = [];
    public $autoloading = true;
    public $highstock = false;
    // Handlers
    public $onSuccess;
    public $onFailure;
    public $onBeforeRequest;
    public $onError;

    protected $buttonGroupOptions = [];

    const VIEW_CHART = 'chart';
    const VIEW_TABLE = 'table';

    public function init() {
        if( ! $this->url) {
            throw new InvalidConfigException('The "url" property must be specified.');
        }

        // determine the ID of the container element
        if (isset($this->htmlOptions['id'])) {
            $this->id = $this->htmlOptions['id'];
        } else {
            $this->id = $this->htmlOptions['id'] = $this->getId();
        }
        Html::addCssClass($this->htmlOptions, 'statreport-container');
        Html::addCssClass($this->captionOptions, 'statreport-caption');
        $this->initSeries();
        $this->initColumns();
        $this->initButtonOptions();
    }

    public function run() {
        // render the container element
        echo Html::beginTag('div', $this->htmlOptions);

        if($this->showCaption) {
            echo Html::beginTag('div', ArrayHelper::merge(
                [
                    'class' => $this->captionOptions['class']
                ],
                $this->captionOptions
            ));

            echo Html::endTag('div');
        }

        $this->renderHighcharts();
        $this->renderDataTablesGridView();

        echo ButtonGroup::widget($this->buttonGroupOptions);

        echo Html::endTag('div');

        StatReportAsset::register($this->view);
        if($this->bootstrap) {
            DataTablesBootstrapAsset::register($this->view);
        }
        if($this->responsive) {
            DataTablesResponsiveAsset::register($this->view);
            $this->dataTablesOptions = ArrayHelper::merge(['responsive' => true], $this->dataTablesOptions);
        }

        $this->renderChartSeries();

        $this->renderJavaScript();
        parent::run();
    }

    public function encodeParams() {
        $params = array();
        foreach($this->params as $key => $param) {
            if(is_array($param) && count($param) == 2) {
                list($model, $attribute) = $param;
                $params[Html::getInputName($model, $attribute)] = new JsExpression('$("#'.Html::getInputId($model, $attribute).'")');
            } else {
                $params[$key] = $param;
            }
        }
        return $params;
    }

    public function renderChartSeries() {
        foreach($this->series as $s) {
            if($s->isInChart) {
                $this->chartSeries[] = $s->header;
            }
        }
        $this->chartSeries = Json::encode($this->chartSeries);
    }

    public function renderJavaScript() {
        $js = "var chartSeries{$this->id} = [{$this->chartSeries}];\n";
        $options = Json::encode([
            'table' => new JsExpression("$('#{$this->id} > .grid-view > table').eq(0)"),
            'chart' => new JsExpression("$('#{$this->highcharts->getId()}')"),
            'url' => $this->url,
            'params' => $this->encodeParams(),
            'dataTablesOptions' => $this->dataTablesOptions,
            'chartSeries' => new JsExpression("chartSeries{$this->id}"),
            'chartOptions' => $this->highcharts->options,
            'onSuccess' => ( ! is_null($this->onSuccess) ? $this->onSuccess : null),
            'onFailure' => ( ! is_null($this->onFailure) ? $this->onFailure : null),
            'onBeforeRequest' => ( ! is_null($this->onBeforeRequest) ? $this->onBeforeRequest : null),
            'onError' => ( ! is_null($this->onError) ? $this->onError : null),
            'autoloading' => $this->autoloading,
        ], JSON_NUMERIC_CHECK);
        $js .= "$('#{$this->id}').statReport({$options});\n";
        $this->view->registerJs($js);
    }

    public function renderHighcharts() {
        $class = 'miloschuman\highcharts\\';
        $class .= ! $this->highstock ? 'Highcharts' : 'Highstock';
        $this->highcharts = $class::begin([
            'htmlOptions' => [
                'data-view-role' => static::VIEW_CHART,
                'class' => 'statreport-view',
            ],
            'options' => $this->chartOptions,
        ]);
        $this->highcharts->scripts = [ ! $this->highstock ? 'highcharts' : 'highstock', 'modules/data'];
        $this->highcharts->callback = 'createHighcharts' . $this->getId();
        $this->highcharts->end();
    }

    public function renderDataTablesGridView() {
        echo DataTablesGridView::widget([
            'filterModel' => null,
            'emptyText' => null,
            'columns' => $this->columns,
            'dataProvider' => new ArrayDataProvider([
                    'pagination' => false,
                ]),
            'options' => [
                'data-view-role' => static::VIEW_TABLE,
                'class' => 'grid-view statreport-view'
            ],
            'tableOptions' => $this->tableOptions,
        ]);
    }

    public function initSeries() {
        foreach($this->series as $i => $s) {
            $this->series[$i] = Yii::createObject(array_merge([
                'class' => Series::className(),
            ], $s));
        }
    }

    public function initColumns() {
        foreach($this->series as $s) {
            $column = [];
            $column['header'] = $s->header;
            $column['footer'] = $s->footer;
            $column['visible'] = $s->visible;
            $column['headerOptions'] = $s->headerOptions;
            $column['footerOptions'] = $s->footerOptions;

            $this->columns[] = $column;
        }
    }

    public function initButtonOptions() {
        $this->buttonGroupOptions['options']['class'] = 'statreport-switcher-buttons';
        $this->buttonGroupOptions['buttons'] = [
            ['label' => $this->switchBtnChartLabel, 'options' => ['value' => static::VIEW_CHART]],
            ['label' => $this->switchBtnTableLabel, 'options' => ['value' => static::VIEW_TABLE]],
        ];
        $this->buttonGroupOptions['encodeLabels'] = false;
    }
}