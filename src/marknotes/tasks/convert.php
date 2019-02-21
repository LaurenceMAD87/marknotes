<?php
/**
 * Generic class used by different converter like
 * /plugins/content/docx.php, /plugins/content/pdf/pandoc.php, ...
 */

namespace MarkNotes\Tasks;

use \Symfony\Component\Yaml\Yaml;

defined('_MARKNOTES') or die('No direct access allowed');

class Convert
{
    private $sMDFileName = '';	// File to convert

    // For instance "docx" or "odt"
    private $sLayout = '';

    // For instance "pandoc" or "decktape"
    private $sMethod = '';

    // plugins options (f.i. the plugins->options->pandoc entry)
    private $arrConfig          = null;
    protected static $hInstance = null;

    public function __construct(string $filename = '', string $layout = '', string $method = '')
    {
        // The source file name i.e. the name of the .md file
        // to convert
        $this->sMDFileName=$filename;

        // The output format (f.i. .docx, .epub, ...)
        $this->sLayout=$layout;

        // Method will be f.i. "pandoc"
        $this->sMethod=$method;

        // And retrieve the configuration to use for that method
        // from settings.json (f.i. plugins.options.task.pandoc)
        $this->arrConfig=self::getConfig();

        return true;
    }

    /**
     * Return the options from settings.json, f.i. then
     * plugins->options->task->pandoc entry.
     */
    public function getConfig(): array
    {
        if (null == $this->arrConfig) {
            $aeSettings     = \MarkNotes\Settings::getInstance();
            $this->arrConfig=$aeSettings->getPlugins('options.task.export.' . $this->sMethod);
        }

        return $this->arrConfig;
    }

    /**
     * Check that the converter is well installed on the machine,
     * return True if the converter is there, False otherwise.
     */
    public function isValid(): bool
    {
        $bReturn=true;

        $aeSettings = \MarkNotes\Settings::getInstance();

        if (($this->arrConfig === []) || (!isset($this->arrConfig['script']))) {
            // <!-- build:debug -->
            if ($aeSettings->getDebugMode()) {
                $aeDebug = \MarkNotes\Debug::getInstance();
                $aeDebug->log('Error, options should be ' .
                    'specified in the settings.json file, in the ' . JSON_OPTIONS_PANDOC . ' ' .
                    'node, please verify your settings.json file.', 'warning');
            }
            // <!-- endbuild -->

            $bReturn=false;
        }

        // <!-- build:debug -->
        if ($aeSettings->getDebugMode()) {
            $aeDebug = \MarkNotes\Debug::getInstance();
            $aeDebug->log('Plugin options : ' . json_encode($this->arrConfig), 'debug');
        }
        // <!-- endbuild -->

        if (($bReturn) && ('pandoc' === $this->sMethod)) {
            // Be sure that the script pandoc.exe is well
            // installed on the system
            $aeFiles = \MarkNotes\Files::getInstance();

            // Get the path for pandoc.exe. Since that path is,
            // *always* a relative path (like
            // tools/pandoc/pandoc.exe), make it absolute
            $root        = $aeSettings->getFolderWebRoot();
            $sScriptName = $root . ltrim($this->arrConfig['script'], DS);

            // <!-- build:debug -->
            if ($aeSettings->getDebugMode()) {
                $aeDebug->log('Check if pandoc can be retrieved, ' .
                    '[' . $sScriptName . ']', 'debug');
            }
            // <!-- endbuild -->

            if (!$aeFiles->exists($sScriptName)) {
                // <!-- build:debug -->
                if ($aeSettings->getDebugMode()) {
                    $aeDebug->log('File ' . $sScriptName . ' not ' .
                        'found', 'warning');
                }
                // <!-- endbuild -->

                $bReturn=false;
            }
        }

        return $bReturn;
    }

    public function setLayout(string $layout)
    {
        $this->sLayout = $layout;
    }

    /**
     * Taking the name of the note, provide the name of the
     * file that should be created
     * F.i. for file c:\sites\marknotes\docs\so_nice_app.md return
     * c:\sites\marknotes\docs\so_nice_app.pdf when the layout is .pdf.
     */
    public function getFileName(): string
    {
        $aeFiles    = \MarkNotes\Files::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();

        $fname=$aeFiles->makeFileNameAbsolute($this->sMDFileName);
        $fname=str_replace('/', DS, $fname);

        $fname = $aeFiles->replaceExtension($fname, $this->sLayout);

        // <!-- build:debug -->
        if ($aeSettings->getDebugMode()) {
            $aeDebug = \MarkNotes\Debug::getInstance();
            $aeDebug->log('Target file : ' . $fname, 'debug');
        }
        // <!-- endbuild -->

        return $fname;
    }

    /**
     * Return a "slug" from a filename (f.i. return "connectas"
     * when the filename is "connect-as.md").
     */
    public function getSlugName(): string
    {
        $aeFiles     = \MarkNotes\Files::getInstance();
        $aeFunctions = \MarkNotes\Functions::getInstance();

        $slug = $aeFiles->removeExtension(basename($this->sMDFileName));
        $slug = $aeFunctions->slugify($slug);

        return $slug;
    }

    /**
     * Return a "debug filename" (f.i. connect-as_debug.log).
     */
    public function getDebugFileName(): string
    {
        return self::getSlugName() . '_debug.log';
    }

    /*
     * Read the note and call any plugins.
     * Generate a temporary version of the note in the
     * temporary folder
     */
    public function createTempNote(): string
    {
        $aeFiles    = \MarkNotes\Files::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();
        $aeSession  = \MarkNotes\Session::getInstance();
        $aeMarkdown = \MarkNotes\FileType\Markdown::getInstance();

        $fname=$this->sMDFileName;

        if (!$aeFiles->exists($fname)) {
            $fname=$aeSettings->getFolderDocs(true) . $fname;
        }

        // The read method is also responsible to run
        // any markdown.read plugins
        $content=$aeMarkdown->read($fname);

        // Derive the temporary filename
        $filename=$aeSettings->getFolderTmp() . self::getSlugname($fname) . '.md';

        // Check if there is a YAML header and if so,
        // add in back in the .md file
        $yaml=trim($aeSession->get('yaml', ''));

        if ('' !== $yaml) {
            $lib=$aeSettings->getFolderLibs() . 'symfony/yaml/Yaml.php';

            if ($aeFiles->exists($lib)) {
                include_once $lib;

                // Yaml::dump will add double-quotes so remove them
                $content=
                    '---' . PHP_EOL .
                    str_replace('\\n', PHP_EOL, trim(Yaml::dump($yaml), '"')) . PHP_EOL .
                    '---' . PHP_EOL . PHP_EOL .
                    $content;
            }
        }

        // <!-- build:debug -->
        /*
        $aeDebug = \MarkNotes\Debug::getInstance();
        if ($aeDebug->getDevMode()) {
            $aeDebug->here("DEVELOPPER MODE - SHOW THE MARKDOWN CONTENT BEFORE PANDOC CONVERSION (".$filename.")",1);
            die("<pre>".print_r($content, true)."</pre>");
        }*/

        // Return the temporary filename or an empty string
        if ($aeFiles->exists($filename)) {
            $aeFiles->delete($filename);
        }

        return $aeFiles->create($filename, $content) ? $filename : '';
    }

    public function getScript(string $InputFileName, string $TargetFileName): string
    {
        $sScript = '';

        if ('pandoc' === $this->sMethod) {
            $sScript = self::getPandocScript($InputFileName, $TargetFileName);
        }

        return $sScript;
    }

    public function run(string $sScript, string $TargetFileName)
    {
        $aeFiles    = \MarkNotes\Files::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();

        $fScriptFile = $aeSettings->getFolderTmp() . self::getSlugName() . '.bat';
        $fScriptFile = str_replace('/', DS, $fScriptFile);

        if ($aeFiles->exists($fScriptFile)) {
            $aeFiles->delete($fScriptFile);
        }

        if (!$aeFiles->create($fScriptFile, $sScript)) {
            // <!-- build:debug -->
            if ($aeSettings->getDebugMode()) {
                $aeDebug = \MarkNotes\Debug::getInstance();
                $aeDebug->log('Error while creating file ' . $fScriptFile, 'warning');
            }
            // <!-- endbuild -->
        }

        if (!$aeFiles->exists($fScriptFile)) {
            // <!-- build:debug -->
            if ($aeSettings->getDebugMode()) {
                $aeDebug = \MarkNotes\Debug::getInstance();
                if ($aeDebug->getDevMode()) {
                    $aeDebug->here('The file [' . $fScriptFile . '] is missing; ' .
                        'should be impossible', 10);
                }
                $aeDebug->log('The file [' . $fScriptFile . '] is missing', 'warning');
            }
            // <!-- endbuild -->
        } // if (!$aeFiles->exists($fScriptFile))

        // Run the script.
        // This part can be long depending on the size of the .md file

        set_time_limit(0);

        $output = [];
        exec('start cmd /c ' . $fScriptFile, $output);

        // Once the exec() statement is finished

        if ($aeFiles->exists($TargetFileName)) {
            // The file has been correctly exported, the batch
            // is no more needed

            // <!-- build:debug -->
            $aeDebug = \MarkNotes\Debug::getInstance();
            if (!$aeDebug->getDevMode()) {
                // Kill the script file only when not Developper mode
                // <!-- endbuild -->
                $aeFiles->delete($fScriptFile);
                // <!-- build:debug -->
            }
            // <!-- endbuild -->
        } // if (!$aeFiles->exists($final))

/*
        die(__FILE__." - ".__LINE__. " -  called, is this still needed ?");

        // If the filename doesn't mention the file's extension, add it.
        if (substr($params['filename'], -3) != '.md') {
            $params['filename'] .= '.md';
        }

        $aeFiles = \MarkNotes\Files::getInstance();
        $aeSettings = \MarkNotes\Settings::getInstance();

        $layout = isset($params['layout']) ? $params['layout'] : '';

        // Retrieve the fullname of the file that will be generated
        // The task can be "docx" or "pdf" i.e. the file's extension
        $final = self::getFileName($params['filename'], $params['task']);

        // And check if the file already exists => faster than creating on-the-fly
        if ($aeFiles->exists($final)) {
            $fMD = $aeSettings->getFolderDocs(true).$aeFiles->replaceExtension($params['filename'], 'md');
            if (filemtime($final) < filemtime($fMD)) {
                // The note has been modified after the generation of the .pdf => no more up-to-date
                $final = '';
            }
        }

        // Doesn't exists yet ? Create it
        if (($final === '') || (!$aeFiles->exists($final))) {

            // Try to use the best Converter
            $converter = '';

            // The exec() function should be enabled to use deckTape
            $aeFunctions = \MarkNotes\Functions::getInstance();
            if (!$aeFunctions->ifDisabled('exec')) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    if (in_array($layout, array('reveal', 'remark'))) {

                        // deckTape is only for slideshow view and not for HTML view
                        $converter = ($aeSettings->getConvert('decktape') !== array() ? 'decktape' : '');
                    } else { // if (in_array($layout, array('reveal', 'remark')))

                        // Check for pandoc
                        $converter = ($aeSettings->getConvert('pandoc') !== array() ? 'pandoc' : '');
                    }
                } // if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            } // if (!$aeFunctions->ifDisabled('exec'))

            switch ($converter) {
                case 'decktape':
                    $aeConvert = \MarkNotes\Tasks\Converter\Decktape::getInstance();
                    break;

                case 'pandoc':
                    $aeConvert = \MarkNotes\Tasks\Converter\Pandoc::getInstance();
                    break;

                default:
                    $aeConvert = \MarkNotes\Tasks\Converter\Dompdf::getInstance();
                    break;
            }

            $final = $aeConvert->run($params);
        }

        // Return the fullname of the file
        return $final;*/
    }

    private function getPandocScript(string $InputFileName, string $TargetFileName): string
    {
        $aeSettings = \MarkNotes\Settings::getInstance();

        $debugFile=self::getDebugFileName();
        $slug     =self::getSlugName();

        $template = '';

        // Get the template to use, if any
        if ('txt' !== $this->sLayout) {
            $template = $aeSettings->getTemplateFile($this->sLayout, '');

            $template = str_replace('/', DS, $template);

            // Pandoc don't support --reference-txt
            if ('' !== $template) {
                $template='--reference-' . $this->sLayout . '="' . $template . '" ';
            }
        }

        // Retrieve the options for this conversion
        // Found in settings.json->plugins->options->METHOD->options
        // Method is a supported method like "pandoc"
        $options = isset($this->arrConfig['options'][$this->sLayout]) ? $this->arrConfig['options'][$this->sLayout] : '';

        // Get the path for pandoc.exe. Since that path is,
        // *always* a relative path (like
        // tools/pandoc/pandoc.exe), make it absolute
        $root   = $aeSettings->getFolderWebRoot();
        $script = '"' . $root . ltrim($this->arrConfig['script'], DS) . '" ';
        $script = str_replace('/', DS, $script);

        // Output filename
        $outFile='-o "' . basename($TargetFileName) . '" ';
        $inFile =basename($InputFileName);

        $killFiles='';

        // <!-- build:debug -->
        if ($aeSettings->getDebugMode()) {
            $aeDebug = \MarkNotes\Debug::getInstance();
            if (!$aeDebug->getDevMode()) {
                // Once copied, kill from temp
                $killFiles=
                    'if exist "' . $TargetFileName . '" (' . PHP_EOL .
                    '	del "' . basename($TargetFileName) . '"' . PHP_EOL .
                    '	del "' . $debugFile . '"' . PHP_EOL .
                    '	del "' . $inFile . '"' . PHP_EOL .
                    ')';
            } // if (!$aeDebug->getDevMode())
        }
        // <!-- endbuild -->

        $sScript =
            '@ECHO OFF' . PHP_EOL .
            // Change default code page of Windows console to UTF-8
            // @link : https://superuser.com/questions/269818
            'chcp 65001' . PHP_EOL .
            // Make the temporary folder the working folder
            'cd "' . $aeSettings->getFolderTmp() . '"' . PHP_EOL .
            // Kill the old debug informations
            'if exist "' . $debugFile . '" del "' . $debugFile . '"' . PHP_EOL .
            // run the tool
            $script . $template . $options . ' ' . $outFile . '"' . $inFile . '" > ' . $debugFile . ' 2>&1' . PHP_EOL .
            // Copy the result file in the correct folder
            'copy "' . basename($TargetFileName) . '" "' . $TargetFileName . '"' . PHP_EOL .
            $killFiles;

        return $sScript;
    }

    public static function getInstance(string $filename, string $layout, string $method = '')
    {
        if (null === self::$hInstance) {
            self::$hInstance = new Convert($filename, $layout, $method);
        }

        return self::$hInstance;
    }
}
