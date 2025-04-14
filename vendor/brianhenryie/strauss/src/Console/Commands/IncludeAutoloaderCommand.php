<?php
/**
 * Adds ~`require_once 'autoload_aliases.php'` to `vendor/autoload.php`.
 *
 * During development, when running Strauss as a phar, i.e. outside Composer's autoloading, we need to ensure the
 * `autoload_aliases.php` file is loaded. This is injected into Composer's `vendor/autoload.php` when it is first
 * generated, but when `composer dump-autoload` is run, the change is lost. This command is intended to be run in
 * `post-dump-autoload` scripts in `composer.json` to ensure the aliases are loaded.
 *
 * This command DOES NOT generate the `autoload_aliases.php` files. It only inserts the `require` statement into
 * `vendor/autoload.php`.
 *
 * @package brianhenryie/strauss
 */

namespace BrianHenryIE\Strauss\Console\Commands;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\ReplaceConfigInterface;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Helpers\FileSystem;
use BrianHenryIE\Strauss\Pipeline\Autoload\VendorComposerAutoload;
use BrianHenryIE\Strauss\Pipeline\Prefixer;
use Exception;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class IncludeAutoloaderCommand extends Command
{
    use LoggerAwareTrait;

    /** @var string */
    protected string $workingDir;

    protected StraussConfig $config;

    protected Filesystem $filesystem;

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('include-autoloader');
        $this->setDescription("Adds `require autoload_aliases.php` and `require vendor-prefixed/autoload.php` to `vendor/autoload.php`.");

        // TODO: permissions?
        $this->filesystem = new Filesystem(
            new \League\Flysystem\Filesystem(new LocalFilesystemAdapter('/')),
            getcwd() . '/'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @see Command::execute()
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = new ConsoleLogger(
            $output,
            [ LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL ]
        );

        $this->setLogger($logger);

        $workingDir       = getcwd() . '/';
        $this->workingDir = $workingDir;

        try {
            $config = $this->createConfig($input);

            // Pipeline

            // TODO: check for `--no-dev` somewhere.

            $vendorComposerAutoload = new VendorComposerAutoload(
                $config,
                $this->filesystem,
                $logger
            );

            $vendorComposerAutoload->addAliasesFileToComposer();
            $vendorComposerAutoload->addVendorPrefixedAutoloadToVendorAutoload();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * TODO: This should be in a shared parent class/trait.
     */
    protected function createConfig(InputInterface $input): StraussConfig
    {
        $config = new StraussConfig();
        return $config;
    }
}
