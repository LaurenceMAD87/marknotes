<?php

namespace MarkNotes;

define('DS', DIRECTORY_SEPARATOR);

class Debugging {

	static $host = '';

	public function __construct(string $host)
	{
		self::$host=$host;
	}

	/**
	 * Helper - Create an accordion
	 */
	private function makeHeading(string $title, string $icon = '', string $tip = '', string $extra = '') : string
	{
		if (trim($tip)!=='') {
			$tip = "data-balloon='".str_replace("'", "\'", $tip)."'";
		}

		if (trim($icon) !=='') {
			$icon = '<i class="fa fa-'.$icon.'" aria-hidden="true"></i> ';
		}

		$return = '<details><summary '.$extra.' '.$tip.'>'.$icon.$title.'</summary>'.
			'<ul>%CONTENT%</ul></details>';

		return $return;
	}

	/**
	 * Helper - Create an URL and add data- elements to properly
	 * manage these links with an ajax request
	 */
	private function makeURL(string $url, string $caption, string $type = 'json', string $title = '') : string
	{
		if (trim($title)!=='') {
			$title = "data-balloon='".str_replace("'", "\'", $title)."'";
		}

		return '<a data-task="ajax" data-type="'.$type.'" '.
			'href="'.self::$host.$url.'" '.$title.' target="_blank">'.
			$caption.'</a>';
	}

	/**
	 * Misc. Actions
	 */
	private function getActions() : string
	{
		$html = self::makeHeading('Actions','terminal');

		$content = '';

		$content .='<li>'.
			self::makeURL('index.php?task=task.listfiles.erase',
			'<i class="fa fa-trash-o" aria-hidden="true"></i> Erase previous output', 'json', 'Remove any .epub, .docx, .html, ... files generated by marknotes in the /docs folder').'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.fetch.gethtml&param=JTIydGVzdHRlc3QuaHRtbCUyMg%3D%3D&url=https%3A%2F%2Fwww.joomla.org%2Fannouncements%2Frelease-news%2F5693-joomla-3-6-5-released.html',
			'<i class="fa fa-download" aria-hidden="true"></i> Fetch HTTPS content', 'html', 'Get HTML of a page').'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.fetch.gethtml&param=JTIydGVzdHRlc3QuaHRtbCUyMg%3D%3D&url=http%3A%2F%2Fphp.net%2Fmanual%2Fen%2Ffunction.urlencode.php',
			'<i class="fa fa-download" aria-hidden="true"></i> Fetch HTTP content', 'html', 'Get HTML of a page').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * CRUD operations on files
	 */
	private function getFiles() : string
	{
		$html = self::makeHeading('Files','file-o');

		$content ='<li>'.
			self::makeURL('index.php?task=task.file.create&param=VGVtcG9yYWlyZSUyRmFhJTJGQUFB','Create file /docs/temporaire/aa/AAA').
			'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.file.rename&param=VGVtcG9yYWlyZSUyRmFhJTJGQkJC&oldname=VGVtcG9yYWlyZSUyRmFhJTJGQUFB','Rename /docs/temporaire/aa/AAA into /docs/temporaire/aa/BBB').
			'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.file.delete&param=&oldname=VGVtcG9yYWlyZSUyRmFhJTJGQkJC','Kill /docs/temporaire/aa/BBB').
			'</li>';
		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * CRUD operations on folders
	 */
	private function getFolders() : string
	{
		$html = self::makeHeading('Folders','folder-o');

		$content ='<li>'.
			self::makeURL('index.php?task=task.folder.create&param=VGVtcG9yYWlyZSUyRmFhJTJGYmNk','Create folder  /docs/temporaire/aa/bcd').
			'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.folder.renameparam=VGVtcG9yYWlyZSUyRmFhJTJGZWZn&oldname=VGVtcG9yYWlyZSUyRmFhJTJGYmNk','Rename /docs/temporaire/aa/bcd into /docs/temporaire/aa/efg').
			'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.folder.delete&oldname=VGVtcG9yYWlyZSUyRmFhJTJGYmNk','Kill folder /docs/temporaire/aa/bcd').
			'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	private function getLogin() : string
	{
		$html = self::makeHeading('Login','sign-in');

		$content ='<li>'.
			self::makeURL('index.php?task=task.login.getform',
			'Get the login screen', 'html').
			'</li>';

		$content .='<li>'.
			self::makeURL('index.php?task=task.login.login&username=JTIyYWRtaW4lMjI=&password=JTIyYWRtaW4lMjI=',
			'Connect as admin/admin', 'json').
			'</li>';

		$content .='<li>'.
			self::makeURL('index.php?task=task.login.logout',
			'Log out', 'json', 'Log out').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;

	}

	/**
	 * These files are prohibited : the user can't access them
	 * by URL
	 */
	private function getProhibitedFiles() : string
	{
		$arr = array(
			'.htaccess'			=> 'html',
			'htaccess.txt'		=> 'html',
			'package.json'		=> 'html',
			'robots.txt.dist'	=> 'html',
			'settings.json'		=> 'html',
			'settings.json.dist'=> 'html'
		);

		$html = self::makeHeading('Prohibited files','ban',
			'These files cannot be accessed by an URL; '.
			'should return a 404 or 403 page', 'class="red"');

		$content = '';

		foreach ($arr as $item => $type) {
			$content .='<li>'.
				self::makeURL($item, $item, $type).'</li>';
		}

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Prepare a few URLs for searching notes
	 */
	private function getSearch() : string
	{
		// A few keywords to search for
		$arr = array('Marknotes', 'plugins', 'aeSecure');

		$html = self::makeHeading('Search','search');

		$content = '';

		foreach ($arr as $item) {
			$content .='<li>'.
				self::makeURL('index.php?task=task.search.search&str='.$item,'Searching for keyword='.$item).'</li>';
		}

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Settings
	 */
	private function getSettings() : string
	{
		$html = self::makeHeading('Settings','cogs');

		$content = '';

		$content .='<li>'.
			self::makeURL('index.php?task=task.settings.display',
				'Display settings.json', 'json', 'Display settings '.
				'once settings.json.dist and settings.json have been '.
				'merged.').'</li>';
		$content .='<li>'.
			self::makeURL('index.php?task=task.settings.show',
				'Show the settings form', 'html', 'Show the settings '.
				'form').'</li>';
		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * These files should be accessible and shouldn't contains
	 * errors
	 */
	private function getSpecialFiles() : string
	{
		$arr = array(
			'buttons.json'	=> 'json',
			'languages/languages.json'=> 'json',
			'languages/marknotes-en.json'=> 'json',
			'languages/marknotes-fr.json'=> 'json',
			'listfiles.json'=> 'json',
			'robots.txt' 	=> 'text',
			'sitemap.xml' 	=> 'xml',
			'tags.json' 	=> 'json',
			'timeline.json' => 'json',
			'timeline.html' => 'html'
		);

		$html = self::makeHeading('Special files','user-secret');

		$content = '';

		foreach ($arr as $item => $type) {
			$content .='<li>'.
				self::makeURL($item, $item, $type).'</li>';
		}

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Tags
	 */
	private function getTags() : string
	{
		$html = self::makeHeading('Tags','tags');

		$content = '';

		$content .='<li>'.
			self::makeURL('index.php?task=task.tags.make',
				'Make tags.json', 'json').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Tips
	 */
	private function getTips() : string
	{
		$html = self::makeHeading('Tips','lightbulb-o');

		$content = '';

		$content .='<li>'.
			self::makeURL('index.php?task=task.tips.show&param=homepage',
				'Homepage', 'html').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Treeview
	 */
	private function getTreeview() : string
	{
		$html = self::makeHeading('Treeview','tree');

		$content = '';

		$content .='<li>'.
			self::makeURL('index.php?task=task.listfiles.treeview',
				'List of files (task)', 'json').'</li>';
		$content .='<li>'.
			self::makeURL('listfiles.json',
				'List of files (listfiles.json)').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Treeview
	 */
	private function getAFewNotes() : string
	{
		$html = self::makeHeading('A few notes','book');

		$content = '';

		$arr = array (
			array(
				'name'=>'/readme',
				'md5'=>'JTIyJTJGcmVhZG1lJTIy'
			)
		);

		$arrLayout = array(
			'html'=>'html',
			'reveal'=>'html',
			'remark'=>'html',
			'txt'=>'text'
		);

		foreach ($arr as $item) {
			$editor = self::makeURL(
				'index.php?task=task.edit.form&param='.$item['md5'], 'editor', 'html'
			);

			$layouts = '';
			foreach ($arrLayout as $layout=>$format) {
				$layouts .= self::makeURL($item['name'].'.'.$layout,
					$layout, $format).' - ';
			}

			$content .='<li> '.$item['name'].'<br/>'.
				$layouts.$editor.'</li>';
		}

		$content .='<li>'.self::makeURL('docs/index.html',
			'docs/index.html', 'html', 'Display the /docs folder '.
			'as an HTML page').'</li>';

		$html = str_replace('%CONTENT%', $content, $html);
		return $html;
	}

	/**
	 * Return the list of options
	 */
	public function getHTML() : string
	{
		$html = file_get_contents('template.html');

		$content = ''.
			'<div class="btn-group toolbar">'.
				'<a class="btn btn-sm" '.
					'data-task="ajax" data-type="json" '.
					'href="'.self::$host.
					'/index.php?task=task.optimize.clear">'.
					'<i class="fa fa-eraser" title="Empty the cache '.
					'folder, be sure to not deliver cached content"></i>'.
				'</a>'.
				'<a class="btn btn-sm" '.
					'data-task="ajax" data-type="json" '.
					'href="'.self::$host.
					'/index.php?task=task.login.logout">'.
					'<i class="fa fa-sign-out" title="Log out"></i>'.
				'</a>'.
			'</div>';

		$content .= self::getActions();
		$content .= self::getFiles();
		$content .= self::getFolders();
		$content .= self::getLogin();
		$content .= self::getProhibitedFiles();
		$content .= self::getSearch();
		$content .= self::getSettings();
		$content .= self::getSpecialFiles();
		$content .= self::getTags();
		$content .= self::getTips();
		$content .= self::getTreeview();
		$content .= self::getAFewNotes();

		return str_replace('%CONTENT%', $content, $html);

	}
}

// ***********************
// ***** ENTRY POINT *****
// ***********************

$bContinue = false;

// Get the webroot folder
$root=trim(dirname($_SERVER['SCRIPT_FILENAME']), DS);
$root=str_replace('/', DS, $root);
$root=dirname($root).DS;

// Check if [webroot]/settings.json is well present
if (is_file($json = $root.'settings.json')) {
	// Read the json file
	$arr = json_decode(file_get_contents($json), true);
	if (isset($arr['debug'])) {
		// This DEBUG interface can be displayed only when both
		// debug mode is enabled and development mode too; this
		// for security reasons
		$debug = $arr['debug']['enabled']??0;
		$dev = $arr['debug']['development']??0;

		// Both should be on 1 (True) to be able to use the interface
		$bContinue = ($debug && $dev);
	}
}

if ($bContinue) {
	$host = 'http://localhost:8080/notes/';
	$aeDebug = new \MarkNotes\Debugging($host);
	echo $aeDebug->getHTML();
	unset($aeDebug);
} else {
	echo '<p>Security measure - To use this interface, please enable '.
		'first the debug and development mode in the settings.json '.
		'file.</p>'.
		'<p>That file should be present in your webroot folder '.
		'and, thus, his name would be '.
		'<strong>'.$root.'settings.json</strong>.</p>'.
		'<p>To enable the development mode, the file should '.
		'contains <strong>at least</strong> these lines :</p>'.
		'<pre>'.
		'{'.PHP_EOL.
		'	"debug": {'.PHP_EOL.
		'		"development": 1,'.PHP_EOL.
		'		"enabled": 1'.PHP_EOL.
		'	}'.PHP_EOL.
		'}'.PHP_EOL.
		'</pre>';
}
