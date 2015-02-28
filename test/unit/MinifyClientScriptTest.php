<?php

require_once(__DIR__ . '/../../src/MinifyClientScript.php');

/**
 * @author Li Yan <yali@microstrategy.com>
 */
class MinifyClientScriptTest extends CTestCase {

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();

        $runtimePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MinifyClientScriptTest';
        if (!is_dir($runtimePath)) {
            mkdir($runtimePath, 0755);
        }

        Yii::app()->setRuntimePath($runtimePath);
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();

        $runtimePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MinifyClientScriptTest';
        $wd = $runtimePath . DIRECTORY_SEPARATOR . 'minify';
        if (is_dir($wd)) {
            rmdir($wd);
        }

        if (is_dir($runtimePath)) {
            rmdir($runtimePath);
        }
    }

    public function testPropertyDefaults() {
        $cs = new MinifyClientScript();

        $this->assertTrue($cs->trimBaseUrl);
        $this->assertTrue($cs->rewriteCssUrl);
        $this->assertTrue($cs->minify);
        $this->assertFalse($cs->failOnError);
        $this->assertEquals('.min', $cs->minFileSuffix);
    }

    public function testGetWorkingDir() {
        $wd = Yii::app()->getRuntimePath() . DIRECTORY_SEPARATOR . 'minify';
        if (is_dir($wd)) {
            rmdir($wd);
        }

        if (is_dir($wd)) {
            $this->fail('Unable to remove minify working directory before test.');
        }

        $cs = new MinifyClientScript();
        $expected = $wd;
        $actual = TestHelper::invokeProtectedMethod($cs, 'getWorkingDir');
        $this->assertEquals($expected, $actual);
        $this->assertTrue(is_dir($wd));
    }

    public function isMinifiedDataProvider() {
        $data = [];

        $data[] = ['.min', '/MSTR/UsherNetwork/js/yanli.min.js', true];
        $data[] = ['.min', '/MSTR/UsherNetwork/js/yanli.js', false];

        $data[] = ['-min', '/MSTR/UsherNetwork/js/yanli-min.js', true];
        $data[] = ['-min', '/MSTR/UsherNetwork/js/yanli.js', false];

        return $data;
    }

    /**
     * @dataProvider isMinifiedDataProvider
     */
    public function testIsMinified($minFileSuffix, $filename, $expected) {
        $cs = new MinifyClientScript();
        $cs->minFileSuffix = $minFileSuffix;
        $actual = TestHelper::invokeProtectedMethod($cs, 'isMinified', [$filename]);
        $this->assertEquals($expected, $actual);
    }

    public function findMinifiedFileFileDataProvider() {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $expected = $tmp . 'yanli.min.js';
        if (file_exists($expected)) {
            unlink($expected);
        }

        $data = [];

        $data[] = [$tmp . 'yanli.js', NULL, NULL];
        $data[] = [$tmp . 'yanli.min.js', NULL, NULL];
        $data[] = [$tmp . 'yanli.js', $expected, $expected];

        return $data;
    }

    /**
     * @dataProvider findMinifiedFileFileDataProvider
     */
    public function testFindMinifiedFile($filename, $expected, $createFile) {
        if ($createFile) {
            file_put_contents($createFile, self::class);
        }

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'findMinifiedFile', [$filename]);
        $this->assertEquals($expected, $actual);

        if ($createFile && file_exists($createFile)) {
            unlink($createFile);
        }
    }
}
