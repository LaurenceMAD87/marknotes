<?php
/**
 * Export the note as a .pdf file
 */

namespace MarkNotes\Plugins\Task\Export;

defined('_MARKNOTES') or die('No direct access allowed');

class PDF extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.task.export.pdf';
	protected static $json_options = '';

	private static $extension = 'pdf';

	/*
	 * Export a note to a .pdf, thanks to dompdf
	 *
	 * dompdf requires external libraries :
	 * (https://github.com/dompdf/dompdf/wiki/Requirements)
	 *	- php-font-lib (https://github.com/PhenX/php-font-lib.git)
	 *	- php-svg-lib (https://github.com/PhenX/php-svg-lib.git)
	 *
	 * They should be installed manually
	 */
	private static function domPDF(&$params = null)
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		$bReturn= true;

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log("Use domPDF", "debug");
		}
		/*<!-- endbuild -->*/

		// $params['filename'] is f.i. note.docx, retrieve the .md
		// filename
		$mdFilename = $aeFiles->removeExtension($params['filename']).'.md';

		// ----------------------------------------
		// Call the generic class for file conversion
		$aeConvert = \MarkNotes\Tasks\Convert::getInstance($mdFilename, static::$extension, 'dompdf');

		// Get the filename, once exported (f.i. notes.pdf)
		$final = $aeConvert->getFileName();

		// Derive the filename for the debugging file
		$debugFile = $aeSettings->getFolderTmp().$aeConvert->getDebugFileName().'.html';

		// Use the pdf template and not the "html" one
		$params['task'] = 'pdf';
		$params['template'] = 'pdf';

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log('Get the note\'s html content', 'debug');
		}
		/*<!-- endbuild -->*/

		$aeTask = \MarkNotes\Tasks\Display::getInstance();
		$html = $aeTask->run($params);

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log("Initialize Dompdf", "debug");
			$aeDebug->log("**Dompdf error logfile** : ".$debugFile, "debug");
		}
		/*<!-- endbuild -->*/

		include_once $aeSettings->getFolderLibs().'dompdf/dompdf/autoload.inc.php';
		$dompdf_options = array(
			'logOutputFile' => $debugFile,
			'isHtml5ParserEnabled' => true,
			'debugPng' => false,
			'debugKeepTemp' => false,
			'debugCss' => false,
			'debugLayout' => false,
			'debugLayoutLines' => false,
			'debugLayoutBlocks' => false,
			'debugLayoutInline' => false,
			'debugLayoutPaddingBox' => false
		);
		$dompdf = new \Dompdf\Dompdf($dompdf_options);
		$dompdf->getOptions()->setTempDir($aeSettings->getFolderTmp());

		// Needed for f.i. correctly retrieve images (relative filenames)
		$dompdf->set_base_path(dirname($final).DS);

		// instantiate and use the dompdf class
		$dompdf->loadHtml($html);

		// (Optional) Setup the paper size and orientation
		$dompdf->setPaper('A4', 'portrait');

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log('Start Dompdf rendering', 'debug');
		}
		/*<!-- endbuild -->*/

		// Render the HTML as PDF
		// *** Don't stop on errors ***
		@$dompdf->render();

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug->log('Save Dompdf output to '.$final, 'debug');
		}
		/*<!-- endbuild -->*/

		$output = $dompdf->output();
		$aeFiles->rewrite($final, $output);
		return array($bReturn, $final);
	}

	/**
	 * Export a note thanks to pandoc (on Windows OS)
	 */
	private static function pandoc(&$params = null)
	{

		$bReturn = true;
		$aeSettings = \MarkNotes\Settings::getInstance();

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log("Use pandoc", "debug");
		}
		/*<!-- endbuild -->*/

		// ----------------------------------------
		// Call the generic class for file conversion
		$aeConvert = \MarkNotes\Tasks\Convert::getInstance($params['filename'], static::$extension, 'pandoc');

		// Get the filename, once exported (f.i. notes.txt)
		$final = $aeConvert->getFileName();

		// Check if pandoc is installed; if not, check if the
		// exported file already exists
		if (!$aeConvert->isValid()) {
			$bReturn = false;
		} else {
			// if (!$aeConvert->isValid())
			$arrPandoc = $aeSettings->getPlugins(JSON_OPTIONS_PANDOC);

			// Read the content of the .md file
			$filename = $aeSettings->getFolderDocs(true).$params['filename'];

			// Derive filenames
			$slug = $aeConvert->getSlugName();
			$debugFile = $aeConvert->getDebugFileName();

			// Get a copy of the .md note (in /temp folder),
			// run any plugins
			$tempMD = $aeConvert->createTempNote();
			$sScript = $aeConvert->getScript($tempMD, $final);
			$aeConvert->Run($sScript, $final);
		} // if (!$aeConvert->isValid())

		return array($bReturn, $final);
	}

	/**
	 * Export a slideshow (reveal or remark), thanks to decktape
	 */
	private static function deckTape(&$params = null)
	{
		$aeSettings = \MarkNotes\Settings::getInstance();

		/*<!-- build:debug -->*/
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log("Use decktape", "debug");
		}
		/*<!-- endbuild -->*/

		$aeDebug = \MarkNotes\Debug::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		// DeckTape is only for slides : reveal or remark
		// and not for "normal" html rendering
		$layout = $params['layout'] ?? '';
		if (!in_array($layout, array('remark','reveal'))) {
			return array(false, '');
		}

		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();

		$root = $aeSettings->getFolderWebRoot();
		$arrSettings = $aeSettings->getPlugins('plugins.options.task.export.decktape');

		if ($arrSettings === array()) {
			return array(false, '');
		}

		$sScriptName = $root.ltrim($arrSettings['script'], DS);
		$sScriptName = str_replace('/', DS, $sScriptName);

		if (!$aeFiles->exists($sScriptName)) {
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				$aeDebug->here('ERROR - Decktape, file '.$sScriptName.' not found', 5);
			}
			/*<!-- endbuild -->*/
			return array(false, '');
		}

		// $params['filename'] is f.i. note.reveal.pdf or
		// note.remark.pdf so retrieve the .md by removing the
		// two extensions (.reveal.pdf)

		// remove the .pdf
		$basename = $aeFiles->removeExtension($params['filename']);
		// remove the .reveal
		$basename = basename($aeFiles->removeExtension($basename));

		$mdFilename = $basename.'.md';
		$params['filename'] = $mdFilename;

		$aeConvert = \MarkNotes\Tasks\Convert::getInstance($mdFilename, $layout, 'decktape');

		// For instance c:\site\marknotes\tmp\note.reveal.html
		$tmpHTML = $aeSettings->getFolderTmp().$basename.'.'.$params['layout'].'.html';

		// Derive the resulting filename
		// will be .reveal.pdf or .remark.pdf
		$aeConvert->setLayout($layout.'.'.static::$extension);
		$final = $aeConvert->getFileName();

		// When this function is called, the task.export.xxx
		// (where xxx is reveal or remark) has already be fired
		// and the HTML slideshow is stored in
		// the $params['content'] entry so just get it back
		$html = $params['content'];

		// Create the HTML file on the disk, in the tmp folder
		$aeFiles->rewrite($tmpHTML, $html);

		// Create a script on the disk
		// Phantomjs (used by the Decktape conversion)
		// should be started from the folder where the
		// HTML file to convert stay so use the Windows PUSH
		// instruction to change the default directory
		// the time needed to run the script
		// Be carefull : decktape don't like accentuated
		// characters names below (to the html and pdf file)
		// shouldn't contains any accentuated characters
		// Use 'chcp 65001' command, accentuated characters won't
		// be correctly understand if
		// the file should be executable (like a .bat file)
		// see https://superuser.com/questions/269818/change-default-code-page-of-windows-console-to-utf-8
		$sProgram =
			'@ECHO OFF'.PHP_EOL.
			'chcp 65001'.PHP_EOL.
			'pushd "'.dirname($tmpHTML).'"'.PHP_EOL.
			'"'.$sScriptName.'" "'.dirname($sScriptName).DS.'decktape.js" '.$layout.' "'.basename($tmpHTML).'"'.
			' "'.$final.'"'.PHP_EOL.
			'popd';

		$slug = $aeFunctions->slugify($aeFiles->removeExtension(basename($params['filename'])));

		$fScriptFile = dirname($tmpHTML).DS.$slug.'.bat';

		// Create the DOS batch file
		$aeFiles->rewrite($fScriptFile, $sProgram);

		// Run the script. This part can be long depending on the
		// number of slides in the HTML file to convert
		$output = array();

		// Disable the PHP timeout and give time to DeckTape
		// for the conversion
		set_time_limit(0);

		exec($fScriptFile, $output);

		$bReturn = $aeFiles->exists($final);

		return array($bReturn, $final);
	}

	public static function run(&$params = null) : bool
	{
		$bReturn = true;

		$aeDebug = \MarkNotes\Debug::getInstance();
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		$aeConvert = \MarkNotes\Tasks\Convert::getInstance($params['filename'], static::$extension, 'pandoc');
		$aeConvert->setLayout(static::$extension);

		// Get the filename, once exported (f.i. notes.txt)
		$final = $aeConvert->getFileName();

		// Generate the file ... only if not yet there
		if (!$aeFiles->exists($final)) {

			$layout = $params['layout'] ?? '';

			if (in_array($layout, array('remark','reveal'))) {
				// This is a slideshow => use deckTape for
				// the exportation
				list($bReturn, $final) = self::deckTape($params);
			} else {

				// Check if domPDF is installed
				$domPDF = $aeSettings->getFolderLibs().'Dompdf/dompdf/autoload.inc.php';
				if ($aeFiles->exists($domPDF)) {
					list($bReturn, $final) = self::domPDF($params);
				} else {
					list($bReturn, $final) = self::pandoc($params);
				}

			} // if (in_array($layout, array('remark','reveal')))
		} //if(!$aeFiles->exists($final))
		// In case of error, there is no output at all
		$params['output'] = ($bReturn ? $final : '');
		return $bReturn;
	}
}
