<?php
/**
 * Return a timeline with the list of notes displayed in
 * a descending chronological order
 */

namespace MarkNotes\Plugins\Task\Timeline;

defined('_MARKNOTES') or die('No direct access allowed');

class Get extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.task.timeline';
	protected static $json_options = 'plugins.options.task.timeline';

	/**
	 * Get the list of notes, relies on the listFiles task
	 * plugin for this in order to, among other things, be
	 * sure that only files that the user can access are
	 * retrieved and not confidential ones
	 */
	private static function getFiles() : array
	{
		$arrFiles = [];

		// Call the listfiles.get event and initialize $arrFiles
		$aeEvents = \MarkNotes\Events::getInstance();
		$args = [&$arrFiles];
		$aeEvents->loadPlugins('task.listfiles.get');
		$aeEvents->trigger('task.listfiles.get::run', $args);

		return $args[0];
	}

	private static function doGetTimeline() : array
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();
		$aeMarkDown = \MarkNotes\FileType\MarkDown::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		$json = [];

		$docs = str_replace('/', DS, $aeSettings->getFolderDocs(true));

		$arrFiles = self::getFiles();

		// -------------------------------------------------------
		// Based on https://github.com/Albejr/jquery-albe-timeline
		// -------------------------------------------------------

		foreach ($arrFiles as $file) {
			// Calling $aeMarkDown->read will, also, fire
			// markdown::read event and thus plugins. This for
			// every files in $arrFiles ==> can be really slow.
			// $content = $aeMarkDown->read($docs.$file);

			// Optimization : just read file on disk,
			// without plugin support

			$fullname = $aeFiles->makeFileNameAbsolute($file);

			$content = $aeFiles->getContent($fullname);

			$relFileName = utf8_encode(str_replace($docs, '', $file));

			$url = rtrim($aeFunctions->getCurrentURL(), '/') . '/' . rtrim($aeSettings->getFolderDocs(false), DIRECTORY_SEPARATOR) . '/';

			$urlHTML = $url . str_replace(DIRECTORY_SEPARATOR, '/', $aeFiles->replaceExtension($relFileName, 'html'));

			$urlSlides = $aeFiles->replaceExtension($urlHTML, 'reveal');
			$urlPDF = $aeFiles->replaceExtension($urlHTML, 'pdf');

			$json[] =
				[
					'fmtime' => $aeFiles->timestamp($fullname),
					'time' => date('Y-m-d', $aeFiles->timestamp($fullname)),
					'header' => htmlentities($aeMarkDown->getHeadingText($content)),
					'body' => [
						[
							'tag' => 'a',
							'content' => $aeFiles->replaceExtension($relFileName, 'html'),
							'rel' => 'noopener',
							'attr' => [
								'href' => $urlHTML,
								'target' => '_blank',
								'title' => $relFileName
							] // attr
						],
						[
							'tag' => 'span',
							'content' => ' ('
						],
						[
							'tag' => 'a',
							'content' => 'slide',
							'rel' => 'noopener',
							'attr' => [
								'href' => $urlSlides,
								'target' => '_blank',
								'title' => $relFileName
							] // attr
						],
						[
							'tag' => 'span',
							'content' => ' - '
						],
						[
							'tag' => 'a',
							'content' => 'pdf',
							'rel' => 'noopener',
							'attr' => [
								'href' => $urlPDF,
								'target' => '_blank',
								'title' => $relFileName
							] // attr
						],
						[
							'tag' => 'span',
							'content' => ')'
						]
					] // body
				];
		} // foreach

		usort($json, function ($a, $b) {
			//return strtotime($a['start_date']) - strtotime($b['start_date']);
			return strcmp($b['fmtime'], $a['fmtime']);
		});

		return $json;
	}

	private static function getJSON() : bool
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();
		$aeJSON = \MarkNotes\JSON::getInstance();

		$sReturn = '';

		$arrSettings = $aeSettings->getPlugins(JSON_OPTIONS_CACHE);
		$bCache = $arrSettings['enabled'] ?? false;

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log('Get the timeline', 'debug');
		}
		/*<!-- endbuild -->*/

		$arr = null;

		if ($bCache) {
			$aeCache = \MarkNotes\Cache::getInstance();

			// The list of files can vary from one user to an
			// another so we need to use his username
			$key = $aeSession->getUser() . '###timeline';

			$cached = $aeCache->getItem(md5($key));
			$arr = $cached->get();
		}

		if (is_null($arr)) {
			$arr['timeline'] = self::doGetTimeline();

			if ($bCache) {
				// Save the list in the cache
				$arr['from_cache'] = 1;
				// Default : 7 days.
				$duration = $arrSettings['duration']['sitemap'];
				$cached->set($arr)->expiresAfter($duration)->addTags(md5('listfiles'));
				$aeCache->save($cached);
				$arr['from_cache'] = 0;
			}
		} else {
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				$aeDebug->log('	Retrieving from the cache', 'debug');
			}
			/*<!-- endbuild -->*/
		} // if (is_null($arr))

		$json = $arr['timeline'];

		$sReturn = $aeJSON->json_encode($json, JSON_PRETTY_PRINT);

		header('Content-Type: application/json');
		echo $sReturn;

		return true;
	}

	private static function getHTML(array $params = []) : bool
	{
		$aeEvents = \MarkNotes\Events::getInstance();
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();
		$aeHTML = \MarkNotes\FileType\HTML::getInstance();

		$arrSettings = $aeSettings->getPlugins(JSON_OPTIONS_OPTIMIZE);
		$bOptimize = $arrSettings['localStorage'] ?? false;

		$template = $aeFiles->getContent($aeSettings->getTemplateFile('timeline'));

		// The template can contains variables so call the variables
		// plugins to translate them
		// (the markdown.variables can be called even if, here, the
		// content is a HTML string)
		$aeEvents->loadPlugins('markdown.variables');
		$tmp = ['markdown' => $template, 'filename' => $params['filename']];
		$args = [&$tmp];
		$aeEvents->trigger('markdown.variables::markdown.read', $args);
		$template = $args[0]['markdown'];

		$aeEvents->loadPlugins('page.html');
		$args = [&$template];
		$aeEvents->trigger('page.html::render.html', $args);
		$template = $args[0];

		$additionnalJS = '';
		$args = [&$additionnalJS];
		$aeEvents->trigger('page.html::render.js', $args);
		$template = str_replace('<!--%ADDITIONNAL_JS%-->', $args[0], $template);

		$additionnalCS = '';
		$args = [&$additionnalCS];
		$aeEvents->trigger('page.html::render.css', $args);
		$template = str_replace('<!--%ADDITIONNAL_CSS%-->', $args[0], $template);

		header('Content-Type: text/html; charset=utf-8');
		echo $aeHTML->replaceVariables($template, '', $params);

		return true;
	}

	public static function run(&$params = null) : bool
	{
		if (self::isEnabled(true)) {
			$aeSession = \MarkNotes\Session::getInstance();

			// filename will be timeline.html or timeline.json, keep only the extension
			$layout = trim($aeSession->get('filename', ''));

			$layout = substr($layout, -4);

			if ($layout === 'html') {
				self::getHTML($params);
			} else {
				self::getJSON();
			}
		} else {
			header('HTTP/1.0 404 Not Found');
			exit();
		}

		return true;
	}
}
