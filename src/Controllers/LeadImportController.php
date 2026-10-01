<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\LeadImportService;
use InvalidArgumentException;

final class LeadImportController
{
    public function __construct(private LeadImportService $service) {}

    public function index(): void
    {
        require dirname(__DIR__, 2) . '/src/Views/import.php';
    }

    public function start(array $data): array
    {
        return $this->service->start((string) ($data['filename'] ?? ''), (int) ($data['size'] ?? 0));
    }

    public function chunk(array $data): array
    {
        $bytes = file_get_contents('php://input');
        if ($bytes === false) {
            throw new InvalidArgumentException('Не удалось прочитать фрагмент файла');
        }
        return $this->service->uploadChunk(
            (string) ($_SERVER['HTTP_X_IMPORT_ID'] ?? ''),
            (int) ($_SERVER['HTTP_X_UPLOAD_OFFSET'] ?? -1),
            $bytes
        );
    }

    public function finish(array $data): array
    {
        return $this->service->finishUpload((string) ($data['id'] ?? ''));
    }

    public function process(array $data): array
    {
        return $this->service->process((string) ($data['id'] ?? ''));
    }
}
