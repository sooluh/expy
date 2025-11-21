<?php

namespace App\Support\Concerns;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

trait ScrapesHtmlWithFallback
{
    protected ?object $scrapingantService = null;

    protected bool $scrapingantEnabled = false;

    protected function bootScrapingant(?object $scrapingantService = null): void
    {
        $this->scrapingantService = $scrapingantService;

        if ($scrapingantService === null) {
            $this->scrapingantEnabled = false;

            return;
        }

        try {
            $scrapingantService->getApiKey();

            $this->scrapingantEnabled = true;
        } catch (Throwable) {
            $this->scrapingantEnabled = false;
        }
    }

    protected function fetchHtmlWithFallback(
        string $url,
        ?string $cookies = null,
        ?string $waitForSelector = null,
        array $headers = []
    ): string {
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ...$headers,
        ];

        if ($cookies) {
            $headers['Cookie'] = $cookies;
        }

        $primaryException = null;
        $statusCode = null;

        try {
            $response = $this->client?->get($url, [
                'headers' => $headers,
                'http_errors' => false,
            ]);

            if ($response && $response->getStatusCode() === 200) {
                $body = $response->getBody()->getContents();

                if (is_string($body) && trim($body) !== '') {
                    return $body;
                }

                $primaryException = new Exception("Empty HTML response from {$url}");
            } elseif ($response) {
                $statusCode = $response->getStatusCode();

                Log::warning('Direct fetch failed, will try fallback', [
                    'url' => $url,
                    'status' => $statusCode,
                ]);

                $primaryException = new Exception("Request failed ({$url}): {$statusCode}");
            }
        } catch (Exception $e) {
            $primaryException = $e;
        }

        if ($this->scrapingantEnabled && $this->scrapingantService !== null) {
            try {
                Log::info('ScrapingAnt fallback attempt', [
                    'url' => $url,
                    'status' => $statusCode,
                    'wait_for_selector' => $waitForSelector,
                ]);

                $html = $this->scrapingantService->scrape(
                    $url,
                    $waitForSelector,
                    $cookies,
                );

                if (is_string($html) && trim($html) !== '') {
                    Log::info('ScrapingAnt fallback succeeded', ['url' => $url]);

                    return $html;
                }
            } catch (Exception $e) {
                Log::warning('ScrapingAnt fallback failed', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('ScrapingAnt not enabled, skipping fallback', [
                'url' => $url,
                'status' => $statusCode,
            ]);
        }

        if ($primaryException !== null) {
            throw $primaryException;
        }

        throw new Exception("Failed to fetch HTML from {$url}");
    }
}
