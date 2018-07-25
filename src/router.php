<?php
/* REQUIRES PHP 7.x AT LEAST */
/**
 * Author : AVONTURE Christophe - https://www.aesecure.com
 *
 * Documentation : https://github.com/cavo789/marknotes/wiki
 * Demo : https://www.marknotes.fr
 */
namespace MarkNotes;

define('_MARKNOTES', 1);

// As fast as possible, enable debugging mode if settings.json->debug mode is enabled
include_once 'marknotes/includes/debug_show_errors.php';

include_once 'marknotes/includes/initialize.php';

$class = new Includes\Initialize();
$bReturn = $class->init();

// Remember the website root folder
$root = $class->getWebRoot();

unset($class);

if ($bReturn) {
	$aeFunctions = \MarkNotes\Functions::getInstance();

	$filename = rawurldecode($aeFunctions->getParam('file', 'string', '', false));
	$filename = rtrim($filename, DS);
	$filename = str_replace('/', DS, $filename);

	$task = rawurldecode($aeFunctions->getParam('task', 'string', '', false));

	// Very special case : when accessing to a file called index.html,
	// the task is task.index.get like defined in the .htaccess file.
	// But, if a file, in the same folder, is called index.md, so, don't
	// call the index task plugin but well the export.html one

	if (basename($filename) == 'index.html') {

		$aeFiles = \MarkNotes\Files::getInstance();
		$md = $aeFiles->makeFileNameAbsolute($filename);
		$md = $aeFiles->replaceExtension($md, 'md');

		if ($aeFiles->exists($md)) {
			$task = "task.export.html";
		}
	}

	$params = array('filename' => $filename);
	// Retrieve the file's extension

	$aeFiles = \MarkNotes\Files::getInstance();
	$ext = trim($aeFiles->getExtension($filename));

	$params['layout'] = '';

	if (in_array($ext, array('reveal','reveal.pdf'))) {
		$params['layout'] = 'reveal';
	} else {
		if (in_array($ext, array('remark','remark.pdf'))) {
			$params['layout'] = 'remark';
		}
	}

	$aeSettings = \MarkNotes\Settings::getInstance($root, $params);

	$aeSession = \MarkNotes\Session::getInstance($root);
	$aeSession->set('filename', $filename);
	$aeSession->set('layout', $params['layout']);
	$aeSession->set('img_id', 0);

	$aeFiles = \MarkNotes\Files::getInstance();
	$aeEvents = \MarkNotes\Events::getInstance();

	// Initialize the DocFolder filesystem
	$aeInitialize = new Includes\Initialize();
	$aeInitialize->setDocFolder();
	unset($aeInitialize);

	if ($filename !== '') {
		// The filename shouldn't mention the docs folders,
		// just the filename. So, $filename should not be
		// docs/markdown.md but only markdown.md because the
		// folder name will be added later on
		$docRoot = $aeSettings->getFolderDocs(false);

		if ($aeFunctions->startsWith($filename, $docRoot)) {
			$filename = substr($filename, strlen($docRoot));
		}
	} // if ($filename !== '')

	$aeMarkDown = new \MarkNotes\Markdown();
	$aeMarkDown->process($task, $filename, $params);
	unset($aeMarkDown);

	/*<!-- build:debug -->*/
	if ($aeSettings->getDebugMode()) {
		$aeDebug = \MarkNotes\Debug::getInstance();
		$aeDebug->logEnd();
		unset($aeDebug);
	}
	/*<!-- endbuild -->*/
}
