<?php

namespace App\Http\Controllers;

use App\Models\ProjectVersion;
use App\Models\Tenant;
use App\Services\HomeOwnerDataService;
use App\Services\SubHomeDataService;
use App\Services\TenantContextService;
use App\Services\UserUiPreferencesService;
use App\Services\VersionArchiveService;
use App\Services\VersionPreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use ZipArchive;

class VersionBrowserController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index(int $version, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData)
    {
        $tenant = $this->resolveTenantFromAuth();

        $versionModel = ProjectVersion::where('tenant_id', $tenant->id)
            ->where('id', $version)
            ->firstOrFail();

        $zip = $this->openZipForVersion($versionModel);
        $tree = $this->buildTreeFromZip($zip);
        $zip->close();

        [$layoutData, $theme] = $this->buildLayoutData($homeOwnerData, $subHomeData, $tenant);

        return view('main.project.version-files', array_merge($layoutData, [
            'theme' => $theme,
            'version' => $versionModel,
            'tree' => $tree,
            'pageTitle' => 'Arquivos da versão',
            'pageBreadcrumbHome' => 'Início',
            'pageBreadcrumbCurrent' => 'Arquivos',
        ]));
    }

    /**
     * Executa a rotina 'view' no fluxo de negocio.
     */
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
        $spreadsheetExts = ['csv', 'xlsx', 'xlsm', 'xls'];
        $textExts = [
            'txt', 'md', 'json', 'xml', 'yml', 'yaml',
            'php', 'js', 'ts', 'css', 'html', 'vue',
            'py', 'java', 'c', 'cpp', 'cs', 'go', 'rs',
            'sql',
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

        if (in_array($ext, $spreadsheetExts, true)) {
            try {
                $binary = $this->readZipEntryContent(
                    $zip,
                    $idx,
                    $entryName,
                    10 * 1024 * 1024,
                    'Arquivo grande demais para visualizar como planilha.'
                );
            } finally {
                $zip->close();
            }

            try {
                $preview = $this->buildSpreadsheetPreview($ext, $binary);
            } catch (Throwable) {
                return view('main.project.version-view-text', [
                    'version' => $versionModel,
                    'path' => $path,
                    'mode' => 'unsupported',
                    'content' => '',
                    'ext' => $ext,
                    'message' => 'Nao foi possivel gerar a pre-visualizacao da planilha.',
                ]);
            }

            return view('main.project.version-view-spreadsheet', [
                'version' => $versionModel,
                'path' => $path,
                'ext' => $ext,
                'sheetPreviews' => $preview['sheets'],
                'truncated' => $preview['truncated'],
            ]);
        }

        if ($ext === 'docx') {
            try {
                $binary = $this->readZipEntryContent(
                    $zip,
                    $idx,
                    $entryName,
                    10 * 1024 * 1024,
                    'Arquivo grande demais para visualizar.'
                );
            } finally {
                $zip->close();
            }

            try {
                $preview = $this->buildDocxPreview($binary);
            } catch (Throwable) {
                return view('main.project.version-view-text', [
                    'version' => $versionModel,
                    'path' => $path,
                    'mode' => 'unsupported',
                    'content' => '',
                    'ext' => $ext,
                    'message' => 'Nao foi possivel gerar a pre-visualizacao do DOCX.',
                ]);
            }

            return view('main.project.version-view-docx', [
                'version' => $versionModel,
                'path' => $path,
                'ext' => $ext,
                'paragraphs' => $preview['paragraphs'],
                'truncated' => $preview['truncated'],
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

        try {
            $content = $this->readZipEntryContent(
                $zip,
                $idx,
                $entryName,
                2 * 1024 * 1024,
                'Arquivo grande demais para visualizar em texto.'
            );
        } finally {
            $zip->close();
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $mode = 'code';

        return view('main.project.version-view-text', [
            'version' => $versionModel,
            'path' => $path,
            'mode' => $mode,
            'content' => $content,
            'ext' => $ext,
        ]);
    }

    /**
     * Executa a rotina 'raw' no fluxo de negocio.
     */
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

    /**
     * Executa a rotina 'resolveTenantFromAuth' no fluxo de negocio.
     */
    private function resolveTenantFromAuth(): Tenant
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        return app(TenantContextService::class)->resolveTenantFromSession($user, true);
    }

    /**
     * Executa a rotina 'buildLayoutData' no fluxo de negocio.
     */
    private function buildLayoutData(HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData, Tenant $tenant): array
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $tenantContext = app(TenantContextService::class);
        $isOwnerContext = $tenantContext->isOwnerContext($user, $tenant);

        if (!$isOwnerContext) {
            $role = $tenantContext->resolveRoleInTenant($user, $tenant);

            $data = $role === 'student'
                ? $subHomeData->buildStudent($user)
                : (in_array($role, ['assistant'], true)
                    ? $subHomeData->buildAssistant($user)
                    : $subHomeData->buildTeacher($user));

            if (!in_array($role, ['student', 'assistant', 'teacher'], true)) {
                $data = [
                    'groups' => collect(),
                    'labs' => collect(),
                    'notifications' => collect(),
                    'userPreferences' => null,
                ];
            }

            $data['groups'] = $data['groups'] ?? collect();
            $data['labs'] = $data['labs'] ?? collect();
            $data['notifications'] = $data['notifications'] ?? collect();

            $theme = $this->resolveTheme($data['userPreferences'] ?? null);

            return [$data + ['user' => $user], $theme];
        }

        $isSoloOwnerContext = (int) $tenant->creator_id === (int) $user->id
            && (string) ($tenant->plan ?? '') === 'solo';

        $data = $isSoloOwnerContext
            ? $homeOwnerData->buildSolo($user)
            : $homeOwnerData->build($user);
        $theme = $this->resolveTheme($data['userPreferences'] ?? null);

        return [$data, $theme];
    }

    /**
     * Executa a rotina 'resolveTheme' no fluxo de negocio.
     */
    private function resolveTheme($userPreferences): string
    {
        return app(UserUiPreferencesService::class)->resolveTheme($userPreferences);
    }

    /**
     * Executa a rotina 'normalizeZipPath' no fluxo de negocio.
     */
    private function normalizeZipPath(string $path): string
    {
        return app(VersionArchiveService::class)->normalizeZipPath($path);

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

    /**
     * Executa a rotina 'locateZipEntry' no fluxo de negocio.
     */
    private function locateZipEntry(ZipArchive $zip, string $path): ?array
    {
        return app(VersionArchiveService::class)->locateZipEntry($zip, $path);

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

    /**
     * Executa a rotina 'stripFirstSegment' no fluxo de negocio.
     */
    private function stripFirstSegment(string $path): ?string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn ($seg) => $seg !== ''));
        if (count($segments) <= 1) {
            return null;
        }

        array_shift($segments);

        return implode('/', $segments);
    }

    /**
     * Executa a rotina 'openZipForVersion' no fluxo de negocio.
     */
    private function openZipForVersion(ProjectVersion $version): ZipArchive
    {
        return app(VersionArchiveService::class)->openZipForVersion($version);

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

    /**
     * Executa a rotina 'buildTreeFromZip' no fluxo de negocio.
     */
    private function buildTreeFromZip(ZipArchive $zip): array
    {
        return app(VersionArchiveService::class)->buildTreeFromZip($zip);

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

    /**
     * Executa a rotina 'readZipEntryContent' no fluxo de negocio.
     */
    private function readZipEntryContent(
        ZipArchive $zip,
        int $idx,
        string $entryName,
        int $maxSize,
        string $tooLargeMessage
    ): string {
        return app(VersionArchiveService::class)->readZipEntryContent($zip, $idx, $entryName, $maxSize, $tooLargeMessage);

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

    /**
     * Executa a rotina 'buildSpreadsheetPreview' no fluxo de negocio.
     */
    private function buildSpreadsheetPreview(string $ext, string $binary): array
    {
        return app(VersionPreviewService::class)->buildSpreadsheetPreview($ext, $binary);

        return $ext === 'csv'
            ? $this->buildCsvPreview($binary)
            : $this->buildExcelPreview($ext, $binary);
    }

    /**
     * Executa a rotina 'buildCsvPreview' no fluxo de negocio.
     */
    private function buildCsvPreview(string $content): array
    {
        $maxRows = 200;
        $maxCols = 30;
        $maxCellChars = 700;
        $truncated = false;
        $rows = [];
        $totalRows = 0;
        $totalCols = 0;

        $normalized = str_replace("\r\n", "\n", $content);
        $normalized = str_replace("\r", "\n", $normalized);

        $samples = array_values(array_filter(
            explode("\n", $normalized),
            static fn ($line) => trim($line) !== ''
        ));
        $samples = array_slice($samples, 0, 5);

        $delimiter = $this->detectCsvDelimiter($samples);

        $stream = fopen('php://temp', 'r+');
        if (!$stream) {
            throw new RuntimeException('Nao foi possivel abrir stream temporario.');
        }

        fwrite($stream, $normalized);
        rewind($stream);

        try {
            while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
                $totalRows++;
                $columnCount = count($row);
                $totalCols = max($totalCols, $columnCount);

                if ($columnCount > $maxCols) {
                    $truncated = true;
                }

                if ($totalRows > $maxRows) {
                    $truncated = true;
                    break;
                }

                $displayRow = array_slice($row, 0, $maxCols);
                $displayRow = array_map(function ($value) use ($maxCellChars, &$truncated) {
                    $text = (string) ($value ?? '');
                    return $this->truncatePreviewText($text, $maxCellChars, $truncated);
                }, $displayRow);

                $rows[] = $displayRow;
            }
        } finally {
            fclose($stream);
        }

        $displayCols = min(max($totalCols, 1), $maxCols);
        foreach ($rows as &$row) {
            while (count($row) < $displayCols) {
                $row[] = '';
            }
        }
        unset($row);

        return [
            'sheets' => [[
                'name' => 'CSV',
                'rows' => $rows,
                'displayRows' => count($rows),
                'displayCols' => $displayCols,
                'totalRows' => $totalRows,
                'totalCols' => $totalCols,
            ]],
            'truncated' => $truncated,
        ];
    }

    /**
     * Executa a rotina 'detectCsvDelimiter' no fluxo de negocio.
     */
    private function detectCsvDelimiter(array $samples): string
    {
        $candidates = [',', ';', "\t", '|'];
        $selected = ',';
        $bestScore = 0;

        foreach ($candidates as $delimiter) {
            $score = 0.0;
            foreach ($samples as $sample) {
                $columns = str_getcsv($sample, $delimiter);
                $score += count($columns);
            }

            $average = count($samples) > 0 ? ($score / count($samples)) : 0;
            if ($average > $bestScore) {
                $bestScore = $average;
                $selected = $delimiter;
            }
        }

        return $selected;
    }

    /**
     * Executa a rotina 'buildExcelPreview' no fluxo de negocio.
     */
    private function buildExcelPreview(string $ext, string $binary): array
    {
        $maxSheets = 5;
        $maxRows = 200;
        $maxCols = 30;
        $maxCellChars = 700;
        $truncated = false;
        $sheetPreviews = [];
        $filePath = $this->storePreviewTempFile($ext, $binary);
        $spreadsheet = null;

        try {
            $reader = IOFactory::createReaderForFile($filePath);
            if (method_exists($reader, 'setReadDataOnly')) {
                $reader->setReadDataOnly(true);
            }

            $spreadsheet = $reader->load($filePath);
            $sheetCount = $spreadsheet->getSheetCount();
            if ($sheetCount > $maxSheets) {
                $truncated = true;
            }

            foreach ($spreadsheet->getWorksheetIterator() as $sheetIndex => $worksheet) {
                if ($sheetIndex >= $maxSheets) {
                    break;
                }

                $highestRow = (int) $worksheet->getHighestDataRow();
                $highestCol = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn() ?: 'A');

                if ($highestRow < 1 || $highestCol < 1) {
                    $sheetPreviews[] = [
                        'name' => $worksheet->getTitle(),
                        'rows' => [],
                        'displayRows' => 0,
                        'displayCols' => 1,
                        'totalRows' => 0,
                        'totalCols' => 0,
                    ];
                    continue;
                }

                if ($highestRow > $maxRows || $highestCol > $maxCols) {
                    $truncated = true;
                }

                $displayRows = min($highestRow, $maxRows);
                $displayCols = min($highestCol, $maxCols);
                $rows = [];

                for ($rowIndex = 1; $rowIndex <= $displayRows; $rowIndex++) {
                    $row = [];
                    for ($colIndex = 1; $colIndex <= $displayCols; $colIndex++) {
                        $coordinate = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                        $value = $worksheet->getCell($coordinate)->getFormattedValue();

                        if (is_bool($value)) {
                            $value = $value ? 'TRUE' : 'FALSE';
                        } elseif (is_scalar($value) || $value === null) {
                            $value = (string) ($value ?? '');
                        } else {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
                        }

                        $row[] = $this->truncatePreviewText($value, $maxCellChars, $truncated);
                    }
                    $rows[] = $row;
                }

                $sheetPreviews[] = [
                    'name' => $worksheet->getTitle(),
                    'rows' => $rows,
                    'displayRows' => $displayRows,
                    'displayCols' => $displayCols,
                    'totalRows' => $highestRow,
                    'totalCols' => $highestCol,
                ];
            }
        } finally {
            if ($spreadsheet) {
                $spreadsheet->disconnectWorksheets();
            }
            @unlink($filePath);
        }

        return [
            'sheets' => $sheetPreviews,
            'truncated' => $truncated,
        ];
    }

    /**
     * Executa a rotina 'buildDocxPreview' no fluxo de negocio.
     */
    private function buildDocxPreview(string $binary): array
    {
        return app(VersionPreviewService::class)->buildDocxPreview($binary);

        $maxParagraphs = 500;
        $maxParagraphChars = 2500;
        $truncated = false;
        $paragraphs = [];
        $filePath = $this->storePreviewTempFile('docx', $binary);
        $docZip = new ZipArchive();
        $zipOpened = false;

        try {
            if ($docZip->open($filePath) !== true) {
                throw new RuntimeException('Nao foi possivel abrir DOCX.');
            }
            $zipOpened = true;

            $documentXml = $docZip->getFromName('word/document.xml');
            if (!is_string($documentXml) || trim($documentXml) === '') {
                return ['paragraphs' => [], 'truncated' => false];
            }

            $dom = new DOMDocument();
            if (!@$dom->loadXML($documentXml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                throw new RuntimeException('Nao foi possivel interpretar XML do DOCX.');
            }

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $nodes = $xpath->query('//w:body//w:p');
            if (!$nodes) {
                return ['paragraphs' => [], 'truncated' => false];
            }

            foreach ($nodes as $paragraphNode) {
                if (count($paragraphs) >= $maxParagraphs) {
                    $truncated = true;
                    break;
                }

                $runs = $xpath->query('.//w:t|.//w:tab|.//w:br|.//w:cr', $paragraphNode);
                if (!$runs) {
                    continue;
                }

                $text = '';
                foreach ($runs as $run) {
                    $nodeName = $run->localName;
                    if ($nodeName === 't') {
                        $text .= $run->textContent;
                    } elseif ($nodeName === 'tab') {
                        $text .= "\t";
                    } elseif ($nodeName === 'br' || $nodeName === 'cr') {
                        $text .= "\n";
                    }
                }

                $text = trim($text);
                if ($text === '') {
                    continue;
                }

                $paragraphs[] = $this->truncatePreviewText($text, $maxParagraphChars, $truncated);
            }
        } finally {
            if ($zipOpened) {
                $docZip->close();
            }
            @unlink($filePath);
        }

        return [
            'paragraphs' => $paragraphs,
            'truncated' => $truncated,
        ];
    }

    /**
     * Executa a rotina 'storePreviewTempFile' no fluxo de negocio.
     */
    private function storePreviewTempFile(string $ext, string $binary): string
    {
        $filePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'version-preview-' . uniqid('', true) . '.' . $ext;

        $written = @file_put_contents($filePath, $binary);
        if (!is_int($written) || $written <= 0) {
            throw new RuntimeException('Nao foi possivel gerar arquivo temporario para preview.');
        }

        return $filePath;
    }

    /**
     * Executa a rotina 'truncatePreviewText' no fluxo de negocio.
     */
    private function truncatePreviewText(string $text, int $maxChars, bool &$truncated): string
    {
        $strlen = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($strlen <= $maxChars) {
            return $text;
        }

        $truncated = true;
        $slice = function_exists('mb_substr') ? mb_substr($text, 0, $maxChars) : substr($text, 0, $maxChars);
        return $slice . '...';
    }

    /**
     * Executa a rotina 'guessContentType' no fluxo de negocio.
     */
    private function guessContentType(string $ext): string
    {
        return app(VersionArchiveService::class)->guessContentType($ext);

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
}
