<?php

namespace Aquis\XporterBundle\Service\FixtureDump;

use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Class FixturesWriter.
 *
 * @copyright Aquis Grana impex srl (http://www.webnou.ro/)
 * @author    Petronel Malutan <malutanpetronel@gmail.com>
 */
class FixturesWriter
{
    /**
     * The name of the exported fixtures file.
     */
    const FILENAME = 'data.yml';

    /**
     * @var string
     */
    private $fileName;
    /**
     * @var string
     */
    private $folder;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var Yaml
     */
    private $yaml;

    /**
     * FixturesWriter constructor.
     */
    public function __construct(Filesystem $filesystem, string $folder)
    {
        $this->filesystem = $filesystem;
        $this->folder = $folder;
        if (!$this->filesystem->exists($this->folder)) {
            try {
                $this->filesystem->mkdir($this->folder);
            } catch (IOExceptionInterface $exception) {
                echo 'An error occurred while creating your directory at '.$exception->getPath();
            }
        }
        $this->setFileName(self::FILENAME);
        $this->yaml = new Yaml();
    }

    /**
     * Drop previous fixtures dump and create a new empty filename for current fixtures dump.
     */
    public function setFileName(): void
    {
        $this->fileName = $this->folder.DIRECTORY_SEPARATOR.self::FILENAME;

        if ($this->filesystem->exists($this->fileName)) {
            $this->filesystem->remove($this->fileName);
        }
    }

    /**
     * Write some text to fixture dump.
     */
    public function write(array $yaml): void
    {
        $this->filesystem->appendToFile($this->fileName, $this->yaml->dump($yaml, 3, 4, 1));
    }

    public function setFixturesDumpHeader($entityName, $targetIds)
    {
        $header = '#####################################################################################
###
### Fixtures Dump for \''.$entityName.'\' with id: \''.implode(',', $targetIds).'\'
###
### created at \''.date('Y-m-d-H-i-s').'\'
###
### @copyright Aquis Grana impex srl (http://www.webnou.ro/) ASK 4 MORE
###
#####################################################################################

'
        ;
        $this->filesystem->appendToFile($this->fileName, $header);
    }
}
