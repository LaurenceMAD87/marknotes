<?php
/**
 * Working with files.
 *
 * Note : this class will handle two types of paths, real or
 * symbolic.
 *
 * Symbolic link concern the files that are not "really" in the
 * web folder (f.i. c:\sites\notes) but elsewhere (f.i.
 * c:\repo\marknotes). In the web folder, we can have a folder like
 * the "marknotes" folder (which contains the source code of
 * marknotes) and that folder is a symbolic link to
 * c:\repo\marknotes.
 *
 * By checking if the folder "marknotes" exists in "c:\sites\notes",
 * the result will be False. We need to check "c:\repo\marknotes".
 *
 * Therefore, before checking if a file/folder exists, we need to
 * check if the path is for the "web folder" or the
 * "application folder".
 *
 * Using Flysystem : @https://github.com/thephpleague/flysystem
 */

namespace MarkNotes;

defined('_MARKNOTES') or die('No direct access allowed');

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

class Files
{
	protected static $hInstance = null;
	protected static $flyWebRoot = null; // web root
	protected static $flyAppRoot = null; // application root
	protected static $sWebRoot = '';
	protected static $sAppRoot = '';

	// Root folder to the /docs folder i.e. where notes are stored
	protected static $sDocsRoot = '';
	// "FlySystem" for the documentation (can be on Dropbox f.i.)
	protected static $flyDocsRoot = null;

	/**
	 * Create an instance of MarkNotes\Files and Initialize
	 * the $flyWebRoot object and, if needed, $flyAppRoot
	 */
	public function __construct(string $webroot = '')
	{
		if ($webroot=='') {
			// Get the root folder of marknotes (f.i. C:\sites\marknotes\
			// or /home/html/sites/marknotes/)
			self::$sWebRoot = rtrim(dirname($_SERVER['SCRIPT_FILENAME']), DS);
			self::$sWebRoot = str_replace('/', DS, self::$sWebRoot).DS;
		} else {
			self::$sWebRoot = rtrim($webroot, DS).DS;
		}

		// Application root folder.
		self::$sAppRoot = rtrim(dirname(dirname(__DIR__)), DS).DS;
		self::$sAppRoot = str_replace('/', DS, self::$sAppRoot);

		// Default : empty; will be initialized by setCloud()
		self::$sDocsRoot = '';

		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			self::$sWebRoot = DS.ltrim(self::$sWebRoot, DS);
			self::$sAppRoot = DS.ltrim(self::$sAppRoot, DS);
		}

		// Local::SKIP_LINKS = don't throw fatal errors when
		// a symlink file/folder is found, just ignore it
		// https://flysystem.thephpleague.com/adapter/local/
		$adapter = new Local(static::$sWebRoot, LOCK_EX, Local::SKIP_LINKS);

		// With Flysystem (https://flysystem.thephpleague.com),
		// we can use multiple adapter for, for instance, the local
		// system (FileSystem), for Azure, Dropbox, FTP, WebDAV, ...
		// See https://flysystem.thephpleague.com/adapter/local/
		// So, here below, we are choising for FileSystem i.e. local
		// folder
		static::$flyWebRoot = new Filesystem($adapter);

		// When using symbolic link, the application root (i.e.
		// the folder when marknotes source files are stored) is
		// perhaps different than the web root (where the notes
		// are stored). We then need to have two objects
		if (self::$sWebRoot!==self::$sAppRoot) {
			// Local::SKIP_LINKS = don't throw fatal errors when
			// a symlink file/folder is found, just ignore it
			// https://flysystem.thephpleague.com/adapter/local/
			$adapter = new Local(static::$sAppRoot, LOCK_EX, Local::SKIP_LINKS);
			static::$flyAppRoot = new Filesystem($adapter);
		}

		return true;
	}

	public static function getInstance(string $webroot='')
	{
		if (self::$hInstance === null) {
			self::$hInstance = new Files($webroot);
		}
		return self::$hInstance;
	}

	/**
	 * Initialize the "cloud filesystem" that will then allow to
	 * work with Dropbox, Amazon S3, ... or just the FileSystem
	 */
	public static function setDocFolder(array $arr, string $docFolder) : bool
	{
		$enabled = boolval($arr['enabled']);
		$platform = strtolower($arr['platform']);
		self::$sDocsRoot = $docFolder;

		if ($enabled && ($platform!=='')) {
			// Be sure that we've a token
			if (!isset($arr['token'])) {
				throw new \Exception('FATAL ERROR - No token '.
					'has been provided; you need to specify one '.
					'in the settings.json file, node cloud->token '.
					'as soon as a value has been specified for '.
					'cloud->platform. If you don\'t use a cloud '.
					'system, leave cloud->platform empty.');
			}

			// Get it
			$token = trim($arr['token']);

			if ($platform=='dropbox') {
				$client = new Client($token);
				$adapter = new DropboxAdapter($client);
			}
		} else { // if ($platform!=='')
			// Local::SKIP_LINKS = don't throw fatal errors when
			// a symlink file/folder is found, just ignore it
			// https://flysystem.thephpleague.com/adapter/local/
			$adapter = new Local(self::$sDocsRoot, LOCK_EX, Local::SKIP_LINKS);
		}

		static::$flyDocsRoot = new Filesystem($adapter);
		return true;
	}

	/**
	 * Based on the $path, determine which "filesystem" should
	 * be used.
	 *
	 * In January 2018, three are three filesystems :
	 *
	 *	- One for the documentation folder i.e. where the notes are
	 *		stored (path is something like c:\site\marknotes\docs)
	 *	- One for the webroot folder i.e. the root folder
	 *		(path is something like c:\site\marknotes)
	 *	- One for the application folder i.e. where the application
	 *		is stored (path can be something like
	 *		c:\repository\marknotes). This is only usefull when
	 *		some folders are not real in the webfolder but are symlinks
	 *
	 * The function below will receive an absolute path
	 * (f.i. c:\site\marknotes\docs\subfolder\note.md) and will
	 * decide which "filesystem" should be used.
	 * The function will also modify the $path absolute variable and
	 * make it relative (f.i. subfolder\note.md)
	 */
	private static function getFileSystem(string &$filename, &$obj)
	{
		$filename = str_replace('/', DS, $filename);

		if ((self::$sDocsRoot!=='') && (strpos($filename, self::$sDocsRoot)!==FALSE)) {
			// The folder is stored in the /docs folder
			// ==> can be on a cloud
			$filename = str_replace(self::$sDocsRoot, '', $filename);
			$obj = self::$flyDocsRoot;
		}  else if (strpos($filename, self::$sWebRoot)!==FALSE) {
			// The folder is stored in the webroot folder
			$filename = str_replace(self::$sWebRoot, '', $filename);
			$obj = self::$flyWebRoot;
		}  else {
			// The folder is stored in the application folder
			$filename = str_replace(self::$sAppRoot, '', $filename);
			$obj = self::$flyAppRoot;
		}

		return true;
	}

	/**
	 * Check if a file exists and return FALSE if not.
	 * With FlySystem
	 *
	 * @param  type $filename
	 * @return boolean
	 */
	public static function exists(string $filename) : bool
	{
		if ($filename == '') {
			return false;
		}

		self::getFileSystem($filename, $obj);

		if (trim($filename)=='') {
			return true;
		} else {
			return $obj->has($filename);
		}
	}

	/**
	 * Create a file
	 * With FlySystem
	 */
	public static function create(string $filename, string $content) : bool
	{
		if ($filename == '') {
			return false;
		}

		$arr = array('visibility' => AdapterInterface::VISIBILITY_PUBLIC);
		self::getFileSystem($filename, $obj);

		$wReturn = false;
		try {
			if ($obj->has($filename)) {
				$obj->delete($filename);
			}
			$obj->write($filename, $content, $arr);
			$wReturn = $obj->has($filename);
		} catch (Exception $ex) {
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				echo $ex->getMessage();
				$aeDebug = \MarkNotes\Debug::getInstance();
				$aeDebug->here("", 10);
			}
			/*<!-- endbuild -->*/
		}

		return $wReturn;
	}

	/**
	 * Rename an existing file
	 * With FlySystem
	 */
	public static function rename(string $oldname, string $newname) : bool
	{
		if (($oldname == '') && ($oldname!==$newname)) {
			return false;
		}

		$bReturn = false;
		$old = $oldname;
		$new = $newname;

		self::getFileSystem($newname, $obj);
		self::getFileSystem($oldname, $obj);

		if (self::exists($old)) {
			try {
				$bReturn = $obj->rename($oldname, $newname);
			} catch (Exception $ex) {
				/*<!-- build:debug -->*/
				if ($aeSettings->getDebugMode()) {
					echo $ex->getMessage();
					$aeDebug = \MarkNotes\Debug::getInstance();
					$aeDebug->here("", 10);
				}
				/*<!-- endbuild -->*/
			}
		}

		return $bReturn;
	}

	/**
	 * Remove a file
	 * With FlySystem
	 */
	public static function delete(string $filename) : bool
	{
		if ($filename == '') {
			return false;
		}
		self::getFileSystem($filename, $obj);

		// Don't halt if the file can't be deleted like, f.i.,
		// when the file is locked by a process (this is the case
		// for the active session file)
		$errorlevel = error_reporting();
		error_reporting(0);

		try {
			$bReturn = $obj->delete($filename);
		} catch (\Exception $e) {
		}

		error_reporting($errorlevel);

		return $bReturn;
	}

	/**
	 * Get the content of the file
	 */
	public static function getContent(string $filename) : string
	{
		if ($filename == '') {
			return false;
		}

		self::getFileSystem($filename, $obj);
		if ($obj->has($filename)) {
			return $obj->read($filename);
		} else {
			return '';
		}
	}

	/**
	 * Get the filesize
	 */
	public static function getSize(string $filename) : int
	{
		if ($filename == '') {
			return false;
		}

		self::getFileSystem($filename, $obj);
		if ($obj->has($filename)) {
			return $obj->getSize($filename);
		} else {
			return 0;
		}
	}

	/**
	 * Rewrite an existing file : update his content by a new one
	 * With FlySystem
	 *
	 * @param  string $filename	Absolute filename
	 * @param  string $content	The new content
	 * @return bool				return False in case of error
	 */
	public static function rewrite(string $filename, string $content) : bool
	{
		if ($filename == '') {
			return false;
		}

		$fullname = $filename;

		$bReturn = false;
		self::getFileSystem($filename, $obj);

		try {
			if ($obj->has($filename)) {
				$obj->update($filename, $content);
			} else {
				$arr = array('visibility' => AdapterInterface::VISIBILITY_PUBLIC);
				$obj->write($filename, $content, $arr);
			}
			$bReturn = $obj->has($filename);
		} catch (Exception $ex) {
			/*<!-- build:debug -->*/
			if ($aeSettings->getDebugMode()) {
				echo $ex->getMessage();
				$aeDebug = \MarkNotes\Debug::getInstance();
				$aeDebug->here("", 10);
			}
			/*<!-- endbuild -->*/
		}
		return $bReturn;
	}

	/**
	 * Get the timestamp (last modification date) of a file
	 * The timestamp can be displayed for human like :
	 *
	 *		'f.i. 25-12-2018 23:59:30
	 *		echo date('d-m-Y G:i:s', $timestamp)
	 *
	 * With FlySystem
	 */
	public static function timestamp(string $filename) : string
	{
		if ($filename == '') {
			return false;
		}

		self::getFileSystem($filename, $obj);
		return $obj->getTimestamp($filename);
	}

	/**
	 * Write a content into a UTF8-BOM file
	 */
	/*public static function fwriteUTF8BOM(string $sFileName,
		string $sContent)
	{
		$f = fopen($sFileName, "wb");
		fputs($f, "\xEF\xBB\xBF".$sContent);
		fclose($f);
	}*/
	/**
	 * Under Windows, create a text file with the support of
	 * UTF8 in his content.
	 */
	/*public static function fwriteANSI(string $sFileName, string $sContent)
	{
		file_put_contents($sFileName, utf8_encode($sContent));
		return true;
	}*/

	/**
	* Recursive glob : retrieve all files that are under
	* $path (if empty, $path is the root folder of the website)
	*
	* For instance :
	*		aeSecureFct::rglob('.htaccess', $rootFolder);
	* 		to find every .htaccess files on the server
	*
	* If folders should be skipped :
	*
	*		aeSecureFct::rglob('.htaccess', $rootFolder,
	*			0, array('aesecure','administrator'))
	*
	* @param  type $pattern
	* @param  type $path
	* @param  type $flags
	* @param  type $arrSkipFolder Folders to skip...
	* @return type
	*/
	public static function rglob(string $pattern = '*', string $path = '', int $flags = 0, $arrSkipFolder = null) : array
	{
		// ------------------------------
		// @DEPRECATED
		// CONSIDER TO USE $aeFolders->getContent()
		/*<!-- build:debug -->*/
		$aeSettings = \MarkNotes\Settings::getInstance();
		if ($aeSettings->getDebugMode()) {
			$aeDebug = \MarkNotes\Debug::getInstance();
			$aeDebug->log("CONSIDER TO USE aeFolders->getContent()", "debug");
		}
		/*<!-- endbuild -->*/
		// ------------------------------

		static $adjustCase = false;

		if ($path!=='') {
			$path = str_replace('/', DS, $path);
		}

		// glob() is case sensitive so, search for PHP isn't
		// searching for php.
		// Here, the pattern will be changed to be case insensitive.
		// "*.php" will be changed to "*.[pP][hH][pP]"

		if (($pattern != '') && ($adjustCase == false)) {
			$length = strlen($pattern);
			$tmp = $pattern;
			$pattern = '';
			for ($i = 0; $i < $length; $i++) {
				$pattern .= (ctype_alpha($tmp[$i]) ? '['.strtolower($tmp[$i]).strtoupper($tmp[$i]).']' : $tmp[$i]);
			}
			// Do this only once
			$adjustCase = true;
		}

		// If the "$path" is one of the folder to skip, ... skip it.
		if (($arrSkipFolder != null) && (count($arrSkipFolder) > 0)) {
			foreach ($arrSkipFolder as $folder) {
				if (self::startsWith($folder, $path)) {
					return null;
				}
			} // foreach
		} // if

		$paths = glob($path.'*', GLOB_MARK | GLOB_ONLYDIR);

		// Sort, case insensitive
		usort($paths, 'strnatcasecmp');

		$files = glob(rtrim($path, DS).DS.$pattern, $flags);

		// Sort, case insensitive
		usort($files, 'strnatcasecmp');

		foreach ($paths as $path) {
			// Avoid recursive loop when the folder is a symbolic link
			if (rtrim(str_replace('/', DS, $path), DS) == realpath($path)) {
				$arr = self::rglob($pattern, $path, $flags, $arrSkipFolder);
				if (($arr != null) && (count($arr) > 0)) {
					$files = array_merge($files, $arr);
				}
			} else {
				// $path is a symbolic link.  Doing a glob on a
				// symbolic link will create a recursive
				// call and will crash the script
			}
		} // foreach

		// Don't use the / notation but well the correct
		// directory separator

		foreach ($files as $key => $value) {
			$files[$key] = $value;
		}

		return $files;
	}

	/**
	* Replace file's extension
	 *
	* @param  string $filename	  The filename ("test.md")
	* @param  string $new_extension The new extension ("html")
	* @return string				The new filename (test.html)
	*/
	public static function replaceExtension(string $filename, string $new_extension) : string
	{
		$sResult = $filename;
		$info = pathinfo($filename);
		
		if (($info['extension']??'')!== $new_extension) {
			// Get the filename without the extension and add the new one
			$path = '';

			if (isset($info['dirname'])) {
				$path = rtrim($info['dirname'], DS). DS;
			}
			// Keep the full path
			$sResult = $path . $info['filename'] . '.' . $new_extension;
			//$sResult = self::removeExtension($filename).'.'.$new_extension;
		}

		return $sResult;
	}

	/**
	* Remove file's extension
	 *
	* @param  string $filename The filename ("test.md")
	* @return string				The new filename (test)
	*/
	public static function removeExtension(string $filename) : string
	{
		// Get the extension (in case of a double like .reveal.pdf),
		// get well the double extension
		$ext = self::getExtension($filename);

		if ($ext!=='') {
			// Remove the extension; return "test" and not 	"test.reveal"
			$filename = substr($filename, 0, (strlen($ext)+1) * -1);
		}

		// Return the filename without any extension
		return $filename;

		// Correctly handle double extension like
		// docs\development\marknotes.reveal.pdf
		//$arr = explode('.', $filename);

		//$extension = '';
		//if (count($arr) > 0) {
		//	// Remove the last extension and save it into $extension
		//	$extension = array_pop($arr);
		//}

		//return str_replace('.'.$extension, '', $filename);
	}

	/**
	* Get file's extension
	 *
	* @param  string $filename The filename ("test.md")
	* @return string				The new filename (test)
	*/
	public static function getExtension(string $filename) : string
	{
		$info = pathinfo($filename);

		return $info['extension'] ?? '';

		//$filename = basename($filename);
		// Correctly handle double extension like
		// docs\development\marknotes.reveal.pdf
		//$arr = explode('.', $filename);

		//$extension = '';
		//if (count($arr) > 0) {
		//	unset($arr[0]);
		//	$sResult = implode($arr, '.');
		//}

		//return $sResult;
	}

	/**
	* Be sure that the filename isn't something like f.i.
	* ../../../../dangerous.file
	* Remove dangerouse characters and remove ../
	*
	* @param  string $filename
	* @return string
	*
	* @link http://stackoverflow.com/a/2021729/1065340
	*/
	public static function sanitize(string $filename) : string
	{
		// Remove anything which isn't a word, whitespace, number
		// or any of the following caracters -_~,;[]().
		// If you don't need to handle multi-byte characters
		// you can use preg_replace rather than mb_ereg_replace
		// Thanks @Łukasz Rysiak!

		// Remove any trailing dots, as those aren't ever
		// valid file names.
		$filename = rtrim($filename, '.');

		// Replace characters not in the list below by a dash (-)
		// For instance : single quote, double-quote, parenthesis,
		// ...
		// The list mentionned below is thus the allowed characters
		$regex = array('#[^: A-Za-z0-9&_àèìòùÀÈÌÒÙáéíóúýÁÉÍÓÚÝâêîôûÂÊÎÔÛãñõÃÑÕäëïöüÿÄËÏÖÜŸçÇ\.\\\/\_\- ]#');
		$filename = trim(preg_replace($regex, '-', $filename));

		// Don't allow a double .. in the name and don't allow to
		// start with a dot
		$regex = array('#(\.){2,}#', '#^\.#');
		$filename = trim(preg_replace($regex, '', $filename));

		return $filename;
	}

	/**
	 * A lot of functions requires an absolute filename
	 * and this function will ensure that f.i. "docs/file.md"
	 * will become "/home/public/xxx/docs/file.md".
	 */
	public static function makeFileNameAbsolute(string $filename) : string
	{
		// If $filename already starts with "docs/",
		// remove that part
		$aeSettings	= \MarkNotes\Settings::getInstance();
		$aeFunctions = \MarkNotes\Functions::getInstance();

		// Get the full path
		$doc = $aeSettings->getFolderDocs(true);
		if ($aeFunctions->startsWith($filename, $doc)) {
			// Already absolute
			return $filename;
		}

		// get the relative /docs folder
		$doc = $aeSettings->getFolderDocs(false);

		// Make the slash operating system dependent
		$filename = str_replace('/', DS, $filename);

		if ($aeFunctions->startsWith($filename, $doc)) {
			// If the name already starts with "docs/",
			// remove that part
			$filename = substr($filename, strlen($doc));
		}

		// Now get the absolute path to the /docs folder
		$doc = $aeSettings->getFolderDocs(true);
		// And put that folder before the filename
		$filename = $doc.$filename;

		// Now $filename is absolute
		return $filename;
	}

}
