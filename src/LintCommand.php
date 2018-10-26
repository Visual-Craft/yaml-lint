<?php

namespace VisualCraft\YamlLint;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class LintCommand extends Command
{
    private $parser;
    private $format;
    private $displayCorrectFiles;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('lint:yaml')
            ->setDescription('Lints a file and outputs encountered errors')
            ->addArgument('filename', InputArgument::IS_ARRAY, 'A files or directories')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The output format', 'txt')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints a YAML file and outputs to STDOUT
the first encountered syntax error.

You can validates YAML contents passed from STDIN:

  <info>cat filename | php %command.full_name%</info>

You can also validate the syntax of a file:

  <info>php %command.full_name% filename</info>

Or of a whole directory:

  <info>php %command.full_name% dirname</info>
  <info>php %command.full_name% dirname --format=json</info>

Also you can provide multiple file names or directories:

  <info>php %command.full_name% filename1 filename2</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $filenames = $input->getArgument('filename');
        $this->format = $input->getOption('format');
        $this->displayCorrectFiles = $output->isVerbose();

        if (!$filenames) {
            if (!$stdin = $this->getStdin()) {
                throw new \RuntimeException('Please provide a filename or pipe file content to STDIN.');
            }

            return $this->display($io, array($this->validate($stdin)));
        }

        foreach ($filenames as $filename) {
            if (!is_readable($filename)) {
                throw new \RuntimeException(sprintf('File or directory "%s" is not readable.', $filename));
            }
        }

        $filesInfo = array();

        foreach ($filenames as $filename) {
            foreach ($this->getFiles($filename) as $file) {
                $filesInfo[] = $this->validate(file_get_contents($file), $file);
            }
        }

        return $this->display($io, $filesInfo);
    }

    private function validate($content, $file = null)
    {
        $prevErrorHandler = set_error_handler(function ($level, $message, $file, $line) use (&$prevErrorHandler) {
            if (E_USER_DEPRECATED === $level) {
                throw new ParseException($message);
            }

            return $prevErrorHandler ? $prevErrorHandler($level, $message, $file, $line) : false;
        });

        try {
            $this->getParser()->parse($content, 0);
        } catch (ParseException $e) {
            return array('file' => $file, 'valid' => false, 'message' => $e->getMessage());
        } finally {
            restore_error_handler();
        }

        return array('file' => $file, 'valid' => true);
    }

    private function display(SymfonyStyle $io, array $files)
    {
        switch ($this->format) {
            case 'txt':
                return $this->displayTxt($io, $files);
            case 'json':
                return $this->displayJson($io, $files);
            default:
                throw new \InvalidArgumentException(sprintf('The format "%s" is not supported.', $this->format));
        }
    }

    private function displayTxt(SymfonyStyle $io, array $filesInfo)
    {
        $countFiles = count($filesInfo);
        $erroredFiles = 0;

        foreach ($filesInfo as $info) {
            if ($info['valid']) {
                if ($this->displayCorrectFiles) {
                    $io->comment('<info>OK</info>' . ($info['file'] ? sprintf(' in %s', $info['file']) : ''));
                }
            } else {
                ++$erroredFiles;
                $io->text('<error> ERROR </error>'.($info['file'] ? sprintf(' in %s', $info['file']) : ''));
                $io->text(sprintf('<error> >> %s</error>', $info['message']));
            }
        }

        if ($erroredFiles === 0) {
            $io->success(sprintf('All %d YAML files contain valid syntax.', $countFiles));
        } else {
            $io->warning(sprintf('%d YAML files have valid syntax and %d contain errors.', $countFiles - $erroredFiles, $erroredFiles));
        }

        return min($erroredFiles, 1);
    }

    private function displayJson(SymfonyStyle $io, array $filesInfo)
    {
        $errors = 0;

        array_walk($filesInfo, function (&$v) use (&$errors) {
            $v['file'] = (string) $v['file'];
            if (!$v['valid']) {
                ++$errors;
            }
        });

        $io->writeln(json_encode($filesInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return min($errors, 1);
    }

    private function getFiles($fileOrDirectory)
    {
        if (is_file($fileOrDirectory)) {
            yield new \SplFileInfo($fileOrDirectory);
        } else {
            foreach ($this->getDirectoryIterator($fileOrDirectory) as $file) {
                if (!in_array($file->getExtension(), array('yml', 'yaml'), true)) {
                    continue;
                }

                yield $file;
            }
        }
    }

    private function getStdin()
    {
        if (0 !== ftell(STDIN)) {
            return null;
        }

        $inputs = '';
        while (!feof(STDIN)) {
            $inputs .= fread(STDIN, 1024);
        }

        return $inputs;
    }

    private function getParser()
    {
        if (!$this->parser) {
            $this->parser = new Parser();
        }

        return $this->parser;
    }

    private function getDirectoryIterator($directory)
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
    }
}
