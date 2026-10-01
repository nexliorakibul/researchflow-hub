<?php

declare(strict_types=1);

final class ScholarlyApiException extends RuntimeException
{
}

function scholarly_text_limit(string $value, int $maximum): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maximum, 'UTF-8')
        : substr($value, 0, $maximum);
}

function normalize_doi(string $doi): string
{
    $doi = trim(rawurldecode($doi));
    $doi = preg_replace('~\A(?:https?://)?(?:dx\.)?doi\.org/~i', '', $doi) ?? $doi;
    $doi = preg_replace('/\Adoi:\s*/i', '', $doi) ?? $doi;

    return strtolower(trim($doi));
}

function valid_doi(string $doi): bool
{
    return preg_match('/\A10\.\d{4,9}\/\S+\z/i', normalize_doi($doi)) === 1;
}

function scholarly_safe_http_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));

    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

function scholarly_api_rate_limit(string $bucket, int $maximum, int $windowSeconds): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $now = time();
    $stored = $_SESSION['_scholarly_api_limits'][$bucket] ?? [];
    $attempts = is_array($stored)
        ? array_values(array_filter($stored, static fn (mixed $time): bool => is_int($time) && $time > $now - $windowSeconds))
        : [];

    if (count($attempts) >= $maximum) {
        throw new ScholarlyApiException('Too many scholarly API requests. Please wait a minute and try again.');
    }

    $attempts[] = $now;
    $_SESSION['_scholarly_api_limits'][$bucket] = $attempts;
}

function scholarly_api_cache_path(string $url): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'researchflow-api-cache'
        . DIRECTORY_SEPARATOR . hash('sha256', $url) . '.json';
}

function scholarly_api_status_code(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
    }

    return $status ?? 0;
}

function scholarly_api_get_json(string $url, array $allowedHosts, int $cacheSeconds = 3600): array
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));

    if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, $allowedHosts, true)) {
        throw new ScholarlyApiException('The scholarly API address is not allowed.');
    }

    $cachePath = scholarly_api_cache_path($url);
    if (is_file($cachePath) && filemtime($cachePath) !== false && filemtime($cachePath) >= time() - $cacheSeconds) {
        $cached = file_get_contents($cachePath);
        $decoded = is_string($cached) ? json_decode($cached, true) : null;
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $contactEmail = (string) app_config('api_contact_email', '');
    $agent = 'ResearchFlow-Hub/2.0';
    if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $agent .= ' (mailto:' . $contactEmail . ')';
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => (int) app_config('scholarly_api_timeout', 8),
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Accept: application/json\r\nUser-Agent: {$agent}\r\nConnection: close\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $http_response_header = [];
    set_error_handler(static fn (int $severity, string $message): bool => true);
    try {
        $response = file_get_contents($url, false, $context, 0, 2_000_000);
    } finally {
        restore_error_handler();
    }

    $status = scholarly_api_status_code($http_response_header);
    if (!is_string($response) || $status < 200 || $status >= 300) {
        if ($status === 404) {
            throw new ScholarlyApiException('No matching scholarly record was found.');
        }
        if ($status === 429) {
            throw new ScholarlyApiException('The scholarly service is busy. Please wait and try again.');
        }
        throw new ScholarlyApiException('The scholarly service is temporarily unavailable.');
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new ScholarlyApiException('The scholarly service returned an invalid response.');
    }

    $cacheDirectory = dirname($cachePath);
    if ((is_dir($cacheDirectory) || @mkdir($cacheDirectory, 0700, true)) && is_writable($cacheDirectory)) {
        @file_put_contents($cachePath, $response, LOCK_EX);
    }

    return $decoded;
}

function scholarly_year_from_date_parts(array $record): string
{
    foreach (['published-print', 'published-online', 'published', 'issued', 'created'] as $field) {
        $year = $record[$field]['date-parts'][0][0] ?? null;
        if (is_int($year) || (is_string($year) && ctype_digit($year))) {
            return (string) $year;
        }
    }

    return '';
}

function crossref_paper_by_doi(string $doi): array
{
    $doi = normalize_doi($doi);
    if (!valid_doi($doi)) {
        throw new ScholarlyApiException('Enter a valid DOI, for example 10.1000/example.');
    }
    scholarly_api_rate_limit('crossref_lookup', 20, 60);

    $query = [];
    $contactEmail = (string) app_config('api_contact_email', '');
    if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $query['mailto'] = $contactEmail;
    }

    $url = 'https://api.crossref.org/works/' . rawurlencode($doi);
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $response = scholarly_api_get_json($url, ['api.crossref.org']);
    $record = $response['message'] ?? null;
    if (!is_array($record)) {
        throw new ScholarlyApiException('Crossref did not return paper metadata.');
    }

    $authors = [];
    foreach (($record['author'] ?? []) as $author) {
        if (!is_array($author)) {
            continue;
        }
        $name = trim(implode(' ', array_filter([
            trim((string) ($author['given'] ?? '')),
            trim((string) ($author['family'] ?? '')),
        ])));
        if ($name !== '') {
            $authors[] = $name;
        }
    }

    $abstract = strip_tags((string) ($record['abstract'] ?? ''));
    $subjects = array_filter(array_map('strval', is_array($record['subject'] ?? null) ? $record['subject'] : []));
    $title = (string) (($record['title'][0] ?? '') ?: ($record['short-title'][0] ?? ''));
    $venue = (string) (($record['container-title'][0] ?? '') ?: ($record['publisher'] ?? ''));
    $recordDoi = normalize_doi((string) ($record['DOI'] ?? $doi));

    return [
        'project_id' => '',
        'title' => scholarly_text_limit($title, 500),
        'authors' => scholarly_text_limit(implode(', ', $authors), 1000),
        'publication_year' => scholarly_year_from_date_parts($record),
        'venue' => scholarly_text_limit($venue, 255),
        'volume' => scholarly_text_limit((string) ($record['volume'] ?? ''), 50),
        'issue' => scholarly_text_limit((string) ($record['issue'] ?? ''), 50),
        'pages' => scholarly_text_limit((string) ($record['page'] ?? ''), 50),
        'doi' => $recordDoi,
        'url' => 'https://doi.org/' . $recordDoi,
        'research_area' => '',
        'keywords' => scholarly_text_limit(implode(', ', array_slice($subjects, 0, 12)), 1000),
        'summary' => scholarly_text_limit(html_entity_decode($abstract, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 65000),
        'reading_status' => 'to_read',
        'personal_notes' => '',
        'source' => 'Crossref',
    ];
}

function openalex_reconstruct_abstract(mixed $index): string
{
    if (!is_array($index)) {
        return '';
    }

    $words = [];
    foreach ($index as $word => $positions) {
        if (!is_string($word) || !is_array($positions)) {
            continue;
        }
        foreach ($positions as $position) {
            if (is_int($position) || (is_string($position) && ctype_digit($position))) {
                $words[(int) $position] = $word;
            }
        }
    }
    ksort($words);

    return implode(' ', $words);
}

function openalex_paper_from_record(array $record): array
{
    $authors = [];
    foreach (($record['authorships'] ?? []) as $authorship) {
        $name = trim((string) ($authorship['author']['display_name'] ?? ''));
        if ($name !== '') {
            $authors[] = $name;
        }
    }

    $topics = [];
    foreach (($record['topics'] ?? []) as $topic) {
        $name = trim((string) ($topic['display_name'] ?? ''));
        if ($name !== '') {
            $topics[] = $name;
        }
    }

    $doi = normalize_doi((string) ($record['doi'] ?? ''));
    $primaryLocation = is_array($record['primary_location'] ?? null) ? $record['primary_location'] : [];
    $source = is_array($primaryLocation['source'] ?? null) ? $primaryLocation['source'] : [];
    $url = scholarly_safe_http_url((string) ($primaryLocation['landing_page_url'] ?? ''));
    if ($url === '' && $doi !== '') {
        $url = 'https://doi.org/' . $doi;
    }
    if ($url === '') {
        $url = scholarly_safe_http_url((string) ($record['id'] ?? ''));
    }

    $openAlexId = normalize_openalex_work_id((string) ($record['id'] ?? ''));
    $openAlexUrl = preg_match('/\AW\d+\z/', $openAlexId) === 1
        ? 'https://openalex.org/' . $openAlexId
        : '';

    return [
        'project_id' => '',
        'title' => scholarly_text_limit((string) (($record['title'] ?? '') ?: ($record['display_name'] ?? '')), 500),
        'authors' => scholarly_text_limit(implode(', ', $authors), 1000),
        'publication_year' => (string) ($record['publication_year'] ?? ''),
        'venue' => scholarly_text_limit((string) ($source['display_name'] ?? ''), 255),
        'volume' => scholarly_text_limit((string) ($record['biblio']['volume'] ?? ''), 50),
        'issue' => scholarly_text_limit((string) ($record['biblio']['issue'] ?? ''), 50),
        'pages' => scholarly_text_limit(implode('-', array_filter([
            (string) ($record['biblio']['first_page'] ?? ''),
            (string) ($record['biblio']['last_page'] ?? ''),
        ])), 50),
        'doi' => $doi,
        'url' => scholarly_text_limit($url, 1000),
        'research_area' => scholarly_text_limit((string) ($record['primary_topic']['display_name'] ?? ''), 150),
        'keywords' => scholarly_text_limit(implode(', ', array_slice(array_unique($topics), 0, 12)), 1000),
        'summary' => scholarly_text_limit(openalex_reconstruct_abstract($record['abstract_inverted_index'] ?? null), 65000),
        'reading_status' => 'to_read',
        'personal_notes' => '',
        'source' => 'OpenAlex',
        'openalex_id' => $openAlexUrl,
        'cited_by_count' => max(0, (int) ($record['cited_by_count'] ?? 0)),
        'is_open_access' => (bool) ($record['open_access']['is_oa'] ?? false),
    ];
}

function openalex_query_parameters(array $parameters): array
{
    $apiKey = (string) app_config('openalex_api_key', '');
    if ($apiKey !== '') {
        $parameters['api_key'] = $apiKey;
    }

    $contactEmail = (string) app_config('api_contact_email', '');
    if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $parameters['mailto'] = $contactEmail;
    }

    return $parameters;
}

function openalex_search_papers(string $query, int $limit = 10): array
{
    $query = scholarly_text_limit($query, 200);
    if (strlen($query) < 2) {
        throw new ScholarlyApiException('Enter at least two characters to search OpenAlex.');
    }
    scholarly_api_rate_limit('openalex_search', 30, 60);

    $parameters = openalex_query_parameters([
        'search' => $query,
        'per-page' => max(1, min(20, $limit)),
    ]);
    $url = 'https://api.openalex.org/works?' . http_build_query($parameters);
    $response = scholarly_api_get_json($url, ['api.openalex.org'], 900);

    $papers = [];
    foreach (($response['results'] ?? []) as $record) {
        if (is_array($record)) {
            $papers[] = openalex_paper_from_record($record);
        }
    }

    return $papers;
}

function normalize_openalex_work_id(string $workId): string
{
    $workId = trim($workId);
    $workId = preg_replace('~\Ahttps://openalex\.org/~i', '', $workId) ?? $workId;

    return strtoupper($workId);
}

function openalex_paper_by_id(string $workId): array
{
    $workId = normalize_openalex_work_id($workId);
    if (preg_match('/\AW\d+\z/', $workId) !== 1) {
        throw new ScholarlyApiException('The selected OpenAlex work ID is invalid.');
    }
    scholarly_api_rate_limit('openalex_import', 30, 60);

    $parameters = openalex_query_parameters([]);
    $url = 'https://api.openalex.org/works/' . rawurlencode($workId);
    if ($parameters !== []) {
        $url .= '?' . http_build_query($parameters);
    }
    $record = scholarly_api_get_json($url, ['api.openalex.org']);

    return openalex_paper_from_record($record);
}
