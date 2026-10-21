<?php

namespace App\Modules\BackupRestore\Services;

use DirectoryIterator;
use Illuminate\Validation\ValidationException;
use Throwable;

class BackupFolderService
{
    public function browse(?string $path): array
    {
        $roots = $this->roots();
        if ($path === null || trim($path) === '') {
            $configured = trim((string) config('backup.root'));
            $path = is_dir($configured) ? $configured : (is_dir(dirname($configured)) && $configured !== '' ? dirname($configured) : ($roots[0]['path'] ?? ''));
        }
        $path = $this->normalize($path);
        $resolved = realpath($path);
        if ($resolved === false || ! is_dir($resolved) || ! is_readable($resolved)) {
            throw ValidationException::withMessages(['path' => 'This folder is unavailable or cannot be read. Choose another folder or drive.']);
        }
        $folders = [];
        try {
            foreach (new DirectoryIterator($resolved) as $item) {
                if ($item->isDot() || $item->isLink() || str_starts_with($item->getFilename(), '.') || ! $item->isDir()) {
                    continue;
                }
                $folders[] = ['name' => $item->getFilename(), 'path' => $item->getPathname()];
            }
        } catch (Throwable) {
            throw ValidationException::withMessages(['path' => 'This folder cannot be opened. Choose another folder.']);
        }
        usort($folders, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $parent = dirname($resolved);

        return [
            'path' => $resolved, 'parent' => $parent !== $resolved ? $parent : null,
            'roots' => $roots, 'folders' => array_slice($folders, 0, 300), 'truncated' => count($folders) > 300,
            'selectable' => app(BackupService::class)->isSafeDestination($resolved) && is_writable($resolved),
        ];
    }

    public function destination(string $path): string
    {
        $path = $this->normalize($path);
        if (! app(BackupService::class)->isSafeDestination($path)) {
            throw ValidationException::withMessages(['root' => 'Choose an absolute private backup folder outside the website and application folders.']);
        }
        if (file_exists($path) && ! is_dir($path)) {
            throw ValidationException::withMessages(['root' => 'The backup destination must be a folder, not a file.']);
        }
        $writable = is_dir($path) ? $path : dirname($path);
        if (! is_dir($writable) || ! is_writable($writable)) {
            throw ValidationException::withMessages(['root' => 'The application cannot write to this folder. Choose a writable folder or an existing parent for a new subfolder.']);
        }

        return realpath($path) ?: realpath(dirname($path)).DIRECTORY_SEPARATOR.basename($path);
    }

    private function normalize(string $path): string
    {
        $path = trim(trim($path), '"');
        $portable = str_replace(chr(92), '/', $path);
        $absolute = chr(92) === DIRECTORY_SEPARATOR
            ? preg_match('~^(?:[A-Za-z]:/|//[^/]+/[^/]+(?:/|$))~', $portable) === 1
            : str_starts_with($portable, '/');
        if ($path === '' || preg_match('/[\x00-\x1f\x7f]/', $path) || ! $absolute) {
            throw ValidationException::withMessages(['root' => 'Enter an absolute folder path, for example D:\\NilambaraBackups.']);
        }

        if (chr(92) === DIRECTORY_SEPARATOR) {
            $withoutDrive = preg_replace('~^[A-Za-z]:~', '', $portable);
            foreach (explode('/', $withoutDrive) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..') {
                    continue;
                }
                if (preg_match('~[<>:"|?*]~', $segment) || preg_match('~[. ]$~', $segment)
                    || preg_match('~^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\\.|$)~i', $segment)) {
                    throw ValidationException::withMessages(['root' => 'Use a valid Windows folder name without reserved device names, symbols or trailing dots/spaces.']);
                }
            }
        }

        return $path;
    }

    private function roots(): array
    {
        $roots = [];
        if (DIRECTORY_SEPARATOR === '\\') {
            foreach (range('A', 'Z') as $drive) {
                $path = $drive.':\\';
                if (is_dir($path)) {
                    $roots[] = ['name' => $drive.': drive', 'path' => $path];
                }
            }
        } else {
            $roots[] = ['name' => 'Computer', 'path' => '/'];
        }
        $home = getenv('USERPROFILE') ?: getenv('HOME');
        if (is_string($home) && is_dir($home)) {
            array_unshift($roots, ['name' => 'Home folder', 'path' => $home]);
        }

        return $roots;
    }
}
