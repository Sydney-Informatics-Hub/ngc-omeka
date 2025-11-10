<?php

namespace App\Command;

use App\Omeka;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateDBCommand extends Command
{
    private array $coreUpdate = [];

    private array $modulesUpdate = [];

    protected function configure(): void
    {
        $this->setName('update:db');
        $this->setDescription('Applies pending updates to the database after the code update.');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatic yes to prompts; assume "yes" as answer to all prompts and run non-interactively.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $autoConfirm = $input->getOption('yes');
        $rootDir = dirname(__DIR__, 2);
        $configPath = $rootDir . '/config/config.json';

        // Read config.json
        if (!file_exists($configPath)) {
            $output->writeln('<error>config.json not found.</error>');
            return Command::FAILURE;
        }
        $config = json_decode(file_get_contents($configPath), true);

        // Get the user credentials.
        if (!isset($config['admin']['email']) || !isset($config['admin']['password'])) {
            $output->writeln('<error>Admin credentials not found in config.json.</error>');
            return Command::FAILURE;
        }

        // Authenticate to Omeka S
        Omeka::authenticate($config['admin']['email'], $config['admin']['password']);

        $output->writeln('Checking for database updates...');
        // Audit updates
        $this->audit();
        if (!empty($this->coreUpdate)) {
            $output->writeln('Core update available: ' . $this->coreUpdate['from'] . ' => ' . $this->coreUpdate['to']);
        } else {
            $output->writeln('Core is up to date.');
        }
        if (!empty($this->modulesUpdate)) {
            $output->writeln('Module updates available:');
            foreach ($this->modulesUpdate as $moduleID => $update) {
                $output->writeln($moduleID . ': ' . $update['from'] . ' => ' . $update['to']);
            }
        } else {
            $output->writeln('All modules are up to date.');
        }
        if (empty($this->coreUpdate) && empty($this->modulesUpdate)) {
            $output->writeln('<info>No updates available. The database is up to date.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('<error>CAUTION: Updating the DB may disrupt your current installation. Before proceeding, please ensure you have a complete backup of the database.</error>');

        if (!$autoConfirm) {
            $qHelper = new QuestionHelper();
            $question = new ConfirmationQuestion('Would you like to continue? (y|n)', false);

            if (!$qHelper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }
        }

        $hasErrors = false;

        if (!$this->updateCore($output)) {
            $hasErrors = true;
        }

        if (!$this->updateModules($output)) {
            $hasErrors = true;
        }

        if ($hasErrors) {
            $output->writeln('<comment>The database has been updated with some errors. Please check the messages above.</comment>');
        } else {
            $output->writeln('<info>The database has been updated successfully.</info>');
        }

        return Command::SUCCESS;
    }

    private function audit(): void
    {
        $serviceManager = Omeka::getApp()->getServiceManager();
        // Check core update.
        /**
         * @var \Omeka\Mvc\Status $status
         */
        $status = $serviceManager->get('Omeka\Status');
        if ($status->needsVersionUpdate()) {
            $this->coreUpdate = [
                'from' => $status->getInstalledVersion(),
                'to' => $status->getVersion(),
            ];
        }

        // Check modules update.
        /**
         * @var \Omeka\Module\Manager $moduleManager
         */
        $moduleManager = $serviceManager->get('Omeka\ModuleManager');
        $modules = $moduleManager->getModules();
        /**
         * @var \Omeka\Module\Module $module
         */
        foreach ($modules as $module) {
            if ($module->getState() === \Omeka\Module\Manager::STATE_NEEDS_UPGRADE) {
                $this->modulesUpdate[$module->getId()] = [
                    'from' => $module->getDb('version'),
                    'to' => $module->getIni('version'),
                ];
            } elseif ($module->getState() === \Omeka\Module\Manager::STATE_NOT_INSTALLED) {
                $this->modulesUpdate[$module->getId()] = [
                    'from' => null,
                    'to' => $module->getIni('version'),
                ];
            }
        }

    }

    private function updateCore(OutputInterface $output): bool
    {
        if (!empty($this->coreUpdate)) {
            $output->writeln('Applying core updates...');
            $omeka = Omeka::getApp();
            $serviceManager = $omeka->getServiceManager();
            /**
             * @var \Omeka\Mvc\Status $status
             */
            $status = $serviceManager->get('Omeka\Status');
            if ($status->needsMigration()) {
                /**
                 * @var \Omeka\Db\Migration\Manager $migrationManager
                 */
                $migrationManager = $serviceManager->get('Omeka\MigrationManager');
                try {
                    $migrationManager->upgrade();
                } catch (\Exception $e) {
                    $output->writeln('<error>Migration failed: ' . $e->getMessage() . ' Please try to run the core migration through the UI.</error>');
                    return false;
                }
            }
            /**
             * @var \Omeka\Settings\Settings $settings
             */
            $settings = $serviceManager->get('Omeka\Settings');
            $settings->set('version', $status->getVersion());
            $output->writeln('<info>Core update applied successfully.</info>');
        }

        return true;
    }

    private function updateModules(OutputInterface $output): bool
    {
        $hasErrors = false;
        if (!empty($this->modulesUpdate)) {
            foreach ($this->modulesUpdate as $moduleID => $version) {
                $output->writeln("Applying update for module {$moduleID}...");
                $serviceManager = Omeka::getApp()->getServiceManager();
                /**
                 * @var \Omeka\Module\Manager $moduleManager
                 */
                $moduleManager = $serviceManager->get('Omeka\ModuleManager');
                $module = $moduleManager->getModule($moduleID);
                if (!$module) {
                    $output->writeln("<comment>Module {$moduleID} could not be found. Skip...</comment>");
                    continue;
                }
                if (empty($version['from'])) {
                    // Install the module.
                    try {
                        $moduleManager->install($module);
                    } catch (\Exception $e) {
                        $output->writeln('<error>Module installation failed: ' . $e->getMessage() . '</error>');
                        $hasErrors = true;
                        continue;
                    }
                } else {
                    // Update the module.
                    try {
                        $moduleManager->upgrade($module);
                    } catch (\Exception $e) {
                        $output->writeln('<error>Module update failed: ' . $e->getMessage() . '</error>');
                        $hasErrors = true;
                        continue;
                    }

                }
            }
            if ($hasErrors) {
                $output->writeln('<comment>Some module updates failed. Please try to manually update the module via the UI.</comment>');
            } else {
                $output->writeln('<info>All module updates applied successfully.</info>');
            }
        }
        return !$hasErrors;
    }
}
