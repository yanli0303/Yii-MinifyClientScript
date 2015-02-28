<?php

Yii::import('ext.minify.*');

/**
 * Provide functionalities to minify JS and CSS files that current Yii::app()->request used.
 *
 * @author Yan Li <peterleepersonal@gmail.com>
 */
class MinifyClientScript extends CClientScript {
    /**
     * If this is enabled, during minifying all other requests will wait until completed.
     * If disabled, the unminified files will be delivered for these requests.
     * This should prevent the thundering herd problem.
     *
     * @var bool Whether other requests should pause while minifiying.
     * Defaults to TRUE.
     */
    public $exclusivelyMinify = TRUE;

    /**
     * @var bool Whether remove the Yii::app()->baseUrl from the beginning of resource urls.
     * Defaults to TRUE.
     */
    public $trimBaseUrl = TRUE;

    /**
     * @var bool Whether to rewrite "url()" rules of CSS after relocating CSS files.
     * Defaults to TRUE.
     */
    public $rewriteCssUrl = TRUE;

    /**
     * @var bool Whether to enable minifying.
     * Defaults to TRUE.
     */
    public $minify = TRUE;

    /**
     * @var bool If False, fallback to unminified version whenever any error occurred.
     */
    public $failOnError = FALSE;

    /**
     * @var string The filename(without extension) suffix of minified files.
     * Defaults to '.min' which means a minified file is as in "jQuery.min.js".
     */
    public $minFileSuffix = '.min';

    /**
     * The working directory for minifying.
     * @var string Path to the working directory, default is "~/runtime/minify"
     */
    protected $_workingDirectory = NULL;

    protected function getWorkingDir() {
        if (!$this->_workingDirectory) {
            $wd = Yii::app()->getRuntimePath() . DIRECTORY_SEPARATOR . 'minify';

            // create the directory if not existed
            if (is_dir($wd) || mkdir($wd, 0755, true)) {
                $this->_workingDirectory = $wd;
            } else {
                $msg = 'Unable to create directory for minifying JavaScript/CSS files: ' . $wd;
                Yii::log($msg, CLogger::LEVEL_ERROR, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);

                if ($this->failOnError) {
                    throw new Exception($msg);
                }
            }
        }

        return $this->_workingDirectory;
    }

    protected function isMinified($filename) {
        $fileNamePart = pathinfo($filename, PATHINFO_FILENAME);
        $fileNameSuffix = substr($fileNamePart, -strlen($this->minFileSuffix));
        return $this->minFileSuffix === $fileNameSuffix;
    }

    protected function findMinifiedFile($filename) {
        $lastDotPosition = strrpos($filename, '.');
        $minFile = FALSE === $lastDotPosition ? $filename . $this->minFileSuffix : substr($filename, 0, $lastDotPosition) . $this->minFileSuffix . substr($filename, $lastDotPosition);
        return file_exists($minFile) ? $minFile : NULL;
//        if (!file_exists($minFile)) {
//            if ($this->failOnError) {
//                $msg = 'The minified version was not found for: ' . $filename;
//                Yii::log($msg, CLogger::LEVEL_ERROR, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
//
//                throw new Exception($msg);
//            } else {
//                $msg = 'The minified version was not found for: ' . $filename . ', use itself instead.';
//                Yii::log($msg, CLogger::LEVEL_TRACE, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
//
//                return $filename;
//            }
////        }
//
//        return $minFile;
    }

    private function getFileToConcat($file) {
        return $this->isMinified($file) ? $file : $this->findMinifiedFile($file);
    }

    public function doMinifyAndPublish($items, $type, $saveAs) {
        $saveDir = dirname($saveAs);
        $minifiedFiles = array();
        foreach ($items as $url => $file) {
            $actualFile = $this->getFileToConcat($file);
            if (!$actualFile) {
                Yii::log('File not found: ' . $file, CLogger::LEVEL_ERROR, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
                continue;
            }

            // rewrite css url()
            if ($type === 'css' && $this->rewriteCssUrl) {
                $rewrittenCssFile = MinifyHelper::joinPath($saveDir, MinifyHelper::generateUniqueMinifiedFileName($url, $file, $type));
                $previouslyRewritten = file_exists($rewrittenCssFile) && filesize($rewrittenCssFile) > 8 && filemtime($file) <= filemtime($rewrittenCssFile);
                if ($previouslyRewritten) {
                    $actualFile = $rewrittenCssFile;
                } else {
                    $rewriteSuccessful = MinifyHelper::concat(array($actualFile), $rewrittenCssFile, function($cssContent) use (&$url) {
                                // make url() rules of CSS absolute
                                return MinifyHelper::cssUrlRewrite($cssContent, $url);
                            });

                    if ($rewriteSuccessful) {
                        $actualFile = $rewrittenCssFile;
                    } else {
                        Yii::log("Failed to rewrite CSS url() for '{$file}'.", CLogger::LEVEL_WARNING, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
                    }
                }
            }

            $minifiedFiles[] = $actualFile;
        }

        // file header is 8 bytes, if filesize <= 8, consider it's empty
        if (!MinifyHelper::concat($minifiedFiles, $saveAs) || !file_exists($saveAs) || filesize($saveAs) <= 8) {
            return FALSE;
        }

        Yii::app()->getAssetManager()->publish($saveAs, TRUE);
        return TRUE;
    }

    protected function minifyAndPublish($urls, $type, $baseUrl, $basePath) {
        if (count($urls) === 0) {
            return NULL;
        }

        $items = array();
        foreach ($urls as $url) {
            $relativePath = $this->trimBaseUrl ? MinifyHelper::removeUrlPrefix($baseUrl, $url) : $url;
            $items[$url] = MinifyHelper::joinPath($basePath, $relativePath);
        }

        $groupMaxFileModifiedTime = MinifyHelper::maxFileModifiedTime($items);
        $groupId = MinifyHelper::hashFileNames($items) . $groupMaxFileModifiedTime;
        $minFile = MinifyHelper::joinPath($this->getWorkingDir(), $groupId . '.min.' . $type);

        $me = $this;
        $successful = EMutexHelper::exec('minify', 3, $this->exclusivelyMinify, function() use (&$minFile, &$groupMaxFileModifiedTime) {
                    // TODO: move getPublishedPath step outside (following 1 line)
                    $minFilePublishedPath = Yii::app()->getAssetManager()->getPublishedPath($minFile, TRUE);
                    return !empty($minFilePublishedPath) && file_exists($minFilePublishedPath) && filemtime($minFilePublishedPath) >= $groupMaxFileModifiedTime;
                }, function() use(&$me, &$items, &$type, &$minFile) {
                    return $me->doMinifyAndPublish($items, $type, $minFile);
                });

        if ($successful) {
            $minUrl = Yii::app()->getAssetManager()->getPublishedUrl($minFile, TRUE);
            return $this->trimBaseUrl ? MinifyHelper::removeUrlPrefix($baseUrl, $minUrl) : $minUrl;
        }

        return NULL;
    }

    protected function minifyGroup($urls, $type, $baseUrl, $basePath) {
        if (!$this->minify) {
            if (!$this->trimBaseUrl) {
                return $urls;
            }

            $trimmedUrls = array();
            foreach ($urls as $url => $media) {
                if (MinifyHelper::isExternalUrl($url)) {
                    $trimmedUrls[$url] = $type === 'css' ? $media : $url;
                } else {
                    $url = MinifyHelper::removeUrlPrefix($baseUrl, $url);
                    $trimmedUrls[$url] = $type === 'css' ? $media : $url;
                }
            }

            return $trimmedUrls;
        }

        $externs = array();
        $locals = array();
        foreach ($urls as $url => $media) {
            if (MinifyHelper::isExternalUrl($url)) {
                $externs[$url] = $type === 'css' ? $media : $url;
            } else {
                $locals[] = $url;
            }
        }

        $minUrl = $this->minifyAndPublish($locals, $type, $baseUrl, $basePath);
        if (empty($minUrl)) {
            return $urls;
        }

        // the new css media will be empty.
        $externs[$minUrl] = $type === 'css' ? '' : $minUrl;
        return $externs;
    }

    protected function minifyPage() {
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $minifyStartTime = microtime(TRUE);
        }

        $baseUrl = Yii::app()->getBaseUrl();
        $basePath = Yii::getPathOfAlias('webroot');

        // array(
        //     'css url' => 'media type',
        //      ...
        // )
        $this->cssFiles = $this->minifyGroup($this->cssFiles, 'css', $baseUrl, $basePath);

        // array(
        //     POS_HEAD => array("key" => "url", ... ),
        //      ...
        // )
        $newScriptFiles = array();
        foreach ($this->scriptFiles as $jsPosition => $jsFiles) {
            $newScriptFiles[$jsPosition] = $this->minifyGroup($jsFiles, 'js', $baseUrl, $basePath);
        }
        $this->scriptFiles = $newScriptFiles;
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $minifyEndTime = microtime(TRUE);
            $execution = ($minifyEndTime - $minifyStartTime) * 1000;
            $pageUrl = Yii::app()->getRequest()->getUrl();
            Yii::log("Minify took {$execution} ms on {$pageUrl}", CLogger::LEVEL_PROFILE, 'minify.' . __CLASS__ . '.' . __FUNCTION__ . '.line' . __LINE__);
        }
    }

    /**
     * Renders the registered scripts.
     * This method is called in {@link CController::render} when it finishes
     * rendering content. CClientScript thus gets a chance to insert script tags
     * at <code>head</code> and <code>body</code> sections in the HTML output.
     * @param string $output the existing output that needs to be inserted with script tags
     */
    public function render(&$output) {
        // <editor-fold desc="CClientScript->render">
        if (!$this->hasScripts)
            return;

        $this->renderCoreScripts();

        if (!empty($this->scriptMap))
            $this->remapScripts();

        $this->unifyScripts();
        // </editor-fold>

        /**
         * This is the only step that needed
         */
        $this->minifyPage();

        // <editor-fold desc="CClientScript->render">
        $this->renderHead($output);
        if ($this->enableJavaScript) {
            $this->renderBodyBegin($output);
            $this->renderBodyEnd($output);
        }
        // </editor-fold>
    }
}
