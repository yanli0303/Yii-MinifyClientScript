# Yii-MinifyClientScript #
A PHP [Yii framework](http://www.yiiframework.com/ "Yii Framework Home") extension to minify JavaScript and CSS files for a web page. 

## How does it work? ##

1. Minify each JavaScript and CSS files before deployment (during build stage):
	- For each JavaScript/CSS file we have, generated a minified version in same directory, and name it with a **.min** suffix;
	- e.g. Minified version of `~/src/css/style.css` should be named as `~/src/css/style.min.css`
2. Concatenate the JavaScript/CSS files required on the page at run-time


## Usage ##

1. Create a new extension directory for your Yii application: `~/protected/extensions/minify`
2. Download both [src/MinifyClientScript.php](https://github.com/yanli0303/Yii-MinifyClientScript/blob/master/src/MinifyClientScript.php) and [LICENSE.md](https://github.com/yanli0303/Yii-MinifyClientScript/blob/master/LICENSE.md), put them into `~/protected/extensions/minify`
3. Update your Yii application configuration file(usually named `/protected/config/main.php`) and replace the Yii [CClientScript](http://www.yiiframework.com/doc/api/1.1/CClientScript) with `MinifyClientScript`:
```PHP	
return array(
    'basePath' => __DIR__ . '/..',
    'name' => 'Your App Name',
    'preload' => array('log'),
    'import' => array(
        'application.models.*',
        'application.components.*',
        'application.extensions.*'
    ),
    'clientScript' => array(
        'class' => '**ext.minify.MinifyClientScript**',
        'minify' => **!YII_DEBUG**,
        // put all js files before end </body> tag
        // note this setting won't affect css files, they will be put in <head>
        'coreScriptPosition' => CClientScript::POS_END,
        'packages' => array(
            'home_page' => array(
                'baseUrl' => '',
                'js' => array(
                    'bower_components/jquery/jquery.js',
                    'bower_components/angular/angular.js',
                    'bower_components/bootstrap/dist/js/bootstrap.js',
                    'js/home/home_index.js'
                ),
                'js' => array(
                    'bower_components/bootstrap/dist/css/bootstrap.css',
                    'css/home/home_index.css'
                )
            )
        )
    )
);
```
4. Before deploying, minify the individual JavaScript and CSS files:
	- You can do it with [Ant-MinifyJsCss](https://github.com/yanli0303/Ant-MinifyJsCss)
5. Pack your application sources and deploy 

**Note**: set `"minify" => !YII_DEBUG` to disable minifying while developing

## License ##
MIT