<?php

use DOMDocument;
use DOMXPath;

if (! function_exists('registrar_normalize_cookies')) {
    function registrar_normalize_cookies(?string $cookies): ?string
    {
        if ($cookies === null || trim($cookies) === '') {
            return null;
        }

        return preg_replace('/\s*;\s*/', '; ', trim($cookies)) ?: $cookies;
    }
}

if (! function_exists('registrar_parse_idr_price')) {
    function registrar_parse_idr_price(?string $price): ?float
    {
        if ($price === null) {
            return null;
        }

        $clean = preg_replace('/[^\d,\.]/', '', $price);

        if ($clean === null || $clean === '') {
            return null;
        }

        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);

        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }
}

if (! function_exists('registrar_create_xpath')) {
    function registrar_create_xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;

        $internalErrors = libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return new DOMXPath($dom);
    }
}
