<?php

defined('YII_ENABLE_EXCEPTION_HANDLER') or define('YII_ENABLE_EXCEPTION_HANDLER', false);
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER', false);
defined('YII_DEBUG') or define('YII_DEBUG', true);

require_once __DIR__.'/../vendor/yiisoft/yii/framework/yii.php';
require_once __DIR__.'/tools/TestHelper.php';
Yii::import('system.test.CTestCase');

Yii::createWebApplication(__DIR__.'/../vendor/yiisoft/yii/demos/phonebook/protected/config/main.php');
