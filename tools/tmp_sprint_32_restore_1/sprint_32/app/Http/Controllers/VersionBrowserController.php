<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Models\ProjectVersion;
use App\Models\Tenant;
use App\Services\HomeOwnerDataService;
use App\Services\SubHomeDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class VersionBrowserController extends Controller
{
    public function index(int $version, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData)
    {
        $tenant = $this->resolveTenantFromAuth();

        $versionModel = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('id', $version)
            ->firstOrFail();

        $zip = $this->openZipForVersion($versionModel);
        $tree = $this->buildTreeFromZip($zip);
        $zip->close();

        [$layoutData, $theme] = $this->buildLayoutData($homeOwnerData, $subHomeData);

        return view('main.project.version-files', array_merge($layoutData, [
            'theme' => $theme,
            'version' => $versionModel,
            'tree' => $tree,
            'pageTitle' => 'Arquivos da versão',
            'pageBreadcrumbHome' => 'Início',
            'pageBreadcrumbCurrent' => 'Arquivos',
        ]));
    }

    public function view(Request $request, int $version)
    {
        $tenant = $this->resolveTenantFromAuth();

        $versionModel = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('id', $version)
            ->firstOrFail();

        $path = $this->normalizeZipPath((string) $request->query('path'));

        $zip = $this->openZipForVersion($versionModel);
        $entry = $this->locateZipEntry($zip, $path);
        if (!$entry) {
            $zip->close();
            abort(404, 'Arquivo não encontrado no ZIP.');
        }

        $idx = $entry['index'];
        $entryName = $entry['name'] ?? $path;
        $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

        $imageExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        $textExts = [
            'txt', 'md', 'json', 'xml', 'yml', 'yaml',
            'php', 'js', 'ts', 'css', 'html', 'vue',
            'py', 'java', 'c', 'cpp', 'cs', 'go', 'rs',
            'sql', 'csv',
        ];

        $isImage = in_array($ext, $imageExts, true);
        $isPdf = $ext === 'pdf';

        if ($isImage || $isPdf) {
            $zip->close();
            return view('main.project.version-view-binary', [
                'version' => $versionModel,
                'path' => $path,
                'isImage' => $isImage,
                'isPdf' => $isPdf,
            ]);
        }

        if (!in_array($ext, $textExts, true)) {
            $zip->close();
            return view('main.project.version-view-text', [
                'version' => $versionModel,
                'path' => $path,
                'mode' => 'unsupported',
                'content' => '',
                'ext' => $ext,
            ]);
        }

        $stat = $zip->statIndex($idx);
        $size = (int) ($stat['size'] ?? 0);

        if ($size > 2 * 1024 * 1024) {
            $zip->close();
            abort(413, 'Arquivo grande demais para visualizar em texto.');
        }

        $entryName = $stat['name'] ?? $entryName;
        $stream = $zip->getStream($entryName);
        if (!$stream) {
            $zip->close();
            abort(404);
        }

        $content = stream_get_contents($stream);
        fclose($stream);
        $zip->close();

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $mode = $ext === 'csv' ? 'csv' : 'code';

        return view('main.project.version-view-text', [
            'version' => $versionModel,
            'path' => $path,
            'mode' => $mode,
            'content' => $content,
            'ext' => $ext,
        ]);
    }

    public function raw(Request $request, int $version)
    {
        $tenant = $this->resolveTenantFromAuth();

        $versionModel = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('id', $version)
            ->firstOrFail();

        $path = $this->normalizeZipPath((string) $request->query('path'));

        $zip = $this->openZipForVersion($versionModel);
        $entry = $this->locateZipEntry($zip, $path);
        if (!$entry) {
            $zip->close();
            abort(404, 'Arquivo não encontrado no ZIP.');
        }

        $idx = $entry['index'];
        $entryName = $entry['name'] ?? $path;

        $stat = $zip->statIndex($idx);
        $size = (int) ($stat['size'] ?? 0);

        if ($size > 200 * 1024 * 1024) {
            $zip->close();
            abort(413, 'Arquivo grande demais para abrir.');
        }

        $entryName = $stat['name'] ?? $entryName;
        $stream = $zip->getStream($entryName);
        if (!$stream) {
            $zip->close();
            abort(404);
        }

        $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
        $contentType = $this->guessContentType($ext);
        $download = $request->boolean('download');

        $headers = [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($download) {
            $headers['Content-Disposition'] = 'attachment; filename="' . basename($path) . '"';
        }

        return response()->stream(function () use ($stream, $zip) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
            $zip->close();
        }, 200, $headers);
    }

    private function resolveTenantFromAuth(): Tenant
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            return Tenant::where('id', $tenantId)->firstOrFail();
        }

        return Tenant::where('creator_id', Auth::id())->firstOrFail();
    }

    private function buildLayoutData(HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData): array
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            $data = $subUser->role === 'student'
                ? $subHomeData->buildStudent($subUser)
                : [
                    'groups' => collect(),
                    'labs' => collect(),
                    'notifications' => collect(),
                    'userPreferences' => null,
                ];

            $data['groups'] = $data['groups'] ?? collect();
            $data['labs'] = $data['labs'] ?? collect();
            $data['notifications'] = $data['notifications'] ?? collect();

            $theme = $this->resolveTheme($data['userPreferences'] ?? null);

            return [$data + ['user' => $subUser], $theme];
        }

        $user = Auth::user();
        $data = $user?->plan === 'solo'
            ? $homeOwnerData->buildSolo($user)
            : $homeOwnerData->build($user);
        $theme = $this->resolveTheme($data['userPreferences'] ?? null);

        return [$data, $theme];
    }

    private function resolveTheme($userPreferences): string
    {
        $preferences = json_encode($userPreferences, true);
        $preferences = explode('{', $preferences)[1] ?? '';
        $preferences = explode('}', $preferences)[0] ?? '';
        $theme = explode(',', $preferences)[0] ?? '';
        $theme = explode(':', $theme)[1] ?? '"light"';

        return $theme ?: '"light"';
    }

    private function normalizeZipPath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, "\0")) {
            abort(400, 'Caminho inválido.');
        }

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
            abort(400, 'Caminho inválido.');
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($seg) => $seg !== ''));
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                abort(400, 'Caminho inválido.');
            }
        }

        return implode('/', $segments);
    }

    private function locateZipEntry(ZipArchive $zip, string $path): ?array
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

    private function stripFirstSegment(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn ($seg) => $seg !== ''));
        if (count($segments) <= 1) {
            return null;
        }

        array_shift($segments);

        return implode('/', $segments);
    }

    private function openZipForVersion(ProjectVersion $version): ZipArchive
    {
        $file = $version->files()
            ->where('type', 'compressed')
            ->firstOrFail();

        $zipAbsolutePath = Storage::disk('private')->path($file->path);

        $zip = new ZipArchive();
        if ($zip->open($zipAbsolutePath) !== true) {
            abort(404, 'ZIP não encontrado ou corrompido.');
        }

        return $zip;
    }

    private function buildTreeFromZip(ZipArchive $zip): array
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

    private function guessContentType(string $ext): string
    {
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=utf-8',
            'txt', 'md', 'json', 'xml', 'yml', 'yaml',
            'php', 'js', 'ts', 'css', 'html', 'vue',
            'py', 'java', 'c', 'cpp', 'cs', 'go', 'rs', 'sql'
                => 'text/plain; charset=utf-8',
            default => 'application/octet-stream',
        };
    }
}
