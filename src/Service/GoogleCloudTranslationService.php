<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleCloudTranslationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $apiKey = ''
    ) {
    }

    public function translateText(string $text, string $targetLocale, ?string $sourceLocale = null): ?string
    {
        $text = trim($text);
        $targetLocale = $this->normalizeLocale($targetLocale);
        $sourceLocale = $sourceLocale !== null ? $this->normalizeLocale($sourceLocale) : null;

        if ($text === '' || $targetLocale === '') {
            return null;
        }

        if ($sourceLocale !== null && $sourceLocale !== '' && $sourceLocale === $targetLocale) {
            return $text;
        }

        $cacheKey = 'gtr_'.sha1($targetLocale.'|'.($sourceLocale ?? 'auto').'|'.$text);
        $cacheItem = $this->cache->getItem($cacheKey);
        if ($cacheItem->isHit()) {
            $cached = $cacheItem->get();

            return is_string($cached) && trim($cached) !== '' ? $cached : null;
        }

        $translated = null;

        if ($this->apiKey !== '') {
            $translated = $this->translateViaGoogleCloud($text, $targetLocale, $sourceLocale);
        }

        if (!is_string($translated) || trim($translated) === '') {
            $translated = $this->translateViaPublicFallback($text, $targetLocale, $sourceLocale);
        }

        if (!is_string($translated) || trim($translated) === '') {
            return null;
        }

        $normalized = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cacheItem->set($normalized);
        $cacheItem->expiresAfter(86400 * 30);
        $this->cache->save($cacheItem);

        return $normalized;
    }

    private function translateViaGoogleCloud(string $text, string $targetLocale, ?string $sourceLocale): ?string
    {
        try {
            $payload = [
                'q' => $text,
                'target' => $targetLocale,
                'format' => 'text',
            ];

            if ($sourceLocale !== null && $sourceLocale !== '') {
                $payload['source'] = $sourceLocale;
            }

            $response = $this->httpClient->request('POST', 'https://translation.googleapis.com/language/translate/v2', [
                'query' => ['key' => $this->apiKey],
                'json' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $data = $response->toArray(false);
            $translated = $data['data']['translations'][0]['translatedText'] ?? null;

            return is_string($translated) && trim($translated) !== '' ? $translated : null;
        } catch (ExceptionInterface) {
            return null;
        }
    }

    private function translateViaPublicFallback(string $text, string $targetLocale, ?string $sourceLocale): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => ($sourceLocale !== null && $sourceLocale !== '') ? $sourceLocale : 'auto',
                    'tl' => $targetLocale,
                    'dt' => 't',
                    'q' => $text,
                ],
            ]);

            $payload = json_decode($response->getContent(false), true);
            if (!is_array($payload) || !isset($payload[0]) || !is_array($payload[0])) {
                return null;
            }

            $parts = [];
            foreach ($payload[0] as $segment) {
                if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                    $parts[] = $segment[0];
                }
            }

            $translated = trim(implode('', $parts));

            return $translated !== '' ? $translated : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('-', '_', $normalized);

        return explode('_', $normalized)[0] ?: $normalized;
    }
}
