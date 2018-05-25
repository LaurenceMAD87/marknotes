<?php
/**
 * Generate the form for the editor; based on SimpleMDE
 * @link https://github.com/sparksuite/simplemde-markdown-editor
 */
namespace MarkNotes\Plugins\Task\Export;

defined('_MARKNOTES') or die('No direct access allowed');

use Symfony\Component\Yaml\Exception\ParseException;

class Form extends \MarkNotes\Plugins\Task\Plugin
{
	protected static $me = __CLASS__;
	protected static $json_settings = 'plugins.page.html.editor';
	protected static $json_options = 'plugins.options.page.html.editor';

	/**
	* Determine if this plugin is needed or not
	*/
	final protected static function canRun() : bool
	{
		$bCanRun = parent::canRun();

		if ($bCanRun) {
			$aeSession = \MarkNotes\Session::getInstance();
			$bCanRun = boolval($aeSession->get('authenticated', 0));
		}

		if (!$bCanRun) {
			$aeSettings = \MarkNotes\Settings::getInstance();

			$return = array();
			$return['status'] = 0;
			$return['message'] = $aeSettings->getText('not_authenticated', 'You need first to authenticate', true);

			header('Content-Type: application/json');
			echo json_encode($return, JSON_PRETTY_PRINT);
		}

		return $bCanRun;
	}

	/**
	* Return the code for showing the login form and respond to the login action
	*/
	public static function run(&$params = null) : bool
	{
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		header('Content-Type: text/plain; charset=utf-8');
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		// Get the filename from the querystring
		$filename = $aeFunctions->getParam('param', 'string', '', true);
		$filename = json_decode(urldecode($filename));

		// Be sure to have the .md extension
		$filename = $aeFiles->RemoveExtension($filename).'.md';

		// Make filename absolute
		$fullname = $aeFiles->makeFileNameAbsolute($filename);

		if (!$aeFiles->exists($fullname)) {
			echo str_replace('%s', '<strong>'.$filename.'</strong>', $aeSettings->getText('file_not_found', 'The file [%s] doesn\\&#39;t exists'));
			die();
		}

		// In the edit form; keep encrypted data ...
		// unencrypted (we need to be able to see and update them)
		$params['encryption'] = 0;

		$aeMD = \MarkNotes\FileType\Markdown::getInstance();
		$markdown = $aeMD->read($fullname, $params);

		// Get the default language
		$lang=$aeSettings->getLanguage();

		// and now, try to retrieve the language used in the note;
		// this from the YAML block if present
		$yaml = $aeSession->get('yaml', array());

		if ($yaml !== array()) {
			$lib=$aeSettings->getFolderLibs()."symfony/yaml/Yaml.php";
			if ($aeFiles->exists($lib)) {
				include_once $lib;
				try {
					$arrYAML = \Symfony\Component\Yaml\Yaml::parse($yaml);
					$lang = $arrYAML['language']??$lang;
				} catch (ParseException $exception) {
					/*<!-- build:debug -->*/
					if ($aeSettings->getDebugMode()) {
						$aeDebug = \MarkNotes\Debug::getInstance();
						if ($aeDebug->getDevMode()) {
							printf('Task edit.form - Unable to parse the YAML string: %s', $exception->getMessage());
						}
					}
					/*<!-- endbuild -->*/
				}
			}
		}

		// Get the options for the plugin
		// Check if the spellcheck should be enabled or not
		$bSpellCheck = boolval(self::getOptions('spellchecker', true));
		$spellcheck = ($bSpellCheck ? 'spellcheck="true"' : '');

		$imageFolder = dirname($fullname);
		$sEditForm = '';

		$docs = $aeSettings->getFolderDocs(true);
		$imageFolder = rtrim(str_replace($docs, '', $imageFolder),DS);
		$imageFolder .= DS.'.images';

		// Upload form
		$sUploadForm =
			'<div class="box-body pad" style="display:none;" '.
				'id="divEditUpload">'.
				'<div class="editor-wrapper">'.
					'<div class="pull-right box-tools" style="margin:5px;">
						<button type="button" class="btn btn-default btn-sm btn-exit-upload-droparea">
						<i class="fa fa-times"></i></button>
					</div>'.
					'<form action="" class="dropzone" id="upload_droparea"><div>'.
						'<input type="hidden" name="folder" '.
						'value="'.base64_encode($imageFolder).'">'.
					'</div></form>'.
				'</div>'.
			'</div>';

		$sEditForm .=
		'<div class="row">'.
			'<div class="col-md-12">'.
				'<div class="box">'.
					'<div class="box-header">'.
						'<h3 class="box-title">'.utf8_encode($fullname).'</h3>'.
					'</div>'.
					$sUploadForm.
					'<div class="box-body pad" id="divEditEditor">'.
						'<div class="editor-wrapper">'.
							'<textarea rows="999" cols="200"  id="sourceMarkDown" '.
								'lang="'.$lang.'" '.
								$spellcheck.
								'>'.$markdown.
							'</textarea>'.
						'</div>'.
					'</div>'.
				'</div>'.
			'</div>'.
		'</div>';

		echo $sEditForm;

		return true;
	}
}
