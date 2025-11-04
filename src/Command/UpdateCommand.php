<?php
namespace App\Command;

use App\Helper;
use App\Omeka;
use GuzzleHttp\Client;
use Laminas\ServiceManager\ServiceManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    private array $coreUpdate = [];

    private array $modulesUpdate = [];

    private array $themesUpdate = [];

    protected function configure(): void
    {
        $this->setName('update');
        $this->setDescription('Update NGC Omeka S distribution.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rootDir = dirname(__DIR__, 2);
        $distFile = $rootDir . '/distribution.json';
        $configPath = $rootDir . '/config/config.json';
        $publicDir = $rootDir . '/public';

        // Read distribution.json
        if (!file_exists($distFile)) {
            $output->writeln('<error>distribution.json not found.</error>');
            return Command::FAILURE;
        }
        $manifest = json_decode(file_get_contents($distFile), true);

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

        // Audit current installation.
        $output->writeln('Checking for updates...');
        $this->audit($manifest);
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
        if (!empty($this->themesUpdate)) {
            $output->writeln('Theme updates available:');
            foreach ($this->themesUpdate as $themeID => $update) {
                $output->writeln($themeID . ': ' . $update['from'] . ' => ' . $update['to']);
            }
        } else {
            $output->writeln('All themes are up to date.');
        }

        // Download the core update.
        if (!$this->downloadCore($output, $manifest, $publicDir)) {
            return Command::FAILURE;
        }



        return Command::SUCCESS;
    }

    private function audit($manifest): void
    {
        $serviceManager = Omeka::getApp()->getServiceManager();

        // Check core update.
        $status = $serviceManager->get('Omeka\Status');
        $currentVersion = $status->getInstalledVersion();
        $latestVersion = $manifest['core']['version'];
        if (version_compare($currentVersion, $latestVersion, '<')) {
            $this->coreUpdate = [
                'from' => $currentVersion,
                'to' => $latestVersion,
            ];
        }

        // Check modules update.
        /**
         * @var \Omeka\Module\Manager $moduleManager
         */
        $moduleManager = $serviceManager->get('Omeka\ModuleManager');
        foreach ($manifest['modules'] as $moduleInfo) {
            $moduleID = $moduleInfo['name'];
            $latestVersion = $moduleInfo['version'];
            $module = $moduleManager->getModule($moduleID);
            if ($module) {
                $currentVersion = $module->getIni('version');
                if (version_compare($currentVersion, $latestVersion, '<')) {
                    $this->modulesUpdate[$moduleID] = [
                        'from' => $currentVersion,
                        'to' => $latestVersion,
                    ];
                }
            } else {
                // Module not installed.
                $this->modulesUpdate[$moduleID] = [
                    'from' => null,
                    'to' => $latestVersion,
                ];
            }
        }

        // Check themes update.
        /**
         * @var \Omeka\Site\Theme\Manager $themeManager
         */
        $themeManager = $serviceManager->get('Omeka\Site\ThemeManager');
        foreach ($manifest['themes'] as $themeInfo) {
            $themeID = $themeInfo['name'];
            $latestVersion = $themeInfo['version'];
            if ($themeManager->isRegistered($themeID)) {
                $theme = $themeManager->getTheme($themeID);
                $currentVersion = $theme->getIni('version');
                if (version_compare($currentVersion, $latestVersion, '<')) {
                    $this->themesUpdate[$themeID] = [
                        'from' => $currentVersion,
                        'to' => $latestVersion,
                    ];
                }
            } else {
                // Theme not installed.
                $this->themesUpdate[$themeID] = [
                    'from' => null,
                    'to' => $latestVersion,
                ];
            }
        }
    }

    private function downloadCore(OutputInterface $output, $manifest, $publicDir): bool
    {
        if (!empty($this->coreUpdate)) {
            $client = new Client();
            $coreUrl = $manifest['core']['url'];
            $coreVersion = $manifest['core']['version'] ?? 'unknown';
            $output->writeln("Downloading Omeka S {$coreVersion}...");
            $tmpZip = tempnam(sys_get_temp_dir(), 'omeka_core_') . '.zip';
            try {
                $client->request('GET', $coreUrl, ['sink' => $tmpZip]);
            } catch (\Exception $e) {
                $output->writeln('<error>Download failed: ' . $e->getMessage() . '</error>');
                return false;
            }
            $output->writeln('Extracting the package...');
            $keepDirs = ['config', 'files', 'modules', 'themes', 'logs'];

            // Delete everything in the public directory except the keep directories.
            $items = scandir($publicDir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (in_array($item, $keepDirs)) {
                    continue;
                }
                $fullPath = $publicDir . '/' . $item;
                if (is_dir($fullPath)) {
                    Helper::rrmdir($fullPath);
                } else {
                    unlink($fullPath);
                }
            }

            // Extract the zip to the temp directory.
            $tempDir = $publicDir . '/update_temp';
        }

        return true;
    }
}
