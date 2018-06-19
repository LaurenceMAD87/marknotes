<?php
/**
* Build the main interface of the application; without showing a note
*/
namespace MarkNotes\Tasks;

defined('_MARKNOTES') or die('No direct access allowed');

class ShowInterface
{
	protected static $root = '';
	protected static $hInstance = null;

	public function __construct()
	{
		self::$root = rtrim(dirname(__DIR__), DS).DS;
		self::$root = str_replace('/', DS, self::$root);
		return true;
	}

	public static function getInstance()
	{
		if (self::$hInstance === null) {
			self::$hInstance = new ShowInterface();
		}

		return self::$hInstance;
	}

	/**
	 * Display a nice information screen and die
	 */
	private static function interfaceDisabled()
	{
		$aeFiles = \MarkNotes\Files::getInstance();

		$fname = self::$root.'errors/error_interface_disabled.html';
		$content = str_replace('%ROOT%', self::$root, $aeFiles->getContent($fname));

		$aeSettings = \MarkNotes\Settings::getInstance();

		$github = $aeSettings->getPlugins('/github', array('url'=>''));
		$content = str_replace('%GITHUB%', $github['url'], $content);

		header('Content-Transfer-Encoding: ascii');
		header('Content-Type: text/html; charset=utf-8');

		die($content);
	}

	private static function fakeLoader(string $html): string
	{
		$aeSettings = \MarkNotes\Settings::getInstance();
		$key = 'plugins.page.html.fakeLoader';
		$arr = $aeSettings->getPlugins($key, array('enabled'=>0));

		if (boolval($arr['enabled'])) {
			// Enabled => the interface shouldn't be displayed
			// during the loading phase but will be make visible
			// by the plugin

			// Capture the body tag and everything until the class
			// attribute so in
			//		<body class="hold-transition fixed skin-blue">
			// capture
			// 		<body class="
			//
			// and add the hidden class (followed by a space)
			// 		<body class="hidden
			//
			// and put the rest ($2 and $3)
			//		hold-transition fixed skin-blue">
			$pattern = '/(<body .*class=")([^"]*)("[^>]*>)/i';
			$html = preg_replace($pattern, '$1hidden $2$3', $html);

			// Retrieve the color to use by the plugin.
			// If not empty, use that color for the body background
			$key = 'plugins.options.page.html.fakeLoader';
			$arr = $aeSettings->getPlugins($key, array('bgColor'=>'#3c8dbc'));

			$bgColor = trim($arr['bgColor']);
			if ($bgColor!=='') {
				$bgColor = 'style="background-color: '.$bgColor.';"';
				$pattern = '/(<body )([^>]*>)/i';
				$html = preg_replace($pattern, '$1'.$bgColor.' $2', $html);
			}

		}

		return $html;
	}

	public function run()
	{
		$aeEvents = \MarkNotes\Events::getInstance();
		$aeFiles = \MarkNotes\Files::getInstance();
		$aeHTML = \MarkNotes\FileType\HTML::getInstance();
		$aeSession = \MarkNotes\Session::getInstance();
		$aeSettings = \MarkNotes\Settings::getInstance();

		$arrSettings = $aeSettings->getPlugins('/interface');
		$canSee = boolval($arrSettings['can_see'] ?? 1);

		if (!$canSee) {
			// The access to the interface can be disabled in settings.json
			self::interfaceDisabled();
		}

		// @link https://github.com/JayBizzle/Crawler-Detect
		// Check the user agent of the current 'visitor' :
		// is it a human or a bot ?
		$arr = array('AbstractProvider', 'Crawlers', 'Exclusions', 'Headers');

		foreach ($arr as $file) {
			require_once($aeSettings->getFolderLibs().'Jaybizzle/crawler-detect/src/Fixtures/'.$file.'.php');
		}

		require_once($aeSettings->getFolderLibs().'Jaybizzle/crawler-detect/src/CrawlerDetect.php');

		$CrawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect;

		// return True when it's a crawler bot
		$isBot=$CrawlerDetect->isCrawler();

		unset($CrawlerDetect);

		// Read the template (in first versions,
		// the template was called "screen"
		$template = $aeFiles->getContent($aeSettings->getTemplateFile('interface'));

		$html = $aeHTML->replaceVariables($template, '', null);

		// Handle the fakeloader plugin
		$html = self::fakeLoader($html);

		// --------------------------------
		// Call page.html plugins so the interface can be built
		$aeEvents->loadPlugins('page.html');
		$args = array(&$html);
		$aeEvents->trigger('page.html::render.html', $args);
		$html = $args[0];

		$additionnalJS = '';
		$args = array(&$additionnalJS);
		$aeEvents->trigger('page.html::render.js', $args);
		$html = str_replace('<!--%ADDITIONNAL_JS%-->', $args[0], $html);

		$css = '';
		$args = array(&$css);
		$aeEvents->trigger('page.html::render.css', $args);
		$html = str_replace('<!--%ADDITIONNAL_CSS%-->', $args[0], $html);

		// Initialize the global marknotes variable (defined in /templates/screen.php)
		$javascript =
		"marknotes.autoload=1;\n".
		"marknotes.isBot=".($isBot?1:0).";\n".
		"marknotes.url='index.php';\n".
		"marknotes.note={};\n".
		"marknotes.note.basename='';\n".
		"marknotes.note.file='';\n".
		"marknotes.note.id='';\n".
		"marknotes.note.url='';\n".
		"marknotes.note.md5='';\n".
		// Don't use jQuery.i18n yet since at this stage,
		// the plugin isn't yet loaded...
		"marknotes.files_found = '".$aeSettings->getText('files_found', '', true)."';\n".
		"marknotes.settings.authenticated=".($aeSession->get('authenticated', 0)?1:0).";\n".
		// Get the username user for the connection
		"marknotes.settings.username=\"".$aeSession->getUser()."\";\n".
		"marknotes.settings.DS='".preg_quote(DS)."';\n".
		"marknotes.settings.locale='".$aeSettings->getLocale()."';\n";
		$html = str_replace('<!--%MARKDOWN_GLOBAL_VARIABLES%-->', '<script>'.$javascript.'</script>', $html);

		return $html;
	}
}
