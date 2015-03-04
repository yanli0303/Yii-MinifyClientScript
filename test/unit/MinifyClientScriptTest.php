<?php

require_once(__DIR__ . '/../../src/MinifyClientScript.php');

/**
 * @author Li Yan <yali@microstrategy.com>
 */
class MinifyClientScriptTest extends CTestCase {
    const BASE_URL = '/MinifyTest';

    private $fileHandlesToClose = array();

    private static function resetDir($dir) {
        if (is_dir($dir)) {
            CFileHelper::removeDirectory($dir);
        }

        CFileHelper::createDirectory($dir, 0755, true);
    }

    private static function getWebRoot() {
        $webroot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MinifyClientScriptTest';
        if (!is_dir($webroot)) {
            CFileHelper::createDirectory($webroot, 0755, true);
        }

        return $webroot;
    }

    private static function getAssetsDir() {
        return self::getWebRoot() . DIRECTORY_SEPARATOR . 'assets';
    }

    private static function getRuntimeDir() {
        return self::getWebRoot() . DIRECTORY_SEPARATOR . 'runtime';
    }

    private static function getMinifyDir() {
        return self::getWebRoot() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'minify';
    }

    private static function prepareTestScripts() {
        $webroot = self::getWebRoot();

        $cssExterns = array(
            'http://www.google.com/path/to/style.css' => 'screen',
            'https://www.google.com/path/to/style.css' => 'print',
            '//www.google.com/path/to/style.css' => 'mobile'
        );

        $cssLocals = array(
            'path/to/style.css' => 'local', // one file, two urls, the file will be included in the bigMinFile twice
            self::BASE_URL . '/path/to/style.css' => 'local2', // one file, two urls
            self::BASE_URL . '/style.css' => 'local',
            self::BASE_URL . '/unit/test/../index.css' => 'local',
            self::BASE_URL . '/./test/../unit/../main/style.css' => 'local'
        );

        $cssLocalFiles = array(
            'path/to/style.css' => implode(DIRECTORY_SEPARATOR, array($webroot, 'path', 'to', 'style.css')),
            self::BASE_URL . '/path/to/style.css' => implode(DIRECTORY_SEPARATOR, array($webroot, 'path', 'to', 'style.css')),
            self::BASE_URL . '/style.css' => $webroot . DIRECTORY_SEPARATOR . 'style.css',
            self::BASE_URL . '/unit/test/../index.css' => implode(DIRECTORY_SEPARATOR, array($webroot, 'unit', 'index.css')),
            self::BASE_URL . '/./test/../unit/../main/style.css' => implode(DIRECTORY_SEPARATOR, array($webroot, 'main', 'style.css')),
        );

        $index = 0;
        foreach ($cssLocalFiles as $css) {
            $dirName = dirname($css);
            if (!is_dir($dirName)) {
                CFileHelper::createDirectory($dirName, 0755, true);
            }

            $fakepath = '';
            $j = 0;
            while ($j <= $index) {
                $fakepath .= "path{$j}/./path{$j}_not_useful/../";

                $j += 1;
            }

            file_put_contents($css, '.class1{background-image:url("../image/' . $fakepath . 'img.png")}');
            $index += 1;
        }

        $cssLocalsTrimmedBaseUrl = array(
            'path/to/style.css' => 'local2',
            'style.css' => 'local',
            'unit/test/../index.css' => 'local',
            './test/../unit/../main/style.css' => 'local'
        );

        $jsExterns = array(
            'http://www.google.com/path/to/script.js' => 'http://www.google.com/path/to/script.js',
            'https://www.google.com/path/to/script.js' => 'https://www.google.com/path/to/script.js',
            '//www.google.com/path/to/script.js' => '//www.google.com/path/to/script.js'
        );

        $jsLocals = array(
            self::BASE_URL . '/path/to/script.js' => self::BASE_URL . '/path/to/script.js', // one file, two urls, the file will be included in the bigMinFile twice.
            'path/to/script.js' => 'path/to/script.js', // one file, two urls
            self::BASE_URL . '/script.js' => self::BASE_URL . '/script.js',
            self::BASE_URL . '/unit/test/../script.js' => self::BASE_URL . '/unit/test/../script.js',
            self::BASE_URL . '/unit/./../test/../main/script.js' => self::BASE_URL . '/unit/./../test/../main/script.js'
        );

        $jsLocalsTrimmedBaseUrl = array(
            'path/to/script.js' => 'path/to/script.js',
            'script.js' => 'script.js',
            'unit/test/../script.js' => 'unit/test/../script.js',
            'unit/./../test/../main/script.js' => 'unit/./../test/../main/script.js'
        );

        $jsLocalFiles = array(
            self::BASE_URL . '/path/to/script.js' => implode(DIRECTORY_SEPARATOR, array($webroot, 'path', 'to', 'script.js')),
            'path/to/script.js' => implode(DIRECTORY_SEPARATOR, array($webroot, 'path', 'to', 'script.js')),
            self::BASE_URL . '/script.js' => $webroot . DIRECTORY_SEPARATOR . 'script.js',
            self::BASE_URL . '/unit/test/../script.js' => implode(DIRECTORY_SEPARATOR, array($webroot, 'unit', 'script.js')),
            self::BASE_URL . '/unit/./../test/../main/script.js' => implode(DIRECTORY_SEPARATOR, array($webroot, 'main', 'script.js')),
        );

        $index = 0;
        foreach ($jsLocalFiles as $js) {
            $dirName = dirname($js);
            if (!is_dir($dirName)) {
                CFileHelper::createDirectory($dirName, 0755, true);
            }

            file_put_contents($js, 'js' . $index);
            $index += 1;
        }

        return array(
            $cssExterns,
            $cssLocals,
            $cssLocalFiles,
            $cssLocalsTrimmedBaseUrl,
            $jsExterns,
            $jsLocals,
            $jsLocalFiles,
            $jsLocalsTrimmedBaseUrl
        );
    }

    private function findPublishedMinFile() {
        $assets = self::getAssetsDir();
        $list = scandir($assets, SCANDIR_SORT_DESCENDING);
        $dir = $list[0];
        if (strlen($dir) < 3) {
            $this->fail('Unable to find min file in: ' . $assets);
            return null;
        }

        $list = scandir($assets . DIRECTORY_SEPARATOR . $dir, SCANDIR_SORT_DESCENDING);
        $filename = $list[0];
        if (strlen($filename) < 3) {
            $this->fail('Unable to find min file in: ' . $assets);
            return null;
        }

        $minFile = implode(DIRECTORY_SEPARATOR, array($assets, $dir, $filename));
        $url = implode('/', array('assets', $dir, $filename));
        return array($url, file_get_contents($minFile), $minFile);
    }

    public static function setUpBeforeClass() {
        parent::setUpBeforeClass();

        self::resetDir(self::getAssetsDir());
        self::resetDir(self::getRuntimeDir());

        Yii::app()->setRuntimePath(self::getRuntimeDir());
        Yii::app()->getRequest()->setHostInfo('http://10.197.38.188:8080');
        Yii::app()->getRequest()->setBaseUrl(self::BASE_URL);
        Yii::setPathOfAlias('webroot', self::getWebRoot());
        Yii::app()->getAssetManager()->setBasePath(self::getAssetsDir());
    }

    public static function tearDownAfterClass() {
        parent::tearDownAfterClass();

        //CFileHelper::removeDirectory(self::getWebRoot());
    }

    public function tearDown() {
        parent::tearDown();

        foreach ($this->fileHandlesToClose as $handle) {
            fclose($handle);
        }
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
        CFileHelper::removeDirectory($wd);

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
        $files = array(
            __FILE__,
            __DIR__ . '/../bootstrap.php',
            __DIR__ . '/../phpunit.xml',
            __DIR__ . '/../../src/MinifyClientScript.php',
            tempnam(self::getWebRoot(), 'max')
        );

        $cs = new MinifyClientScript();
        $actual = TestHelper::invokeProtectedMethod($cs, 'maxFileModifiedTime', array($files));
        $expected = time();
        $this->assertLessThan(1, $expected - $actual);
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

    public function splitUrlDataProvider() {
        $data = array();

        $data[] = array('', array('', '', ''));

        $data[] = array('www.google.com/search?q=1234', array('', 'www.google.com/search', '?q=1234'));

        $data[] = array('http://www.google.com', array('http://www.google.com', '', ''));
        $data[] = array('http://www.google.com/', array('http://www.google.com', '/', ''));
        $data[] = array('http://www.google.com?q=1234', array('http://www.google.com', '', '?q=1234'));
        $data[] = array('http://www.google.com/?q=1234', array('http://www.google.com', '/', '?q=1234'));
        $data[] = array('http://www.google.com/search?q=1234', array('http://www.google.com', '/search', '?q=1234'));

        $data[] = array('https://www.google.com', array('https://www.google.com', '', ''));
        $data[] = array('https://www.google.com/', array('https://www.google.com', '/', ''));
        $data[] = array('https://www.google.com?q=1234', array('https://www.google.com', '', '?q=1234'));
        $data[] = array('https://www.google.com/?q=1234', array('https://www.google.com', '/', '?q=1234'));
        $data[] = array('https://www.google.com/search?q=1234', array('https://www.google.com', '/search', '?q=1234'));

        $data[] = array('//www.google.com', array('//www.google.com', '', ''));
        $data[] = array('//www.google.com/', array('//www.google.com', '/', ''));
        $data[] = array('//www.google.com?q=1234', array('//www.google.com', '', '?q=1234'));
        $data[] = array('//www.google.com/?q=1234', array('//www.google.com', '/', '?q=1234'));
        $data[] = array('//www.google.com/search?q=1234', array('//www.google.com', '/search', '?q=1234'));

        $data[] = array('/path/search?q=1234', array('', '/path/search', '?q=1234'));
        $data[] = array('path/search?q=1234', array('', 'path/search', '?q=1234'));

        return $data;
    }

    /**
     * @dataProvider splitUrlDataProvider
     */
    public function testSplitUrl($url, $expected) {
        $cs = new MinifyClientScript();

        $actual = TestHelper::invokeProtectedMethod($cs, 'splitUrl', array($url));
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

        $data[] = array(self::BASE_URL . '/../image/path0/./path0/../img.png', '/image/path0/img.png');
        $data[] = array(self::BASE_URL . '/../image/path0/./path0/../path1/./path1/../img.png', '/image/path0/path1/img.png');
        $data[] = array(self::BASE_URL . '/../image/path0/./path0/../path1/./path1/../path2/./path2/../path3/./path3/../img.png', '/image/path0/path1/path2/path3/img.png');
        $data[] = array(self::BASE_URL . '/../../image/path0/./path0/../path1/./path1/../path2/./path2/../path3/./path3/../img.png', '/../image/path0/path1/path2/path3/img.png');
        $data[] = array('///////////img.png', '///////////img.png');

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
        $baseUrl = self::BASE_URL;
        $url = $baseUrl . '///////path1/./path_not_useful/../path2/path3/path4/style.css';
        $cssContent = <<<CSS
.class1 { background-image: url(./images/img.png); }
.class2 { background-image: url('.././images/img.png'); } /* remove single quotes */
.class3 { background-image: url("./../images/img.png"); } /* remove double quotes */
.class4 { background-image: url(../../images/img.png); }
.class5 { background-image: url(../../images/../img.png); }
.class6 { background-image: url(../../../../../../images/../img.png); }
.class7 { background-image: url(http://www.google.com/images/../img.png); } /* external url */
.class8 { background-image: url(https://www.google.com/images/../img.png); } /* external url */
.class9 { background-image: url(//www.google.com/images/../img.png); } /* external url */
.class10 { background-image: url(/../images/../img.png); } /* app relative url */
CSS;

        $expected = <<<CSS
.class1 { background-image: url({$baseUrl}/path1/path2/path3/path4/images/img.png); }
.class2 { background-image: url({$baseUrl}/path1/path2/path3/images/img.png); } /* remove single quotes */
.class3 { background-image: url({$baseUrl}/path1/path2/path3/images/img.png); } /* remove double quotes */
.class4 { background-image: url({$baseUrl}/path1/path2/images/img.png); }
.class5 { background-image: url({$baseUrl}/path1/path2/img.png); }
.class6 { background-image: url(/../img.png); }
.class7 { background-image: url(http://www.google.com/images/../img.png); } /* external url */
.class8 { background-image: url(https://www.google.com/images/../img.png); } /* external url */
.class9 { background-image: url(//www.google.com/images/../img.png); } /* external url */
.class10 { background-image: url(/../images/../img.png); } /* app relative url */
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
        CFileHelper::copyDirectory(__DIR__, Yii::getPathOfAlias('webroot') . DIRECTORY_SEPARATOR . basename(__DIR__));

        $files = array(
            'unit/' . basename(__FILE__),
            '../../../../not/exist/style.css'
        );

        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'getFiles', array($files));
    }

    public function testGetFiles() {
        $currentDirName = basename(__DIR__);
        $webroot = Yii::getPathOfAlias('webroot');
        $webrootDirName = basename($webroot);

        CFileHelper::copyDirectory(__DIR__, $webroot . DIRECTORY_SEPARATOR . $currentDirName);
        file_put_contents($webroot . DIRECTORY_SEPARATOR . $currentDirName . DIRECTORY_SEPARATOR . 'style.css', 'style');

        $files = array(
            $currentDirName . '/' . basename(__FILE__),
            '../' . $webrootDirName . '/' . $currentDirName . '/style.css'
        );

        $expected = array(
            $currentDirName . '/' . basename(__FILE__) => $webroot . DIRECTORY_SEPARATOR . $currentDirName . DIRECTORY_SEPARATOR . basename(__FILE__),
            '../' . $webrootDirName . '/' . $currentDirName . '/style.css' => $webroot . DIRECTORY_SEPARATOR . $currentDirName . DIRECTORY_SEPARATOR . 'style.css'
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
        $saveAs = tempnam(Yii::getPathOfAlias('webroot'), 'cat');
        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'concat', array($files, $saveAs));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Failed to append the contents of
     */
    public function testConcatOutputFileLocked() {
        $files = array('current' => __FILE__);

        $saveAs = tempnam(Yii::getPathOfAlias('webroot'), 'loc');
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

        $saveAs = tempnam(Yii::getPathOfAlias('webroot'), 'cat');
        $cs = new MinifyClientScript();
        TestHelper::invokeProtectedMethod($cs, 'concat', array($files, $saveAs, function($content, $key) {
        return $key;
    }));

        $actual = file_get_contents($saveAs);
        $expected = 'current' . PHP_EOL . 'bootstrap' . PHP_EOL;
        $this->assertEquals($expected, $actual);
    }

    public function processScriptGroupDataProvider() {
        $data = array();

        list($cssExterns, $cssLocals, $cssLocalFiles, $cssLocalsTrimmedBaseUrl, $jsExterns, $jsLocals, $jsLocalFiles, $jsLocalsTrimmedBaseUrl) = $this->prepareTestScripts();

        $cssItems = array_merge($cssLocals, $cssExterns);
        $jsItems = array_merge($jsLocals, $jsExterns);

        // not minify, not trimBaseUrl
        $data[] = array(false, false, false, $cssItems, true, $cssItems);
        $data[] = array(false, false, false, $jsItems, false, $jsItems);

        // not minify, trimBaseUrl
        $data[] = array(false, true, false, $cssItems, true, array_merge($cssExterns, $cssLocalsTrimmedBaseUrl));
        $data[] = array(false, true, false, $jsItems, false, array_merge($jsExterns, $jsLocalsTrimmedBaseUrl));

        // no locals
        $data[] = array(true, true, true, $cssExterns, true, $cssExterns);
        $data[] = array(true, true, true, $jsExterns, false, $jsExterns);

        $cssBigMinFileNoRewriteContent = implode(PHP_EOL, array(
                    '.class1{background-image:url("../image/path0/./path0_not_useful/../path1/./path1_not_useful/../img.png")}',
                    '.class1{background-image:url("../image/path0/./path0_not_useful/../path1/./path1_not_useful/../img.png")}',
                    '.class1{background-image:url("../image/path0/./path0_not_useful/../path1/./path1_not_useful/../path2/./path2_not_useful/../img.png")}',
                    '.class1{background-image:url("../image/path0/./path0_not_useful/../path1/./path1_not_useful/../path2/./path2_not_useful/../path3/./path3_not_useful/../img.png")}',
                    '.class1{background-image:url("../image/path0/./path0_not_useful/../path1/./path1_not_useful/../path2/./path2_not_useful/../path3/./path3_not_useful/../path4/./path4_not_useful/../img.png")}',
                )) . PHP_EOL;
        $cssBigMinFileRewriteContent = implode(PHP_EOL, array(
                    '.class1{background-image:url(' . self::BASE_URL . '/path/image/path0/path1/img.png)}',
                    '.class1{background-image:url(' . self::BASE_URL . '/path/image/path0/path1/img.png)}',
                    '.class1{background-image:url(/image/path0/path1/path2/img.png)}',
                    '.class1{background-image:url(' . self::BASE_URL . '/image/path0/path1/path2/path3/img.png)}',
                    '.class1{background-image:url(' . self::BASE_URL . '/image/path0/path1/path2/path3/path4/img.png)}',
                )) . PHP_EOL;

        $jsBigMinFileContent = implode(PHP_EOL, array('js1', 'js1', 'js2', 'js3', 'js4')) . PHP_EOL;

        // not processed previously, not rewriteCssUrl
        $data[] = array(true, true, false, $cssItems, true, $cssExterns, $cssBigMinFileNoRewriteContent);
        $data[] = array(true, true, false, $jsItems, false, $jsExterns, $jsBigMinFileContent);

        // not processed previously, rewriteCssUrl
        $data[] = array(true, true, true, $cssItems, true, $cssExterns, $cssBigMinFileRewriteContent);
        //$data[] = array(true, true, true, $jsItems, false, array_merge($jsExterns, array($jsBigMinFile => $jsBigMinFile)), array($jsBigMinFile => $jsBigMinFileContent));
        // processed previously, rewriteCssUrl
        //$data[] = array(true, true, true, $cssItems, true, array_merge($cssExterns, array($cssBigMinFile => '')), array($cssBigMinFile => $cssBigMinFileRewriteContent));
        //$data[] = array(true, true, true, $jsItems, false, array_merge($jsExterns, array($jsBigMinFile => $jsBigMinFile)), array($jsBigMinFile => $jsBigMinFileContent));

        return $data;
    }

    /**
     * @dataProvider processScriptGroupDataProvider
     */
    public function testProcessScriptGroup($minify, $trimBaseUrl, $rewriteCssUrl, $groupItems, $isCss, $expected, $expectedFileContent = null, $doItAgain = false) {
        self::resetDir(self::getAssetsDir());
        self::resetDir(self::getMinifyDir());
        TestHelper::setProtectedProperty(Yii::app()->getAssetManager(), '_published', array());

        $cs = new MinifyClientScript();
        $cs->minify = $minify;
        $cs->trimBaseUrl = $trimBaseUrl;
        $cs->rewriteCssUrl = $rewriteCssUrl;

        $actual = TestHelper::invokeProtectedMethod($cs, 'processScriptGroup', array($groupItems, $isCss));
        if ($doItAgain) {
            $actual = TestHelper::invokeProtectedMethod($cs, 'processScriptGroup', array($groupItems, $isCss));
        }

        if (empty($expectedFileContent)) {
            $this->assertEquals($expected, $actual);
        } else {
            list($fileurl, $actualFileContent, $filename) = $this->findPublishedMinFile();
            $this->assertEquals($expectedFileContent, $actualFileContent);

            $expected[$fileurl] = $isCss ? '' : $fileurl;
            $this->assertEquals($expected, $actual);
        }
    }
}
