<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LibreTranslateService
{
    private const ENDPOINTS = [
        'https://libretranslate.com/translate',
        'https://translate.argosopentech.com/translate',
    ];

    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function translateText(string $text, string $targetLocale, string $sourceLocale = 'fr'): ?string
    {
        $text = trim($text);
        $targetLocale = strtolower(trim($targetLocale));
        $sourceLocale = strtolower(trim($sourceLocale));

        if ($text === '' || $targetLocale === '' || $targetLocale === $sourceLocale) {
            return $text;
        }

        foreach (self::ENDPOINTS as $endpoint) {
            try {
                $response = $this->httpClient->request('POST', $endpoint, [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'q' => $text,
                        'source' => $sourceLocale,
                        'target' => $targetLocale,
                        'format' => 'text',
                    ],
                ]);

                $payload = $response->toArray(false);
                $translated = $payload['translatedText'] ?? null;

                if (is_string($translated) && trim($translated) !== '') {
                    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            } catch (ExceptionInterface) {
                continue;
            }
        }

        try {
            $response = $this->httpClient->request('GET', 'https://translate.googleapis.com/translate_a/single', [
                'query' => [
                    'client' => 'gtx',
                    'sl' => $sourceLocale,
                    'tl' => $targetLocale,
                    'dt' => 't',
                    'q' => $text,
                ],
            ]);

            $payload = json_decode($response->getContent(false), true);
            if (is_array($payload) && isset($payload[0]) && is_array($payload[0])) {
                $parts = [];
                foreach ($payload[0] as $segment) {
                    if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                        $parts[] = $segment[0];
                    }
                }

                $translated = trim(implode('', $parts));
                if ($translated !== '') {
                    return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        } catch (\Throwable) {
            // Keep silent and let caller fallback to source text.
        }

        return null;
    }
}
