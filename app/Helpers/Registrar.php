<?php

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

if (! function_exists('registrar_extract_table_rows')) {
    function registrar_extract_table_rows(string $html, string $tableId): array
    {
        $tableFound = str_contains($html, $tableId);
        $xpath = registrar_create_xpath($html);

        $rows = $xpath->query("//table[@id=\"{$tableId}\"]//tr");
        $tableCount = $xpath->query("//table[@id=\"{$tableId}\"]")?->length ?? 0;
        $rowCount = $rows?->length ?? 0;

        if ($rowCount === 0) {
            $pattern = '/<table[^>]*id=["\']'.preg_quote($tableId, '/').'["\'][\s\S]*?<\/table>/i';

            if (preg_match($pattern, $html, $matches)) {
                $tableHtml = '<html><body>'.$matches[0].'</body></html>';
                $xpath = registrar_create_xpath($tableHtml);
                $rows = $xpath->query("//table[@id=\"{$tableId}\"]//tr");
                $rowCount = $rows?->length ?? 0;
                $tableCount = max($tableCount, 1);
            }
        }

        return [
            'rows' => $rows ? collect(iterator_to_array($rows))->filter(fn ($node) => $node instanceof DOMElement) : collect(),
            'table_found' => $tableFound,
            'table_count' => $tableCount,
            'row_count' => $rowCount,
        ];
    }
}
