<?php

namespace App;

class Helper
{
    /**
     * Extract a zip file to a destination.
     *
     * @param string $file Path to the zip file.
     * @param string $destination Path to extract the zip file to.
     *
     * @throws \RuntimeException if extraction fails.
     */
    public static function extractZip($file, $destination): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Failed to open zip file.');
        }
        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract zip file.');
        }
        $zip->close();
    }

    /**
     * Extract a zip file and move its top-level directory to the destination.
     *
     * @param string $file Path to the zip file.
     * @param string $destination Path to move the extracted top-level directory to.
     *
     * @throws \RuntimeException if extraction or renaming fails.
     */
    public static function extractZipTopLevelDir($file, $destination): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            throw new \RuntimeException('Failed to open zip file.');
        }

        // Find top-level directory
        $topDir = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];
            $parts = explode('/', $name);
            if (count($parts) > 1) {
                $topDir = $parts[0];
                break;
            }
        }
        if (!$topDir) {
            $zip->close();
            throw new \RuntimeException('Could not determine top-level directory in zip.');
        }

        // Extract to parent of destination
        $parentDir = dirname($destination);
        if (!$zip->extractTo($parentDir)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract zip file.');
        }
        $zip->close();

        // Rename extracted top-level directory to destination
        $extractedDir = $parentDir . DIRECTORY_SEPARATOR . $topDir;
        if (!is_dir($extractedDir)) {
            throw new \RuntimeException('Extracted directory not found.');
        }
        if (is_dir($destination)) {
            self::rrmdir($destination);
        }
        if (!rename($extractedDir, $destination)) {
            throw new \RuntimeException('Failed to rename extracted directory.');
        }
    }

    /**
     * Recursively remove a directory.
     */
    public static function rrmdir($dir): void
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object == '.' || $object == '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                self::rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

}
