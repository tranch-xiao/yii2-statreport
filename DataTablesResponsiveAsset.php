<?php

namespace thrieu\statreport;

use yii\web\AssetBundle;

class DataTablesResponsiveAsset extends AssetBundle {

    public $sourcePath = '@vendor/bower/datatables-responsive';
    public $css = [
        'css/dataTables.responsive.css',
    ];
    public $js = [
        'js/dataTables.responsive.js',
    ];
    public $depends = ['thrieu\statreport\DataTablesAsset'];
}
