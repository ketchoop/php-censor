<?php
/**
 * PHPCI - Continuous Integration for PHP
 *
 * @copyright    Copyright 2014, Block 8 Limited.
 * @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
 * @link         https://www.phptesting.org/
 */

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Helper\Lang;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;
use PHPCensor\ZeroConfigPluginInterface;

/**
 * PHP Copy / Paste Detector - Allows PHP Copy / Paste Detector testing.
 *
 * @author       Dan Cryer <dan@block8.co.uk>
 * @package      PHPCI
 * @subpackage   Plugins
 */
class PhpCpd extends Plugin implements ZeroConfigPluginInterface
{
    protected $directory;
    protected $args;

    /**
     * @var string, based on the assumption the root may not hold the code to be
     * tested, extends the base path
     */
    protected $path;

    /**
     * @var array - paths to ignore
     */
    protected $ignore;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'php_cpd';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);

        $this->path   = $this->builder->buildPath;
        $this->ignore = $this->builder->ignore;

        if (!empty($options['path'])) {
            $this->path = $this->builder->buildPath . $options['path'];
        }

        if (!empty($options['ignore'])) {
            $this->ignore = $options['ignore'];
        }
    }

    /**
     * Check if this plugin can be executed.
     * 
     * @param $stage
     * @param Builder $builder
     * @param Build   $build
     * 
     * @return bool
     */
    public static function canExecute($stage, Builder $builder, Build $build)
    {
        if ($stage == 'test') {
            return true;
        }

        return false;
    }

    /**
    * Runs PHP Copy/Paste Detector in a specified directory.
    */
    public function execute()
    {
        $ignore = '';
        if (count($this->ignore)) {
            $map = function ($item) {
                // remove the trailing slash
                $item = rtrim($item, DIRECTORY_SEPARATOR);

                if (is_file(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item)) {
                    return ' --names-exclude ' . $item;
                } else {
                    return ' --exclude ' . $item;
                }

            };
            $ignore = array_map($map, $this->ignore);

            $ignore = implode('', $ignore);
        }

        $phpcpd = $this->builder->findBinary('phpcpd');

        $tmpfilename = tempnam('/tmp', 'phpcpd');

        $cmd = $phpcpd . ' --log-pmd "%s" %s "%s"';
        $success = $this->builder->executeCommand($cmd, $tmpfilename, $ignore, $this->path);

        print $this->builder->getLastOutput();

        $errorCount = $this->processReport(file_get_contents($tmpfilename));
        $this->build->storeMeta('phpcpd-warnings', $errorCount);

        unlink($tmpfilename);

        return $success;
    }

    /**
     * Process the PHPCPD XML report.
     * @param $xmlString
     * @return array
     * @throws \Exception
     */
    protected function processReport($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            $this->builder->log($xmlString);
            throw new \Exception(Lang::get('could_not_process_report'));
        }

        $warnings = 0;
        foreach ($xml->duplication as $duplication) {
            foreach ($duplication->file as $file) {
                $fileName = (string)$file['path'];
                $fileName = str_replace($this->builder->buildPath, '', $fileName);

                $message = <<<CPD
Copy and paste detected:

```
{$duplication->codefragment}
```
CPD;

                $this->build->reportError(
                    $this->builder,
                    'php_cpd',
                    $message,
                    BuildError::SEVERITY_NORMAL,
                    $fileName,
                    (int) $file['line'],
                    (int) $file['line'] + (int) $duplication['lines']
                );
            }

            $warnings++;
        }

        return $warnings;
    }
}
