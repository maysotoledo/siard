<?php

namespace App\Services\Pixel;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsPreviewMetadataService
{
    public function fetch(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return [];
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Mozilla/5.0 SACAT Pixel Preview Bot',
                ])
                ->get($url);
        } catch (\Throwable) {
            return $this->fallback($url);
        }

        if (! $response->successful()) {
            return $this->fallback($url);
        }

        $html = (string) $response->body();

        if ($html === '') {
            return $this->fallback($url);
        }

        $metadata = $this->parse($html, $url);

        return array_filter([
            'og_titulo' => $metadata['title'] ?? null,
            'og_descricao' => $metadata['description'] ?? null,
            'og_imagem' => $metadata['image'] ?? null,
        ], fn ($value) => filled($value));
    }

    public function storeImage(string $imageUrl, string $token): ?string
    {
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 SACAT Pixel Preview Bot',
                ])
                ->get($imageUrl);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        if ($body === '' || strlen($body) > 5 * 1024 * 1024) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $extension = match (true) {
            str_contains($contentType, 'image/jpeg') => 'jpg',
            str_contains($contentType, 'image/png') => 'png',
            str_contains($contentType, 'image/webp') => 'webp',
            default => $this->extensionFromUrl($imageUrl),
        };

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $path = 'pixel-og/noticias/'.$token.'.'.($extension === 'jpeg' ? 'jpg' : $extension);

        Storage::disk('public')->put($path, $body);

        return $path;
    }

    private function parse(string $html, string $baseUrl): array
    {
        $previous = libxml_use_internal_errors(true);

        $html = $this->normalizeEncoding($html);

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);

        $metadata = [
            'title' => $this->meta($xpath, 'property', 'og:title')
                ?: $this->meta($xpath, 'name', 'twitter:title')
                ?: $this->title($xpath),
            'description' => $this->meta($xpath, 'property', 'og:description')
                ?: $this->meta($xpath, 'name', 'description')
                ?: $this->meta($xpath, 'name', 'twitter:description'),
            'image' => $this->meta($xpath, 'property', 'og:image')
                ?: $this->meta($xpath, 'property', 'og:image:url')
                ?: $this->meta($xpath, 'property', 'og:image:secure_url')
                ?: $this->meta($xpath, 'name', 'twitter:image')
                ?: $this->meta($xpath, 'name', 'twitter:image:src'),
        ];

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($metadata['image']) {
            $metadata['image'] = $this->absolutizeUrl($metadata['image'], $baseUrl);
        }

        return [
            'title' => $this->limit($metadata['title'], 100),
            'description' => $this->limit($metadata['description'], 200),
            'image' => $metadata['image'],
        ];
    }

    private function meta(DOMXPath $xpath, string $attribute, string $value): ?string
    {
        $nodes = $xpath->query("//meta[translate(@{$attribute}, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$value}']/@content");

        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        return trim((string) $nodes->item(0)?->nodeValue) ?: null;
    }

    private function normalizeEncoding(string $html): string
    {
        $charset = null;

        if (preg_match('/charset=["\']?([a-z0-9_\-]+)/i', $html, $matches)) {
            $charset = strtoupper($matches[1]);
        }

        if ($charset && $charset !== 'UTF-8') {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);

            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $html;
    }

    private function title(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query('//title');

        if (! $nodes || $nodes->length === 0) {
            return null;
        }

        return trim((string) $nodes->item(0)?->textContent) ?: null;
    }

    private function fallback(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);

        return array_filter([
            'og_titulo' => $host ? Str::headline($host) : null,
            'og_descricao' => $url,
        ], fn ($value) => filled($value));
    }

    private function absolutizeUrl(string $url, string $baseUrl): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $base = parse_url($baseUrl);

        if (! isset($base['scheme'], $base['host'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return "{$base['scheme']}:{$url}";
        }

        if (Str::startsWith($url, '/')) {
            return "{$base['scheme']}://{$base['host']}{$url}";
        }

        $path = isset($base['path']) ? rtrim(dirname($base['path']), '/') : '';

        return "{$base['scheme']}://{$base['host']}{$path}/{$url}";
    }

    private function extensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! $path) {
            return null;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: null;
    }

    private function limit(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, $limit, '');
    }
}
