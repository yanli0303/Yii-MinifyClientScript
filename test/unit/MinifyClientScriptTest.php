<?php

require_once(__DIR__ . '/../../src/MinifyClientScript.php');

/**
 * @author Li Yan <yali@microstrategy.com>
 */
class MinifyClientScriptTest extends CTestCase {
    const BASE_URL = '/MinifyTest';

    private $filesToDelete = array();
    private $fileHandlesToClose = array();

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();

        $runtimePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MinifyClientScriptTest';
        if (!is_dir($runtimePath)) {
            mkdir($runtimePath, 0755);
        }

        Yii::app()->setRuntimePath($runtimePath);
        Yii::app()->getRequest()->setHostInfo('http://10.197.38.188:8080');
        Yii::app()->getRequest()->setBaseUrl(self::BASE_URL);
        Yii::setPathOfAlias('webroot', dirname(__DIR__));
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

    public function tearDown() {
        parent::tearDown();

        foreach ($this->fileHandlesToClose as $handle) {
            fclose($handle);
        }

        $this->removeFiles($this->filesToDelete);
    }

    private function createFiles($files) {
        if (!empty($files)) {
            foreach ($files as $file) {
                if (!empty($file)) {
                    file_put_contents($file, 'MinifyClientScriptTest');
                }
            }
        }
    }

    private function removeFiles($files) {
        if (!empty($files)) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testPropertyDefaults() {
        $cs = new MinifyClientScript();

        $this->assertTrue($cs->minify);
        $this->assertTrue($cs->trimBaseUrl);
        $this->assertTrue($cs->rewriteCssUrl);
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

    public function findMinifiedFileDataProvider() {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $expected = $tmp . 'yanli.min.js';
        $this->removeFiles(array($expected));

        $data = array();

        $data[] = array($tmp . 'yanli.js', NULL, NULL);
        $data[] = array($tmp . 'yanli.js', $expected, $expected);
        $data[] = array($expected, $expected, $expected);

        return $data;
    }

    /**
     * @dataProvider findMinifiedFileDataProvider
     */
    public function testFindMinifiedFile($filename, $expected, $createFile) {
        $this->createFiles(array($createFile));

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'findMinifiedFile', array($filename));
        $this->assertEquals($expected, $actual);

        $this->removeFiles(array($createFile));
    }

    public function findMinifiedFilesDataProvider() {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $file = $tmp . 'yanli.js';
        $minFile = $tmp . 'yanli.min.js';
        $this->removeFiles(array($minFile));

        $data = array();

        $data[] = array(array('file1' => $file, $minFile), false, array('file1' => $file, $minFile), NULL);
        $data[] = array(array($file, 'minFile' => $minFile), true, new Exception(), null);
        $data[] = array(array($file, 'minFile' => $minFile), true, array($minFile, 'minFile' => $minFile), array($minFile));

        return $data;
    }

    /**
     * @dataProvider findMinifiedFilesDataProvider
     */
    public function testFindMinifiedFiles($files, $failOnError, $expected, $createFiles) {
        $this->createFiles($createFiles);

        $cs = new MinifyClientScript();
        $cs->failOnError = $failOnError;

        if ($expected instanceof Exception) {
            try {
                TestHelper::invokeProtectedMethod($cs, 'findMinifiedFiles', array($files));
                $this->fail('The expected exception was not thrown.');
            } catch (Exception $ex) {
                $this->assertStringStartsWith('The minified version was not found for: ', $ex->getMessage());
            }
        } else {
            $actual = TestHelper::invokeProtectedMethod($cs, 'findMinifiedFiles', array($files));
            $this->assertEquals($expected, $actual);
        }

        $this->removeFiles($createFiles);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unable to get last modification time of file: z:/not_exist.file
     */
    public function testMaxFileModifiedTimeFileNotExistsExpectsException() {
        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'maxFileModifiedTime', array(array('z:/not_exist.file')));
    }

    public function testMaxFileModifiedTime() {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $createFiles = array(
            $tmp . 'yanli.js',
            $tmp . 'yanli.min.js'
        );

        $this->createFiles($createFiles);

        $files = array_merge(array(
            __FILE__,
            __DIR__ . '/../bootstrap.php',
            __DIR__ . '/../phpunit.xml',
            __DIR__ . '/../../src/MinifyClientScript.php'
                ), $createFiles);

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'maxFileModifiedTime', array($files));
        $expected = time();
        $this->assertLessThan(1, $expected - $actual);

        $this->removeFiles($createFiles);
    }

    public function testHashFileNames() {
        $files = array(
            __FILE__,
            __DIR__ . '/../bootstrap.php',
            __DIR__ . '/../phpunit.xml',
            __DIR__ . '/../../src/MinifyClientScript.php'
        );

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'hashFileNames', array($files));
        $this->assertNotEmpty($actual);
        $this->assertEquals(32, strlen($actual));

        $files2 = array_merge(array('1'), $files);
        $actual2 = TestHelper::invokeProtectedMethod($cs, 'hashFileNames', array($files2));
        $this->assertNotEmpty($actual2);
        $this->assertEquals(32, strlen($actual2));

        $this->assertNotEquals($actual2, $actual);
    }

    public function canonicalizeUrlDataProvider() {
        $data = array();
        $url = '/path/to/file.php';

        $data[] = array($url, '', 'path/to/file.php');
        $data[] = array($url, '/pathNotFound', 'path/to/file.php');
        $data[] = array($url, '/pathNotFound/', 'path/to/file.php');
        $data[] = array($url, '/pathNotFound/notFound', 'path/to/file.php');
        $data[] = array($url, '/pathNotFound/notFound/', 'path/to/file.php');
        $data[] = array($url, '/path', 'to/file.php');
        $data[] = array($url, '/PaTh', 'to/file.php');
        $data[] = array($url, '/path/', 'to/file.php');
        $data[] = array($url, '/path/TO/', 'file.php');

        return $data;
    }

    /**
     * @dataProvider canonicalizeUrlDataProvider
     */
    public function testCanonicalizeUrl($url, $prefix, $expected) {
        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'canonicalizeUrl', array($url, $prefix));
        $this->assertEquals($expected, $actual);
    }

    public function removeUrlDomainDataProvider() {
        $data = array();

        $data[] = array('', '');

        $data[] = array('www.google.com/search?q=1234', 'www.google.com/search?q=1234');

        $data[] = array('http://www.google.com', '');
        $data[] = array('http://www.google.com?q=1234', '?q=1234');
        $data[] = array('http://www.google.com/?q=1234', '/?q=1234');
        $data[] = array('http://www.google.com/search?q=1234', '/search?q=1234');

        $data[] = array('https://www.google.com', '');
        $data[] = array('https://www.google.com?q=1234', '?q=1234');
        $data[] = array('https://www.google.com/?q=1234', '/?q=1234');
        $data[] = array('https://www.google.com/search?q=1234', '/search?q=1234');

        $data[] = array('//www.google.com', '');
        $data[] = array('//www.google.com?q=1234', '?q=1234');
        $data[] = array('//www.google.com/?q=1234', '/?q=1234');
        $data[] = array('//www.google.com/search?q=1234', '/search?q=1234');

        $data[] = array('/path/search?q=1234', '/path/search?q=1234');
        $data[] = array('path/search?q=1234', 'path/search?q=1234');

        return $data;
    }

    /**
     * @dataProvider removeUrlDomainDataProvider
     */
    public function testRemoveUrlDomain($url, $expected) {
        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'removeUrlDomain', array($url));
        $this->assertEquals($expected, $actual);
    }

    public function isExternalUrlDataProvider() {
        $data = array();

        $data[] = array('http://www.google.com/search?q=1234', true);
        $data[] = array('https://www.google.com/search?q=1234', true);
        $data[] = array('//www.google.com/search?q=1234', true);
        $data[] = array('www.google.com/search?q=1234', false);
        $data[] = array('', false);

        return $data;
    }

    /**
     * @dataProvider isExternalUrlDataProvider
     */
    public function testIsExternalUrl($url, $expected) {
        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'isExternalUrl', array($url));
        $this->assertEquals($expected, $actual);
    }

    public function realurlDataProvider() {
        $data = array();

        $data[] = array('', '');

        $data[] = array('www.google.com/search?q=1234', true);

        $data[] = array('http://www.google.com', true);
        $data[] = array('http://www.google.com?q=1234', true);
        $data[] = array('http://www.google.com/?q=1234', true);
        $data[] = array('http://www.google.com/search?q=1234', true);

        $data[] = array('https://www.google.com', true);
        $data[] = array('https://www.google.com?q=1234', true);
        $data[] = array('https://www.google.com/?q=1234', true);
        $data[] = array('https://www.google.com/search?q=1234', true);

        $data[] = array('//www.google.com', true);
        $data[] = array('//www.google.com?q=1234', true);
        $data[] = array('//www.google.com/?q=1234', true);
        $data[] = array('//www.google.com/search?q=1234', true);

        $data[] = array('/path/search?q=1234', true);
        $data[] = array('path/search?q=1234', true);

        $data[] = array('http://www.google.com/path1/path2/path3/path4/index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');
        $data[] = array('http:\\\\www.google.com\path1\path2\path3\path4\index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');

        $data[] = array('http://www.google.com/./path1/path2/path3/path4/index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/./path2/path3/path4/index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/path2/./path3/path4/index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/path2/path3/./path4/index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/path2/path3/path4/./index.html', 'http://www.google.com/path1/path2/path3/path4/index.html');

        $data[] = array('http://www.google.com/./../path1/path2/path3/path4/index.html', 'http://www.google.com/../path1/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/.././path2/path3/path4/index.html', 'http://www.google.com/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/../path2/./path3/path4/index.html', 'http://www.google.com/path2/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/path2/../../path3/./path4/index.html', 'http://www.google.com/path3/path4/index.html');
        $data[] = array('http://www.google.com/path1/path2/../../../path3/path4/./index.html', 'http://www.google.com/../path3/path4/index.html');

        return $data;
    }

    /**
     * @dataProvider realurlDataProvider
     */
    public function testRealurl($url, $expected) {
        if (true === $expected) {
            $expected = $url;
        }

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'realurl', array($url));
        $this->assertEquals($expected, $actual);
    }

    public function testCssUrlAbsolute() {
        $url = 'http://www.google.com/path1/path2/path3/path4/style.css';
        $cssContent = <<<CSS
.class1 { background-image: url(./images/img.png); }
.class2 { background-image: url('.././images/img.png'); } /* remove single quotes */
.class3 { background-image: url("./../images/img.png"); } /* remove double quotes */
.class4 { background-image: url(../../images/img.png); }
.class5 { background-image: url(../../images/../img.png); }
.class6 { background-image: url(../../../../../images/../img.png); }
CSS;

        $expected = <<<CSS
.class1 { background-image: url(http://www.google.com/path1/path2/path3/path4/images/img.png); }
.class2 { background-image: url(http://www.google.com/path1/path2/path3/images/img.png); } /* remove single quotes */
.class3 { background-image: url(http://www.google.com/path1/path2/path3/images/img.png); } /* remove double quotes */
.class4 { background-image: url(http://www.google.com/path1/path2/images/img.png); }
.class5 { background-image: url(http://www.google.com/path1/path2/img.png); }
.class6 { background-image: url(http://www.google.com/../img.png); }
CSS;

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'cssUrlAbsolute', array($cssContent, $url));
        $this->assertEquals($expected, $actual);
    }

    public function testFilterLocals() {
        $items = array(
            'http://www.google.com/path/to/style.css' => 'screen',
            'https://www.google.com/path/to/style.css' => 'print',
            '//www.google.com/path/to/style.css' => 'mobile',
            '/path/to/style.css' => 'local',
            'path/to/style.css' => 'local',
            'style.css' => 'local',
            '../style.css' => 'local',
            '../../style.css' => 'local',
            'http://www.google.com/path/to/script.js' => 'http://www.google.com/path/to/script.js',
            'https://www.google.com/path/to/script.js' => 'https://www.google.com/path/to/script.js',
            '//www.google.com/path/to/script.js' => '//www.google.com/path/to/script.js',
            '/path/to/script.js' => '/path/to/script.js',
            'path/to/script.js' => 'path/to/script.js',
            'script.js' => 'script.js',
            '../script.js' => '../script.js',
            '../../script.js' => '../../script.js',
        );

        $expectedLocals = array(
            '/path/to/style.css',
            'path/to/style.css',
            'style.css',
            '../style.css',
            '../../style.css',
            '/path/to/script.js',
            'path/to/script.js',
            'script.js',
            '../script.js',
            '../../script.js'
        );
        $expectedExterns = array(
            'http://www.google.com/path/to/style.css' => 'screen',
            'https://www.google.com/path/to/style.css' => 'print',
            '//www.google.com/path/to/style.css' => 'mobile',
            'http://www.google.com/path/to/script.js' => 'http://www.google.com/path/to/script.js',
            'https://www.google.com/path/to/script.js' => 'https://www.google.com/path/to/script.js',
            '//www.google.com/path/to/script.js' => '//www.google.com/path/to/script.js',
        );

        $cs = new MinifyClientScript();
        list($actualExterns, $actualLocals) = TestHelper::invokeProtectedMethod($cs, 'filterLocals', array($items));
        $this->assertEquals($expectedLocals, $actualLocals);
        $this->assertEquals($expectedExterns, $actualExterns);
    }

    public function trimBaseUrlDataProvider() {
        $cssItems = array(
            'http://www.google.com/path/to/style.css' => 'screen',
            'https://www.google.com/path/to/style.css' => 'print',
            '//www.google.com/path/to/style.css' => 'mobile',
            'path/to/style.css' => 'local', // this will be overwritten by following one
            self::BASE_URL . '/path/to/style.css' => 'local2',
            self::BASE_URL . '/style.css' => 'local',
            self::BASE_URL . '/../style.css' => 'local',
            self::BASE_URL . '/../../style.css' => 'local'
        );

        $jsItems = array(
            'http://www.google.com/path/to/script.js' => 'http://www.google.com/path/to/script.js',
            'https://www.google.com/path/to/script.js' => 'https://www.google.com/path/to/script.js',
            '//www.google.com/path/to/script.js' => '//www.google.com/path/to/script.js',
            self::BASE_URL . '/path/to/script.js' => self::BASE_URL . '/path/to/script.js', // this will be overwritten by following one
            'path/to/script.js' => 'path/to/script.js',
            self::BASE_URL . '/script.js' => self::BASE_URL . '/script.js',
            self::BASE_URL . '/../script.js' => self::BASE_URL . '../script.js',
            self::BASE_URL . '/../../script.js' => self::BASE_URL . '../../script.js',
        );

        $expectedCssItems = array(
            'http://www.google.com/path/to/style.css' => 'screen',
            'https://www.google.com/path/to/style.css' => 'print',
            '//www.google.com/path/to/style.css' => 'mobile',
            'path/to/style.css' => 'local2',
            'style.css' => 'local',
            '../style.css' => 'local',
            '../../style.css' => 'local'
        );

        $expectedJsItems = array(
            'http://www.google.com/path/to/script.js' => 'http://www.google.com/path/to/script.js',
            'https://www.google.com/path/to/script.js' => 'https://www.google.com/path/to/script.js',
            '//www.google.com/path/to/script.js' => '//www.google.com/path/to/script.js',
            'path/to/script.js' => 'path/to/script.js',
            'script.js' => 'script.js',
            '../script.js' => '../script.js',
            '../../script.js' => '../../script.js',
        );

        $data = array();

        $data[] = array($cssItems, true, $expectedCssItems);
        $data[] = array($jsItems, false, $expectedJsItems);

        return $data;
    }

    /**
     * @dataProvider trimBaseUrlDataProvider
     */
    public function testTrimBaseUrl($items, $isCss, $expected) {
        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'trimBaseUrl', array($items, $isCss));
        $this->assertEquals($expected, $actual);
    }

    public function testGenerateBigMinFilePath() {
        $wd = Yii::app()->getRuntimePath() . DIRECTORY_SEPARATOR . 'minify' . DIRECTORY_SEPARATOR;
        $extension = '.txt';

        $files = array(
            __FILE__,
            __DIR__ . '/../bootstrap.php',
            __DIR__ . '/../phpunit.xml',
            __DIR__ . '/../../src/MinifyClientScript.php'
        );

        $unixTime = '_' . time();
        $cs = new MinifyClientScript();
        $cs->minFileSuffix = '.mininfied';

        $actual = TestHelper::invokeProtectedMethod($cs, 'generateBigMinFilePath', array($files, $extension));
        $this->assertEquals(strlen($wd) + 32 + strlen($unixTime) + strlen($cs->minFileSuffix) + strlen($extension), strlen($actual));
        $this->assertStringStartsWith($wd, $actual);
        $this->assertStringEndsWith($cs->minFileSuffix . $extension, $actual);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage File not found: ../../../../not/exist/style.css
     */
    public function testGetFilesFileNotExist() {
        $files = array(
            'unit/' . basename(__FILE__),
            '../../../../not/exist/style.css'
        );

        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'getFiles', array($files));
    }

    public function testGetFiles() {
        $files = array(
            'unit/' . basename(__FILE__),
            '../test/bootstrap.php'
        );

        $webroot = dirname(__DIR__);
        $expected = array(
            'unit/' . basename(__FILE__) => $webroot . DIRECTORY_SEPARATOR . 'unit' . DIRECTORY_SEPARATOR . basename(__FILE__),
            '../test/bootstrap.php' => $webroot . DIRECTORY_SEPARATOR . 'bootstrap.php',
        );

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'getFiles', array($files));
        $this->assertEquals($expected, $actual);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to get contents of 'z:/not_exist.file'.
     */
    public function testConcatInputFileNotExist() {
        $files = array('z:/not_exist.file');

        $saveAs = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid() . 'tmp';
        $this->filesToDelete[] = $saveAs;

        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'concat', array($files, $saveAs));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to append the contents of
     */
    public function testConcatOutputFileLocked() {
        $files = array('current' => __FILE__);

        $saveAs = tempnam(sys_get_temp_dir(), 'loc');
        $this->filesToDelete[] = $saveAs;

        $writer = fopen($saveAs, 'w+');
        $this->fileHandlesToClose[] = $writer;

        if (!flock($writer, LOCK_EX | LOCK_NB)) {
            $this->fail('Unable to gain exclusively lock on temporary file: ' . $saveAs);
            return;
        }

        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'concat', array($files, $saveAs));
    }

    public function testConcat() {
        $files = array(
            'current' => __FILE__,
            'bootstrap' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bootstrap.php'
        );

        $saveAs = tempnam(sys_get_temp_dir(), 'loc');
        $this->filesToDelete[] = $saveAs;

        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'concat', array($files, $saveAs, function($content, $key) {
        return $key;
    }));

        $actual = file_get_contents($saveAs);
        $expected = 'current' . PHP_EOL . 'bootstrap' . PHP_EOL;
        $this->assertEquals($expected, $actual);
    }
}
