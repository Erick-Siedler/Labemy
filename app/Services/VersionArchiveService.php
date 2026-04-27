<?php

namespace App\Services;

use App\Models\ProjectVersion;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class VersionArchiveService
{
    public function normalizeZipPath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, "\0")) {
            abort(400, 'Caminho invalido.');
        }

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
            abort(400, 'Caminho invalido.');
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($seg) => $seg !== ''));
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                abort(400, 'Caminho invalido.');
            }
        }

        return implode('/', $segments);
    }

    public function locateZipEntry(ZipArchive $zip, string $path): ?array
    {
        $tries = [$path];

        $stripped = $this->stripFirstSegment($path);
        if ($stripped && $stripped !== $path) {
            $tries[] = $stripped;
        }

        foreach ($tries as $tryPath) {
            $idx = $zip->locateName($tryPath, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
            if ($idx !== false) {
                $stat = $zip->statIndex($idx);
                return [
                    'index' => $idx,
                    'name' => $stat['name'] ?? $tryPath,
                ];
            }
        }

        $suffixes = array_map(
            static fn ($tryPath) => '/' . ltrim($tryPath, '/'),
            $tries
        );

        $candidates = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', $stat['name'] ?? '');
            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            foreach ($tries as $tryPath) {
                if (strcasecmp($name, $tryPath) === 0) {
                    return [
                        'index' => $i,
                        'name' => $stat['name'] ?? $name,
                    ];
                }
            }

            foreach ($suffixes as $suffix) {
                if (str_ends_with(strtolower($name), strtolower($suffix))) {
                    $candidates[] = $i;
                    break;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        if (count($candidates) === 1) {
            $stat = $zip->statIndex($candidates[0]);
            return [
                'index' => $candidates[0],
                'name' => $stat['name'] ?? $path,
            ];
        }

        return null;
    }

    public function openZipForVersion(ProjectVersion $version): ZipArchive
    {
        $file = $version->files()
            ->where('type', 'compressed')
            ->firstOrFail();

        $zipAbsolutePath = Storage::disk('private')->path($file->path);

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            abort(404, 'ZIP nao encontrado ou corrompido.');
        }

        return $zip;
    }

    public function buildTreeFromZip(ZipArchive $zip): array
    {
        $root = ['type' => 'dir', 'name' => '/', 'children' => []];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) {
                continue;
            }

            $name = str_replace('\\', '/', $stat['name'] ?? '');
            if ($name === '' || $name === './') {
                continue;
            }

            if (str_contains($name, "\0") || str_contains($name, '../') || str_starts_with($name, '/')) {
                continue;
            }

            $isDir = str_ends_with($name, '/');
            $parts = array_values(array_filter(explode('/', trim($name, '/'))));
            if (in_array('..', $parts, true) || in_array('.', $parts, true)) {
                continue;
            }

            $node =& $root;

            foreach ($parts as $idx => $part) {
                $isLast = $idx === count($parts) - 1;

                if (!isset($node['children'][$part])) {
                    $node['children'][$part] = [
                        'type' => ($isLast && !$isDir) ? 'file' : 'dir',
                        'name' => $part,
                        'children' => [],
                        'path' => null,
                        'size' => null,
                    ];
                }

                if ($isLast && !$isDir) {
                    $node['children'][$part]['path'] = trim($name, '/');
                    $node['children'][$part]['size'] = (int) ($stat['size'] ?? 0);
                }

                $node =& $node['children'][$part];
            }
        }

        $normalize = function (&$dir) use (&$normalize) {
            if (($dir['type'] ?? '') !== 'dir') {
                return;
            }

            $children = array_values($dir['children'] ?? []);
            foreach ($children as &$child) {
                $normalize($child);
            }

            usort($children, function ($a, $b) {
                if ($a['type'] !== $b['type']) {
                    return $a['type'] === 'dir' ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });

            $dir['children'] = $children;
        };

        $normalize($root);

        return $root;
    }

    public function readZipEntryContent(
        ZipArchive $zip,
        int $idx,
        string $entryName,
        int $maxSize,
        string $tooLargeMessage
    ): string {
        $stat = $zip->statIndex($idx);
        $size = (int) ($stat['size'] ?? 0);

        if ($size > $maxSize) {
            abort(413, $tooLargeMessage);
        }

        $resolvedEntryName = $stat['name'] ?? $entryName;
        $stream = $zip->getStream($resolvedEntryName);
        if (!$stream) {
            abort(404);
        }

        $content = stream_get_contents($stream);
        fclose($stream);

        if (!is_string($content)) {
            throw new RuntimeException('Falha ao ler conteudo do arquivo.');
        }

        return $content;
    }

    public function guessContentType(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xlsm' => 'application/vnd.ms-excel.sheet.macroEnabled.12',
            'xls' => 'application/vnd.ms-excel',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt', 'md', 'json', 'xml', 'yml', 'yaml',
            'php', 'js', 'ts', 'css', 'html', 'vue',
            'py', 'java', 'c', 'cpp', 'cs', 'go', 'rs', 'sql'
                => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }

    private function stripFirstSegment(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn ($seg) => $seg !== ''));
        if (count($segments) <= 1) {
            return null;
        }

        array_shift($segments);

        return implode('/', $segments);
    }
}
