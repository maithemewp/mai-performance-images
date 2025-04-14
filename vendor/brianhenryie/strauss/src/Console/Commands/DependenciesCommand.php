<?php

namespace BrianHenryIE\Strauss\Console\Commands;

use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Composer\ProjectComposerPackage;
use BrianHenryIE\Strauss\Files\DiscoveredFiles;
use BrianHenryIE\Strauss\Helpers\FileSystem;
use BrianHenryIE\Strauss\Helpers\ReadOnlyFileSystem;
use BrianHenryIE\Strauss\Pipeline\Aliases;
use BrianHenryIE\Strauss\Pipeline\Autoload;
use BrianHenryIE\Strauss\Pipeline\Autoload\VendorComposerAutoload;
use BrianHenryIE\Strauss\Pipeline\ChangeEnumerator;
use BrianHenryIE\Strauss\Pipeline\Cleanup;
use BrianHenryIE\Strauss\Pipeline\Copier;
use BrianHenryIE\Strauss\Pipeline\DependenciesEnumerator;
use BrianHenryIE\Strauss\Pipeline\FileCopyScanner;
use BrianHenryIE\Strauss\Pipeline\FileEnumerator;
use BrianHenryIE\Strauss\Pipeline\FileSymbolScanner;
use BrianHenryIE\Strauss\Pipeline\Licenser;
use BrianHenryIE\Strauss\Pipeline\Prefixer;
use BrianHenryIE\Strauss\Types\DiscoveredSymbols;
use Composer\Console\Input\InputOption;
use Composer\InstalledVersions;
use Elazar\Flystream\FilesystemRegistry;
use Elazar\Flystream\StripProtocolPathNormalizer;
use Exception;
use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\WhitespacePathNormalizer;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class DependenciesCommand extends Command
{
    use LoggerAwareTrait;

    /** @var string */
    protected string $workingDir;

    protected StraussConfig $config;

    protected ProjectComposerPackage $projectComposerPackage;

    /** @var Prefixer */
    protected Prefixer $replacer;

    protected DependenciesEnumerator $dependenciesEnumerator;

    /** @var array<string,ComposerPackage> */
    protected array $flatDependencyTree = [];

    /**
     * ArrayAccess of \BrianHenryIE\Strauss\File objects indexed by their path relative to the output target directory.
     *
     * Each object contains the file's relative and absolute paths, the package and autoloaders it came from,
     * and flags indicating should it / has it been copied / deleted etc.
     *
     */
    protected DiscoveredFiles $discoveredFiles;
    protected DiscoveredSymbols $discoveredSymbols;

    protected Filesystem $filesystem;

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dependencies');
        $this->setDescription("Copy composer's `require` and prefix their namespace and classnames.");
        $this->setHelp('');

        $this->addOption(
            'updateCallSites',
            null,
            InputArgument::OPTIONAL,
            'Should replacements also be performed in project files? true|list,of,paths|false'
        );

        $this->addOption(
            'deleteVendorPackages',
            null,
            4,
            'Should original packages be deleted after copying? true|false',
            false
        );
        // Is there a nicer way to add aliases?
        $this->addOption(
            'delete_vendor_packages',
            null,
            4,
            '',
            false
        );

        $this->addOption(
            'dry-run',
            null,
            4,
            'Do not actually make any changes',
            false
        );

        $this->addOption(
            'info',
            null,
            4,
            'output level',
            false
        );

        $this->addOption(
            'debug',
            null,
            4,
            'output level',
            false
        );

        if (version_compare(InstalledVersions::getVersion('symfony/console'), '7.2', '<')) {
            $this->addOption(
                'silent',
                's',
                4,
                'output level',
                false
            );
        }

        $localFilesystemAdapter = new LocalFilesystemAdapter(
            '/',
            null,
            LOCK_EX,
            LocalFilesystemAdapter::SKIP_LINKS
        );

        $this->filesystem = new Filesystem(
            new \League\Flysystem\Filesystem(
                $localFilesystemAdapter,
                [
                    Config::OPTION_DIRECTORY_VISIBILITY => 'public',
                ]
            ),
            getcwd() . '/'
        );
    }

    /**
     * @param InputInterface $input
     * @return array<string, int>
     */
    protected function getLogLevel(InputInterface $input): array
    {

        $logLevel = [LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL];

        if ($input->hasOption('info') && $input->getOption('info') !== false) {
            $logLevel[LogLevel::INFO]= OutputInterface::VERBOSITY_NORMAL;
        }

        if ($input->hasOption('debug') && $input->getOption('debug') !== false) {
            $logLevel[LogLevel::INFO]= OutputInterface::VERBOSITY_NORMAL;
            $logLevel[LogLevel::DEBUG]= OutputInterface::VERBOSITY_NORMAL;
        }

        if (isset($this->config) && $this->config->isDryRun()) {
            $logLevel[LogLevel::INFO] = OutputInterface::VERBOSITY_NORMAL;
            $logLevel[LogLevel::DEBUG] = OutputInterface::VERBOSITY_NORMAL;
        }

        if ($input->hasOption('silent') && $input->getOption('silent') !== false) {
            return [];
        }

        return $logLevel;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @see Command::execute()
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setLogger(new ConsoleLogger($output, $this->getLogLevel($input)));

        $workingDir       = getcwd() . '/';
        $this->workingDir = $workingDir;

        try {
            $this->logger->notice('Starting... ' /** version */); // + PHP version

            $this->loadProjectComposerPackage();
            $this->loadConfigFromComposerJson();
            $this->updateConfigFromCli($input);

            if ($this->config->isDryRun()) {
                $normalizer = new WhitespacePathNormalizer();
                $normalizer = new StripProtocolPathNormalizer(['mem'], $normalizer);

                $this->filesystem =
                    new FileSystem(
                        new ReadOnlyFileSystem(
                            $this->filesystem,
                            $normalizer
                        ),
                        $this->workingDir
                    );

                /** @var FilesystemRegistry $registry */
                $registry = \Elazar\Flystream\ServiceLocator::get(\Elazar\Flystream\FilesystemRegistry::class);

                // Register a file stream mem:// to handle file operations by third party libraries.
                // This exception handling probably doesn't matter in real life but does in unit tests.
                try {
                    $registry->get('mem');
                } catch (\Exception $e) {
                    $registry->register('mem', $this->filesystem);
                }
                $this->setLogger(new ConsoleLogger($output, $this->getLogLevel($input)));
            }
            $this->buildDependencyList();

            $this->enumerateFiles();

            $this->copyFiles();

            $this->determineChanges();

            $this->performReplacements();

            $this->performReplacementsInProjectFiles();

            $this->addLicenses();


            // After file have been deleted, we may need aliases.
            $this->generateAliasesFile();

            $this->cleanUp();

            // This runs after cleanup because cleanup edits installed.json
            $this->generateAutoloader();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 1. Load the composer.json.
     *
     * @throws Exception
     */
    protected function loadProjectComposerPackage(): void
    {
        $this->logger->notice('Loading package...');

        $this->projectComposerPackage = new ProjectComposerPackage($this->workingDir . 'composer.json');

        // TODO: Print the config that Strauss is using.
        // Maybe even highlight what is default config and what is custom config.
    }

    protected function loadConfigFromComposerJson(): void
    {
        $this->logger->notice('Loading composer.json config...');

        $this->config = $this->projectComposerPackage->getStraussConfig();
    }

    protected function updateConfigFromCli(InputInterface $input): void
    {
        $this->logger->notice('Loading cli config...');

        $this->config->updateFromCli($input);
    }

    /**
     * 2. Built flat list of packages and dependencies.
     *
     * 2.1 Initiate getting dependencies for the project composer.json.
     *
     * @see DependenciesCommand::flatDependencyTree
     */
    protected function buildDependencyList(): void
    {
        $this->logger->notice('Building dependency list...');

        $this->dependenciesEnumerator = new DependenciesEnumerator(
            $this->config,
            $this->filesystem,
            $this->logger
        );
        $this->flatDependencyTree = $this->dependenciesEnumerator->getAllDependencies();

        // TODO: Print the dependency tree that Strauss has determined.
    }

    protected function enumerateFiles(): void
    {
        $this->logger->notice('Enumerating files...');

        $fileEnumerator = new FileEnumerator(
            $this->config,
            $this->filesystem,
            $this->logger
        );

        $this->discoveredFiles = $fileEnumerator->compileFileListForDependencies($this->flatDependencyTree);
    }

    // 3. Copy autoloaded files for each
    protected function copyFiles(): void
    {
        (new FileCopyScanner($this->config, $this->filesystem, $this->logger))->scanFiles($this->discoveredFiles);

        if ($this->config->getTargetDirectory() === $this->config->getVendorDirectory()) {
            // Nothing to do.
            return;
        }

        $this->logger->notice('Copying files...');

        $copier = new Copier(
            $this->discoveredFiles,
            $this->config,
            $this->filesystem,
            $this->logger
        );


        $copier->prepareTarget();
        $copier->copy();
    }

    // 4. Determine namespace and classname changes
    protected function determineChanges(): void
    {
        $this->logger->notice('Determining changes...');

        $fileScanner = new FileSymbolScanner(
            $this->config,
            $this->filesystem,
            $this->logger
        );

        $this->discoveredSymbols = $fileScanner->findInFiles($this->discoveredFiles);

        $changeEnumerator = new ChangeEnumerator(
            $this->config,
            $this->filesystem,
            $this->logger
        );
        $changeEnumerator->determineReplacements($this->discoveredSymbols);
    }

    // 5. Update namespaces and class names.
    // Replace references to updated namespaces and classnames throughout the dependencies.
    protected function performReplacements(): void
    {
        $this->logger->notice('Performing replacements...');

        $this->replacer = new Prefixer(
            $this->config,
            $this->filesystem,
            $this->logger
        );

        $this->replacer->replaceInFiles($this->discoveredSymbols, $this->discoveredFiles->getFiles());
    }

    protected function performReplacementsInProjectFiles(): void
    {

        $relativeCallSitePaths =
            $this->config->getUpdateCallSites()
            ?? $this->projectComposerPackage->getFlatAutoloadKey();

        if (empty($relativeCallSitePaths)) {
            return;
        }

        $callSitePaths = array_map(
            fn($path) => $this->workingDir . $path,
            $relativeCallSitePaths
        );

        $projectReplace = new Prefixer(
            $this->config,
            $this->filesystem,
            $this->logger
        );

        $fileEnumerator = new FileEnumerator(
            $this->config,
            $this->filesystem
        );

        $phpFiles = $fileEnumerator->compileFileListForPaths($callSitePaths);

        $phpFilesAbsolutePaths = array_map(
            fn($file) => $file->getSourcePath(),
            $phpFiles->getFiles()
        );

        // TODO: Warn when a file that was specified is not found
        // $this->logger->warning('Expected file not found from project autoload: ' . $absolutePath);

        $projectReplace->replaceInProjectFiles($this->discoveredSymbols, $phpFilesAbsolutePaths);
    }

    protected function addLicenses(): void
    {
        $this->logger->notice('Adding licenses...');

        $author = $this->projectComposerPackage->getAuthor();

        $dependencies = $this->flatDependencyTree;

        $licenser = new Licenser(
            $this->config,
            $dependencies,
            $author,
            $this->filesystem,
            $this->logger
        );

        $licenser->copyLicenses();

        $modifiedFiles = $this->replacer->getModifiedFiles();
        $licenser->addInformationToUpdatedFiles($modifiedFiles);
    }

    /**
     * 6. Generate autoloader.
     */
    protected function generateAutoloader(): void
    {
        if ($this->config->getTargetDirectory() === $this->config->getVendorDirectory()) {
            $this->logger->notice('Skipping autoloader generation as target directory is vendor directory.');
            return;
        }
        if (isset($this->projectComposerPackage->getAutoload()['classmap'])
            && in_array(
                $this->config->getTargetDirectory(),
                $this->projectComposerPackage->getAutoload()['classmap'],
                true
            )
        ) {
            $this->logger->notice('Skipping autoloader generation as target directory is in Composer classmap. Run `composer dump-autoload`.');
            return;
        }

        $this->logger->notice('Generating autoloader...');

        $allFilesAutoloaders = $this->dependenciesEnumerator->getAllFilesAutoloaders();
        $filesAutoloaders = array();
        foreach ($allFilesAutoloaders as $packageName => $packageFilesAutoloader) {
            if (in_array($packageName, $this->config->getExcludePackagesFromCopy())) {
                continue;
            }
            $filesAutoloaders[$packageName] = $packageFilesAutoloader;
        }

        $classmap = new Autoload(
            $this->config,
            $filesAutoloaders,
            $this->filesystem,
            $this->logger
        );

        $classmap->generate($this->flatDependencyTree, $this->discoveredSymbols);
    }

    /**
     * When namespaces are prefixed which are used by both require and require-dev dependencies,
     * the require-dev dependencies need class aliases specified to point to the new class names/namespaces.
     */
    protected function generateAliasesFile(): void
    {
        if (!$this->config->isCreateAliases()) {
            return;
        }

        $this->logger->info('Generating aliases file...');

        $aliases = new Aliases(
            $this->config,
            $this->filesystem
        );
        $aliases->writeAliasesFileForSymbols($this->discoveredSymbols);

        $vendorComposerAutoload = new VendorComposerAutoload(
            $this->config,
            $this->filesystem,
            $this->logger
        );
        $vendorComposerAutoload->addAliasesFileToComposer();
        $vendorComposerAutoload->addVendorPrefixedAutoloadToVendorAutoload();
    }

    /**
     * 7.
     * Delete source files if desired.
     * Delete empty directories in destination.
     */
    protected function cleanUp(): void
    {

        $this->logger->notice('Cleaning up...');

        $cleanup = new Cleanup(
            $this->config,
            $this->filesystem,
            $this->logger
        );

        // This will check the config to check should it delete or not.
        $cleanup->cleanup($this->discoveredFiles->getFiles());
        $cleanup->cleanupVendorInstalledJson($this->flatDependencyTree, $this->discoveredSymbols);
    }
}
