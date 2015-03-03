<?php

/**
 * Extends CClientScript and concatenate JavaScript and CSS files on current page.
 * @author Yan Li <peterleepersonal@gmail.com>
 */
class MinifyClientScript extends CClientScript {
    /**
     * @var bool Whether to enable minifying.
     * Defaults to true.
     */
    public $minify = true;

    /**
     * @var bool Whether remove the Yii::app()->baseUrl from the beginning of resource urls.
     * Defaults to true.
     */
    public $trimBaseUrl = true;

    /**
     * @var bool Whether to rewrite "url()" rules of CSS after relocating CSS files.
     * Defaults to true.
     */
    public $rewriteCssUrl = true;

    /**
     * @var bool If false, fallback to unminified version whenever any error occurred.
     */
    public $failOnError = false;

    /**
     * @var string The filename(without extension) suffix of minified files.
     * Defaults to '.min' which means a minified file is named as in "jquery.min.js".
     */
    public $minFileSuffix = '.min';

    /**
     * The working directory for minifying.
     * @var string Path to the working directory, which is "~/runtime/minify"
     */
    private $_wd = null;

    protected function getWorkingDir() {
        if (!$this->_wd) {
            $wd = Yii::app()->getRuntimePath() . DIRECTORY_SEPARATOR . 'minify';

            // create the directory if not existed
            if (is_dir($wd) || mkdir($wd, 0755, true)) {
                $this->_wd = $wd;
            } else {
                throw new Exception('Unable to create directory: ' . $wd);
            }
        }

        return $this->_wd;
    }

    protected function findMinifiedFile($fileName) {
        $lastDotPosition = strrpos($fileName, '.');
        $extension = false === $lastDotPosition ? '' : substr($fileName, $lastDotPosition);
        $fileNameWithoutExtension = false === $lastDotPosition ? $fileName : substr($fileName, 0, $lastDotPosition);
        $fileNameSuffix = substr($fileNameWithoutExtension, -strlen($this->minFileSuffix));

        if ($this->minFileSuffix === $fileNameSuffix) {
            // already minified
            return $fileName;
        }

        $minFile = $fileNameWithoutExtension . $this->minFileSuffix . $extension;
        return file_exists($minFile) ? $minFile : null;
    }

    protected function findMinifiedFiles($files) {
        $minifiedFiles = array();
        foreach ($files as $key => $file) {
            $minFile = $this->findMinifiedFile($file);
            if (!$minFile) {
                if ($this->failOnError) {
                    throw new Exception('The minified version was not found for: ' . $file);
                }

                $msg = 'The minified version was not found for: ' . $file . ', use itself instead.';
                Yii::log($msg, CLogger::LEVEL_INFO, 'ext.minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);

                // fallback to unminified version
                $minFile = $file;
            }

            $minifiedFiles[$key] = $minFile;
        }

        return $minifiedFiles;
    }

    /**
     * Get the max modified time among the given files.
     * Note: it doesn't support microseconds.
     * @param array $files The file paths.
     * @return int returns time as a Unix timestamp.
     * @throws Exception When a file was not found.
     */
    protected function maxFileModifiedTime($files) {
        $max = 0;
        foreach ($files as $file) {
            $lastModifiedTime = @filemtime($file);
            if (false === $lastModifiedTime) {
                throw new Exception('Unable to get last modification time of file: ' . $file);
            }

            if ($lastModifiedTime > $max) {
                $max = $lastModifiedTime;
            }
        }

        return $max;
    }

    protected function hashFileNames($files) {
        $oneline = implode(PATH_SEPARATOR, $files);
        return md5($oneline);
    }

    /**
     * Removes the leading / and specified prefix from the url.
     * @param string $url the url to canonicalize.
     * @param string $prefix url prefix.
     * @return string
     */
    protected function canonicalizeUrl($url, $prefix) {
        $prefixLength = strlen($prefix);
        if ($prefixLength > 0 && 0 === strncasecmp($prefix, $url, $prefixLength)) {
            $url = substr($url, $prefixLength);
        }

        return ltrim($url, '/');
    }

    protected function splitUrl($url) {
        $domain = '';
        $query = '';

        $questionIndex = strpos($url, '?');
        if (false !== $questionIndex) {
            $query = substr($url, $questionIndex);
            $url = substr($url, 0, $questionIndex);
        }

        $protocolIndex = strpos($url, '//');
        // unable to determine the domain without protocol, e.g. www.google.com/search?q=1234
        if (false !== $protocolIndex) {
            $pathIndex = strpos($url, '/', $protocolIndex + 2);
            if (false !== $pathIndex) {
                $domain = substr($url, 0, $pathIndex);
                $url = substr($url, $pathIndex);
            } else {
                $domain = $url;
                $url = '';
            }
        }

        return array($domain, $url, $query);
    }

    /**
     * Check whether the given URL is relative or not.
     * Any URL that starts with either of ['http:', 'https:', '//'] is considered as external.
     * @return boolean If the given URL is relative, returns FALSE; otherwise returns TRUE;
     */
    protected function isExternalUrl($url) {
        return 0 === strncasecmp($url, 'https:', 6) || 0 === strncasecmp($url, 'http:', 5) || 0 === strncasecmp($url, '//', 2);
    }

    /**
     * Resolves the '..' and '.' in the URL.
     * @param string $url The url to process.
     * @return string
     */
    protected function realurl($url) {
        if (empty($url)) {
            return $url;
        }

        $url = strtr($url, '\\', '/');
        list($domain, $normalizedUrl, $query) = $this->splitUrl($url);
        if (strlen($normalizedUrl) < 2) {
            return $url;
        }

        $urlParts = explode('/', $normalizedUrl);
        $index = 0;
        while ($index < count($urlParts)) {
            $part = $urlParts[$index];
            if ($part === '.') {
                array_splice($urlParts, $index, 1);
            } else if ($part === '..' && $index > 0 && $urlParts[$index - 1] !== '..' && $urlParts[$index - 1] !== '') {
                array_splice($urlParts, $index - 1, 2);
                $index -= 1;
            } else {
                $index += 1;
            }
        }

        return $domain . implode('/', $urlParts) . $query;
    }

    /**
     * Make url() rules of CSS absolute.
     * @param string $cssFileContent The content of the CSS file.
     * @param string $cssFileUrl The full URL to the CSS file.
     * @return string
     */
    protected function cssUrlAbsolute($cssFileContent, $cssFileUrl) {
        $me = $this;
        $baseUrl = Yii::app()->getBaseUrl();
        $newUrlPrefix = $this->canonicalizeUrl(dirname($cssFileUrl), $baseUrl);
        $newUrlPrefix = $baseUrl . '/' . (empty($newUrlPrefix) ? '' : $newUrlPrefix . '/');

        // see http://www.w3.org/TR/CSS2/syndata.html#uri
        return preg_replace_callback('#\burl\(([^)]+)\)#i', function($matches) use (&$newUrlPrefix, &$me) {
            $url = trim($matches[1], ' \'"');
            $isAbsUrl = substr($url, 0, 1) === '/' || $me->isExternalUrl($url);
            if (!$isAbsUrl) {
                $url = $me->realurl($newUrlPrefix . $url);
            }

            return "url({$url})";
        }, $cssFileContent);
    }

    protected function filterLocals($items) {
        $externs = array();
        $locals = array();
        foreach ($items as $url => $media) {
            if ($this->isExternalUrl($url)) {
                $externs[$url] = $media;
            } else {
                $locals[] = $url;
            }
        }

        return array($externs, $locals);
    }

    protected function trimBaseUrl($items, $isCss) {
        $baseUrl = Yii::app()->getBaseUrl();
        $trimmedUrls = array();
        foreach ($items as $url => $media) {
            if ($this->isExternalUrl($url)) {
                $trimmedUrls[$url] = $media;
            } else {
                $url = $this->canonicalizeUrl($url, $baseUrl);
                $trimmedUrls[$url] = $isCss ? $media : $url;
            }
        }

        return $trimmedUrls;
    }

    protected function generateBigMinFilePath($files, $extension) {
        $maxFileModifiedTime = $this->maxFileModifiedTime($files);
        $id = $this->hashFileNames($files) . '_' . $maxFileModifiedTime;
        return $this->getWorkingDir() . DIRECTORY_SEPARATOR . $id . $this->minFileSuffix . $extension;
    }

    protected function getFiles($items) {
        $baseUrl = Yii::app()->getBaseUrl();
        $basePath = Yii::getPathOfAlias('webroot');

        $files = array();
        foreach ($items as $url) {
            $relativePath = $this->canonicalizeUrl($url, $baseUrl);
            $realpath = realpath($basePath . DIRECTORY_SEPARATOR . $relativePath);
            if (false === $realpath) {
                throw new Exception('File not found: ' . $url);
            }

            $files[$url] = $realpath;
        }

        return $files;
    }

    /**
     * Concatenate specified text files into one.
     * @param array $files List of files to concatenate (full path).
     * @param string $saveAs The file name(full path) of concatenated file.
     * @param callable $fnProcessFileContent Callback function which accepts an argument of $fileContent,
     * And returns the modified $fileContent.
     * @return bool Returns TRUE on success, otherwise returns FALSE.
     */
    protected function concat($files, $saveAs, $fnProcessFileContent = null) {
        $isFirst = true;
        foreach ($files as $key => $fileName) {
            $fileContent = @file_get_contents($fileName);
            if (false === $fileContent) {
                throw new Exception("Failed to get contents of '{$fileName}'.");
            }

            if (is_callable($fnProcessFileContent)) {
                $fileContent = call_user_func($fnProcessFileContent, $fileContent, $key);
            }

            // overwrites the existed $saveAs file
            $flags = $isFirst ? 0 : FILE_APPEND;
            if (!@file_put_contents($saveAs, $fileContent . PHP_EOL, $flags)) {
                throw new Exception("Failed to append the contents of '{$fileName}' into '{$saveAs}'.");
            }

            $isFirst = false;
        }
    }

    protected function processScriptGroup($groupItems, $isCss) {
        if (!$this->minify) {
            return $this->trimBaseUrl ? $this->trimBaseUrl($groupItems, $isCss) : $groupItems;
        }

        list($groupItems, $locals) = $this->filterLocals($groupItems);
        if (empty($locals)) {
            return $groupItems;
        }

        $files = $this->getFiles($locals);
        $bigFile = $this->generateBigMinFilePath($files, $isCss ? '.css' : '.js');
        $bigFileUrl = file_exists($bigFile) ? Yii::app()->getAssetManager()->getPublishedUrl($bigFile, true) : null;

        if (!$bigFileUrl) {
            $minFiles = $this->findMinifiedFiles($files);
            $tmpBigFile = tempnam($this->getWorkingDir(), 'min');
            if ($isCss && $this->rewriteCssUrl) {
                $me = $this;
                $this->concat($minFiles, $tmpBigFile, function($content, $url) use (&$me) {
                    return $me->cssUrlAbsolute($content, $url);
                });
            } else {
                $this->concat($minFiles, $tmpBigFile);
            }

            // rename is an atomic operation
            rename($tmpBigFile, $bigFile);
            $bigFileUrl = Yii::app()->getAssetManager()->publish($bigFile, true);
        }

        if ($this->trimBaseUrl) {
            $baseUrl = Yii::app()->getBaseUrl();
            $bigFileUrl = $this->canonicalizeUrl($bigFileUrl, $baseUrl);
        }

        // the new css media will be empty.
        $groupItems[$bigFileUrl] = $isCss ? '' : $bigFileUrl;
        return $groupItems;
    }

    protected function processScripts() {
        // No work to do
        if (!($this->minify || $this->trimBaseUrl)) {
            return;
        }

        // Unable to create working directory, nothing can do
        if (!$this->getWorkingDir()) {
            return;
        }

        // array('css url' => 'media type', ... more ...)
        $this->cssFiles = $this->processScriptGroup($this->cssFiles, true);

        // array(
        //     POS_HEAD => array("key" => "url", ... ),
        //      ...
        // )
        $newScriptFiles = array();
        foreach ($this->scriptFiles as $jsPosition => $jsFiles) {
            $newScriptFiles[$jsPosition] = $this->processScriptGroup($jsFiles, false);
        }
        $this->scriptFiles = $newScriptFiles;
    }

    /**
     * Renders the registered scripts.
     * This method is called in {@link CController::render} when it finishes
     * rendering content. CClientScript thus gets a chance to insert script tags
     * at <code>head</code> and <code>body</code> sections in the HTML output.
     * @param string $output the existing output that needs to be inserted with script tags
     */
    public function render(&$output) {
        if (!$this->hasScripts) {
            return;
        }

        $this->renderCoreScripts();

        if (!empty($this->scriptMap)) {
            $this->remapScripts();
        }

        $this->unifyScripts();

        // The following is the only step that needed that we added to CClientScript->render()
        if (YII_DEBUG) {
            $minifyStartTime = microtime(true);
        }
        $this->processScripts();
        if (YII_DEBUG) {
            $minifyEndTime = microtime(true);
            $execution = ($minifyEndTime - $minifyStartTime) * 1000;
            $pageUrl = Yii::app()->getRequest()->getUrl();
            Yii::log("Minify took {$execution} ms on {$pageUrl}", CLogger::LEVEL_PROFILE, 'ext.minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
        }

        $this->renderHead($output);
        if ($this->enableJavaScript) {
            $this->renderBodyBegin($output);
            $this->renderBodyEnd($output);
        }
    }
}
