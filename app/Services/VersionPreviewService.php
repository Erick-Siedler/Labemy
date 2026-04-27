<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use ZipArchive;

class VersionPreviewService
{
    public function buildSpreadsheetPreview(string $ext, string $binary): array
    {
        return $ext === 'csv'
            ? $this->buildCsvPreview($binary)
            : $this->buildExcelPreview($ext, $binary);
    }

    public function buildDocxPreview(string $binary): array
    {
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
}
