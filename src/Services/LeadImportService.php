<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class LeadImportService
{
    private const HEADER = [
        'external_id', 'created_at', 'first_name', 'last_name', 'phone',
        'email', 'city', 'source', 'utm_campaign', 'product', 'budget_uah',
        'status', 'manager', 'comment', 'next_contact_at',
    ];
    private const MAX_FILE_SIZE = 100_000_000;
    private const MAX_CHUNK_SIZE = 1_500_000;
    private const ROWS_PER_REQUEST = 1000;

    public function __construct(private PDO $pdo, private string $storagePath)
    {
        if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
            throw new RuntimeException('Не удалось создать каталог загрузок');
        }
    }

    public function start(string $filename, int $size): array
    {
        if ($size < 1 || $size > self::MAX_FILE_SIZE || !str_ends_with(strtolower($filename), '.csv')) {
            throw new InvalidArgumentException('Выберите CSV-файл размером до 100 МБ');
        }

        $id = bin2hex(random_bytes(16));
        $path = $this->path($id);
        $handle = fopen($path, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Не удалось создать файл загрузки');
        }
        fclose($handle);

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO lead_imports (id, filename, file_size, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            );
            $statement->execute([$id, mb_substr(basename($filename), 0, 255), $size, 'uploading']);
        } catch (\Throwable $exception) {
            unlink($path);
            throw $exception;
        }

        return ['id' => $id, 'uploaded_bytes' => 0, 'status' => 'uploading'];
    }

    public function uploadChunk(string $id, int $offset, string $bytes): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_CHUNK_SIZE || $offset < 0) {
            throw new InvalidArgumentException('Некорректный фрагмент файла');
        }

        $this->pdo->beginTransaction();
        try {
            $job = $this->lockedJob($id);
            if ($job['status'] !== 'uploading' || $offset !== (int) $job['uploaded_bytes']
                || $offset + strlen($bytes) > (int) $job['file_size']) {
                throw new InvalidArgumentException('Неверный порядок загрузки файла');
            }

            $stream = fopen($this->path($id), 'c+b');
            if ($stream === false) {
                throw new RuntimeException('Файл загрузки недоступен');
            }
            try {
                ftruncate($stream, $offset);
                fseek($stream, $offset);
                $written = 0;
                while ($written < strlen($bytes)) {
                    $part = fwrite($stream, substr($bytes, $written));
                    if ($part === false || $part === 0) {
                        throw new RuntimeException('Не удалось записать файл');
                    }
                    $written += $part;
                }
            } finally {
                fclose($stream);
            }

            $uploaded = $offset + strlen($bytes);
            $this->pdo->prepare('UPDATE lead_imports SET uploaded_bytes = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$uploaded, $id]);
            $this->pdo->commit();
            return ['id' => $id, 'uploaded_bytes' => $uploaded, 'status' => 'uploading'];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function finishUpload(string $id): array
    {
        $this->pdo->beginTransaction();
        try {
            $job = $this->lockedJob($id);
            if ($job['status'] !== 'uploading' || (int) $job['uploaded_bytes'] !== (int) $job['file_size']) {
                throw new InvalidArgumentException('Файл загружен не полностью');
            }
            $stream = fopen($this->path($id), 'rb');
            if ($stream === false) {
                throw new RuntimeException('Файл загрузки недоступен');
            }
            try {
                $header = fgetcsv($stream, 0, ',', '"', '');
                if (!is_array($header)) {
                    throw new InvalidArgumentException('CSV-файл пуст');
                }
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                if ($header !== self::HEADER) {
                    throw new InvalidArgumentException('Заголовки CSV не совпадают с ожидаемыми 15 полями');
                }
                $offset = ftell($stream);
            } finally {
                fclose($stream);
            }

            $this->pdo->prepare('UPDATE lead_imports SET byte_offset = ?, status = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$offset, 'ready', $id]);
            $this->pdo->commit();
            return ['id' => $id, 'status' => 'ready', 'imported_rows' => 0];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function process(string $id): array
    {
        $this->pdo->beginTransaction();
        try {
            $job = $this->lockedJob($id);
            if ($job['status'] === 'completed') {
                $this->pdo->commit();
                return $this->progress($job);
            }
            if (!in_array($job['status'], ['ready', 'processing'], true)) {
                throw new InvalidArgumentException('Импорт ещё не готов');
            }

            $stream = fopen($this->path($id), 'rb');
            if ($stream === false || fseek($stream, (int) $job['byte_offset']) !== 0) {
                throw new RuntimeException('Файл импорта недоступен');
            }
            $rows = [];
            try {
                while (count($rows) < self::ROWS_PER_REQUEST) {
                    $fields = fgetcsv($stream, 0, ',', '"', '');
                    if ($fields === false) {
                        break;
                    }
                    if (count($fields) === 1 && $fields[0] === null) {
                        continue;
                    }
                    $rows[] = $this->normalizeRow($fields, (int) $job['imported_rows'] + count($rows) + 2);
                }
                $nextOffset = ftell($stream);
                $completed = feof($stream);
            } finally {
                fclose($stream);
            }

            if ($rows !== []) {
                $this->insertRows($id, (int) $job['imported_rows'], $rows);
            }
            $count = (int) $job['imported_rows'] + count($rows);
            $status = $completed ? 'completed' : 'processing';
            $this->pdo->prepare(
                'UPDATE lead_imports SET byte_offset = ?, imported_rows = ?, status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([$nextOffset, $count, $status, $id]);
            $this->pdo->commit();

            if ($completed) {
                @unlink($this->path($id));
            }
            return [
                'id' => $id,
                'status' => $status,
                'imported_rows' => $count,
                'progress_percent' => $completed ? 100 : min(99, (int) round($nextOffset / (int) $job['file_size'] * 100)),
            ];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function insertRows(string $id, int $previousCount, array $rows): void
    {
        $columns = [
            'import_id', 'source_row', ...self::HEADER,
        ];
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $sql = 'INSERT INTO imported_applications (' . implode(',', $columns) . ') VALUES '
            . implode(',', array_fill(0, count($rows), $placeholders));
        $values = [];
        foreach ($rows as $index => $row) {
            array_push($values, $id, $previousCount + $index + 2, ...$row);
        }
        $this->pdo->prepare($sql)->execute($values);
    }

    private function normalizeRow(array $fields, int $rowNumber): array
    {
        if (count($fields) !== count(self::HEADER)) {
            throw new InvalidArgumentException("Строка {$rowNumber}: ожидалось 15 колонок");
        }
        $fields = array_map(static fn ($value): string => trim((string) $value), $fields);
        foreach ([0, 1, 3, 4, 6, 7, 9, 11] as $required) {
            if ($fields[$required] === '') {
                throw new InvalidArgumentException("Строка {$rowNumber}: пустое обязательное поле " . self::HEADER[$required]);
            }
        }
        foreach ([1, 14] as $date) {
            if ($fields[$date] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fields[$date])) {
                throw new InvalidArgumentException("Строка {$rowNumber}: неверная дата " . self::HEADER[$date]);
            }
        }
        $budget = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $fields[10]);
        if ($budget !== '' && !preg_match('/^\d+(?:[.,]\d{1,2})?$/', $budget)) {
            throw new InvalidArgumentException("Строка {$rowNumber}: неверный бюджет");
        }
        $fields[10] = $budget === '' ? null : str_replace(',', '.', $budget);
        foreach ([2, 5, 8, 12, 13, 14] as $nullable) {
            $fields[$nullable] = $fields[$nullable] === '' ? null : $fields[$nullable];
        }
        return $fields;
    }

    private function lockedJob(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new InvalidArgumentException('Неверный ID импорта');
        }
        $statement = $this->pdo->prepare('SELECT * FROM lead_imports WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        return $statement->fetch() ?: throw new InvalidArgumentException('Импорт не найден');
    }

    private function progress(array $job): array
    {
        return [
            'id' => $job['id'],
            'status' => $job['status'],
            'imported_rows' => (int) $job['imported_rows'],
            'progress_percent' => $job['status'] === 'completed' ? 100 : min(99, (int) round((int) $job['byte_offset'] / (int) $job['file_size'] * 100)),
        ];
    }

    private function path(string $id): string
    {
        return $this->storagePath . '/' . $id . '.csv';
    }
}
