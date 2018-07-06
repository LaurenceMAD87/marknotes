<?php
/**
 * Optimize the HTML rendering, remove comments, minify css, ... *
 * @link https://github.com/matthiasmullie/minify/
 *
 * Note : the optimization can be disabled on the URL by using
 *		the "optimization" parameter. Setting it to 0 will disable
 *		the optimization even if well enabled in settings.json
 *
 * Example index.php?task=task.export.html&param=xxxxx&optimization=0
 */
namespace MarkNotes\Plugins\Page\HTML;

defined('_MARKNOTES') or die('No direct access allowed');

class Optimize extends \MarkNotes\Plugins\Page\HTML\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.page.html.optimize';
	protected static $json_options = JSON_OPTIONS_OPTIMIZE;

	/**
	 * Provide additionnal javascript
	 */
	public static function addJS(&$js = null) : bool
	{
		// Add JS only if needed based on parameters
		$aeSettings = \MarkNotes\Settings::getInstance();
		$arrOptimize = $aeSettings->getPlugins(JSON_OPTIONS_OPTIMIZE);

		$bLocalStorage = boolval($arrOptimize['localStorage'] ?? false);
		$bServerSession = boolval($arrOptimize['server_session'] ?? false);
		$bCache  = boolval($arrOptimize['cache']['enabled'] ?? false);
		$bLazyLoad = boolval($arrOptimize['images']['lazyload'] ?? false);

		// If at least one is set, the button Clear cache is
		// usefull so show it
		if ($bLocalStorage || $bServerSession || $bCache || $bLazyLoad) {
			$aeFunctions = \MarkNotes\Functions::getInstance();
			$aeSettings = \MarkNotes\Settings::getInstance();

			// Get settings
			$arrOptimize = $aeSettings->getPlugins(JSON_OPTIONS_OPTIMIZE);
			$bLocalStorage = boolval($arrOptimize['localStorage'] ?? false);

			$bServerSession = boolval($arrOptimize['server_session'] ?? false);
			$bLazyLoad = boolval($arrOptimize['images']['lazyload'] ?? false);

			$url = rtrim($aeFunctions->getCurrentURL(), '/');
			$url .= '/marknotes/plugins/page/html/optimize/';

			$script = "";

			// If at least one is set, the button Clear cache is
			// usefull so show it
			if ($bLocalStorage || $bServerSession || $bCache) {

				if ($bLocalStorage) {
					$script.="<script ".
					"src=\"".$url."libs/store-js/store.everything.min.js\" ".
					"defer=\"defer\"></script>\n";
				}

				$script.="<script ".
					"src=\"".$url."optimize.js\" ".
					"defer=\"defer\"></script>\n";
			}

			if ($bLazyLoad) {
				$script .= "<script ".
				"src=\"".$url."libs/lazysizes/lazysizes.min.js\" ".
				"defer=\"defer\"></script>\n";
			}

			$prefix = rtrim($aeSettings->getFolderDocs(false), DS);

			$script .= "<script>\n".
			"marknotes.store={};\n".
			"marknotes.store.prefix='".$prefix."';\n".
			"</script>\n";

			$js .= $aeFunctions->addJavascriptInline($script);
		}
		return true;
	}

	/**
	 * Provide additionnal stylesheets
	 */
	public static function addCSS(&$css = null) : bool
	{
		return true;
	}

	/**
	 * Modify the HTML rendering of the note
	 */
	public static function doIt(&$content = null) : bool
	{
		if (trim($content) === '') {
			return true;
		}

		$aeSettings = \MarkNotes\Settings::getInstance();

		// Verify that the optimize task plugin is well enabled
		$arr = $aeSettings->getPlugins('task.optimize');
		$enabled = boolval($arr['enabled']??0);

		if ($enabled==false) {
			// No so just stop
			return true;
		}

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log('Optimizing the page', 'debug');
		}
		/*<!-- endbuild -->*/

		// Get options
		$arrOptimize = $aeSettings->getPlugins(JSON_OPTIONS_OPTIMIZE);

		$arrHeaders=$arrOptimize['headers']??null;
		require_once('optimize/headers.php');
		$class=new Optimize\Headers();
		$class->run($content, $arrHeaders);
		unset($class);

		// Should the images be optimized ?
		$arrImages=$arrOptimize['images']??null;
		if ($arrImages!==null) {
			require_once('optimize/images.php');
			$class=new Optimize\Images();
			$content=$class->run($content, $arrImages);
			unset($class);
		}

		// Should the HTML be optimized ?
		$arrHTML=$arrOptimize['html']??null;
		if ($arrHTML!==null) {
			require_once('optimize/html.php');
			$class=new Optimize\HTML();
			$content=$class->run($content, $arrHTML);
			unset($class);
		}
		// Should the CSS be optimized ?
		$arrCSS=$arrOptimize['css']??null;
		if ($arrCSS!==null) {
			require_once('optimize/css.php');
			$class=new Optimize\CSS();
			$content=$class->run($content, $arrCSS);
			unset($class);
		}

		// Should the javascript be optimized ?
		$arrJS=$arrOptimize['js']??null;
		if ($arrJS!==null) {
			require_once('optimize/js.php');
			$class=new Optimize\JS();
			$content=$class->run($content, $arrJS);
			unset($class);
		}

		return true;
	}

	/**
	 * Verify if the plugin is well needed and thus have a reason
	 * to be fired
	 */
	final protected static function canRun() : bool
	{
		$bCanRun = parent::canRun();

		if ($bCanRun) {

			$aeFunctions = \MarkNotes\Functions::getInstance();

			// Check if the optimization parameter is set
			// on the querystring and if yes, don't run the
			// optimize plugin if set to 0
			$bCanRun = $aeFunctions->getParam('optimization', 'boolean', true);

			if ($bCanRun) {

				$aeSession = \MarkNotes\Session::getInstance();
				$filename = trim($aeSession->get('filename', ''));

				if ($filename !== '') {
					// Don't run add_icons for PDF exportation
					// (will give errors with decktape)
					$aeFiles = \MarkNotes\Files::getInstance();
					$ext = $aeFiles->getExtension($filename);
					$bCanRun = !(in_array($ext, array('pdf', 'reveal.pdf', 'remark.pdf')));
				}
			}
		}

		return $bCanRun;
	}
}
