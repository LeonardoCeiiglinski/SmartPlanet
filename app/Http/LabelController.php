<?php

declare(strict_types=1);

namespace App\Http;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Domain\Labels\LabelService;

final class LabelController
{
    public function __construct(private readonly LabelService $service = new LabelService())
    {
    }

    public function index(): void
    {
        Auth::authorize('labels.print');
        View::render('labels/index', [
            'title' => 'Etiquetas',
            'products' => $this->service->products(),
            'csrf' => Csrf::token(),
        ], 'labels/layout');
    }

    public function printHistory(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        $this->service->recordPrint(
            is_array($body['items'] ?? null) ? $body['items'] : [],
            (string) ($body['method'] ?? ''),
            is_array($body['settings'] ?? null) ? $body['settings'] : []
        );
        View::json(['ok' => true], 201);
    }

    public function migrationPreview(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        View::json($this->service->previewMigration(is_array($body['products'] ?? null) ? $body['products'] : []));
    }

    public function migrate(): never
    {
        Auth::authorize('labels.print');
        Csrf::enforce();
        $body = $this->jsonBody();
        $result = $this->service->migrate(
            is_array($body['products'] ?? null) ? $body['products'] : [],
            is_array($body['indices'] ?? null) ? $body['indices'] : []
        );
        View::json($result, 201);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $decoded = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON inválido.');
        }
        return $decoded;
    }
}
