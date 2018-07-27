<?php

/* REQUIRES PHP 7.x AT LEAST */

namespace MarkNotes;

defined('_MARKNOTES') or die('No direct access allowed');

class Markdown
{
	protected static $hInstance = null;

	public function __construct()
	{
		return true;
	}

	public static function getInstance()
	{
		if (self::$hInstance === null) {
			self::$hInstance = new Markdown();
		}

		return self::$hInstance;
	}

	/**
	 * Entry point of this class, run a task
	 *
	 * @param string $task
	 * @param string $filename
	 */
	public function process(string $task = '', string $filename = '', array $params = null)
	{
		$aeDebug = \MarkNotes\Debug::getInstance();
		$aeEvents = \MarkNotes\Events::getInstance();
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		// Extend the session
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSession->extend();

		// Be sure the task is lowercase
		$task = strtolower($task);

		$docFolder = $aeSettings->getFolderDocs(true);

		// When no task is mentionned, suppose 'task.export.html'
		if ($task == '') {
			$task = 'task.export.html';
		}

		if ($filename === '') {
			$tmp = urldecode($aeFunctions->getParam('param', 'string', '', true));
			$filename = ((json_decode($tmp) != '') ? json_decode($tmp) : $tmp);
		}

		if (trim($filename) !== '') {
			$filename = str_replace('/', DS, $filename);

			// The filename shouldn't mention the docs
			// folders, just the filename
			// So, $filename should not be docs/markdown.md
			// but only markdown.md because the folder name
			// will be added later on

			$docRoot = $aeSettings->getFolderDocs(false);

			if ($aeFunctions->startsWith($filename, $docRoot)) {
				$filename = substr($filename, strlen($docRoot));
			}

			$docRoot = $aeSettings->getFolderDocs(false);
		}

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log('Running task [' . $task . ']', 'debug');
			$aeDebug->log('Run [' . $task . ']' .
				($filename !== '' ? ' filename [' . $filename . ']' : ''), 'debug', 3);
		}
		/*<!-- endbuild -->*/

		if ($params === null) {
			$params = [];
		}

		$params['filename'] = $filename;
		$params['task'] = $task;

		// Remember somes variables into the server_session
		$aeSession->set('task', $task);
		$aeSession->set('filename', $filename);
		$aeSession->set('layout', ($params['layout'] ?? ''));

		// Read, on the querystring, if there is a ?disable_cache parameter
		// Can we use the cache system ? Default is false (use the cache)
		$disableCache = $aeFunctions->getParam('disable_cache', 'bool', false);
		$params['disable_cache'] = $disableCache ? 1 : 0;

		// Process "core" tasks i.e. not part of a plugin
		switch ($task) {
			case 'index':
				// Display a dynamic index page
				$aeTask = \MarkNotes\Tasks\Index::getInstance();
				header('Content-Type: text/html; charset=utf-8');
				echo $aeTask->run($params);
				break;

			case 'main':
				// Display the interface of marknotes, with the treeview
				// and the selected note content
				$aeTask = \MarkNotes\Tasks\ShowInterface::getInstance();
				echo $aeTask->run();
				break;

			default:
				if (!$aeFunctions->startsWith($task, 'task.')) {
					$task = 'task.' . $task;
				}

				$aeEvents = \MarkNotes\Events::getInstance();
				if ($aeFunctions->startsWith($task, 'task.export.')) {
					// Run the "export.before" events

					// $task is f.i. task.export.txt,
					// retrieve the 'txt' part i.e. the format : it's
					// the last part
					$tmp = explode('.', $task);
					$format = $tmp[count($tmp) - 1];

					$aeEvents->loadPlugins('task.export.before');
					$params['extension'] = $format;
					$args = [&$params];
					$aeEvents->trigger('task.export.before::run', $args);
				}

				// --------------------------------
				// Call task plugins (f.i. task.export, task.search,
				// ...)
				$aeEvents->loadPlugins($task);
				$args = [&$params];
				$aeEvents->trigger($task . '::run', $args);

				if ($aeFunctions->startsWith($task, 'task.export.')) {
					// Run the "export.after" events
					$aeEvents->loadPlugins('task.export.after');
					//$params['extension']=$format;

					$args = [&$params];
					$aeEvents->trigger('task.export.after::run', $args);
				}

				break;
		} // switch ($task)

		// Security; remove somes Session replaceVariables
		$aeSession->free();
	}
}
