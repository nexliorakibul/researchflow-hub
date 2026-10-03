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

function scholarly_api_get_json(
    string $url,
    array $allowedHosts,
    int $cacheSeconds = 3600,
    array $additionalHeaders = []
): array
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
    $agent = 'ResearchFlow-Hub/3.0';
    if ($contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        $agent .= ' (mailto:' . $contactEmail . ')';
    }

    $headers = [
        'Accept: application/json',
        'User-Agent: ' . $agent,
        'Connection: close',
    ];
    foreach ($additionalHeaders as $header) {
        $header = trim((string) $header);
        if ($header !== '' && !str_contains($header, "\r") && !str_contains($header, "\n")) {
            $headers[] = $header;
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => (int) app_config('scholarly_api_timeout', 8),
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
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

function scholarly_api_post_form_json(string $url, array $allowedHosts, array $fields): array
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, $allowedHosts, true)) {
        throw new ScholarlyApiException('The scholarly API address is not allowed.');
    }

    $body = http_build_query($fields);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => (int) app_config('scholarly_api_timeout', 8),
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n",
            'content' => $body,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $http_response_header = [];
    set_error_handler(static fn (int $severity, string $message): bool => true);
    try {
        $response = file_get_contents($url, false, $context, 0, 500_000);
    } finally {
        restore_error_handler();
    }

    $status = scholarly_api_status_code($http_response_header);
    $decoded = is_string($response) ? json_decode($response, true) : null;
    if (!is_array($decoded) || $status < 200 || $status >= 300) {
        throw new ScholarlyApiException('The external identity service could not complete the request.');
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
        'code_url' => '',
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
        'code_url' => '',
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

function semantic_scholar_headers(): array
{
    $apiKey = (string) app_config('semantic_scholar_api_key', '');

    return $apiKey !== '' ? ['x-api-key: ' . $apiKey] : [];
}

function semantic_scholar_paper_id(array $paper): string
{
    $doi = normalize_doi((string) ($paper['doi'] ?? ''));
    if ($doi !== '' && valid_doi($doi)) {
        return 'DOI:' . $doi;
    }

    $title = scholarly_text_limit((string) ($paper['title'] ?? ''), 200);
    if (strlen($title) < 2) {
        throw new ScholarlyApiException('Add a DOI or title before requesting scholarly insights.');
    }

    $url = 'https://api.semanticscholar.org/graph/v1/paper/search?'
        . http_build_query(['query' => $title, 'limit' => 1, 'fields' => 'title,year,authors']);
    $response = scholarly_api_get_json($url, ['api.semanticscholar.org'], 1800, semantic_scholar_headers());
    $paperId = trim((string) ($response['data'][0]['paperId'] ?? ''));
    if ($paperId === '') {
        throw new ScholarlyApiException('Semantic Scholar could not match this saved paper.');
    }

    return $paperId;
}

function semantic_scholar_item(array $record): array
{
    $authors = [];
    foreach (($record['authors'] ?? []) as $author) {
        $name = trim((string) ($author['name'] ?? ''));
        if ($name !== '') {
            $authors[] = $name;
        }
    }
    $externalIds = is_array($record['externalIds'] ?? null) ? $record['externalIds'] : [];
    $doi = normalize_doi((string) ($externalIds['DOI'] ?? ''));
    $openAccess = is_array($record['openAccessPdf'] ?? null) ? $record['openAccessPdf'] : [];
    $url = scholarly_safe_http_url((string) ($record['url'] ?? ''));
    $pdfUrl = scholarly_safe_http_url((string) ($openAccess['url'] ?? ''));

    return [
        'paper_id' => scholarly_text_limit((string) ($record['paperId'] ?? ''), 80),
        'title' => scholarly_text_limit((string) ($record['title'] ?? ''), 500),
        'authors' => scholarly_text_limit(implode(', ', $authors), 1000),
        'year' => (string) ($record['year'] ?? ''),
        'venue' => scholarly_text_limit((string) ($record['venue'] ?? ''), 255),
        'doi' => $doi,
        'url' => $url,
        'pdf_url' => $pdfUrl,
        'citation_count' => max(0, (int) ($record['citationCount'] ?? 0)),
        'reference_count' => max(0, (int) ($record['referenceCount'] ?? 0)),
        'influential_citation_count' => max(0, (int) ($record['influentialCitationCount'] ?? 0)),
    ];
}

function semantic_scholar_paper_list(string $paperId, string $relationship): array
{
    if (!in_array($relationship, ['citations', 'references'], true)) {
        throw new InvalidArgumentException('Unsupported Semantic Scholar relationship.');
    }
    $url = 'https://api.semanticscholar.org/graph/v1/paper/' . rawurlencode($paperId)
        . '/' . $relationship . '?' . http_build_query([
            'limit' => 8,
            'fields' => 'title,authors,year,venue,url,externalIds,citationCount,openAccessPdf',
        ]);
    $response = scholarly_api_get_json($url, ['api.semanticscholar.org'], 1800, semantic_scholar_headers());
    $key = $relationship === 'citations' ? 'citingPaper' : 'citedPaper';
    $items = [];
    foreach (($response['data'] ?? []) as $row) {
        $record = is_array($row) && is_array($row[$key] ?? null) ? $row[$key] : [];
        if ($record !== [] && trim((string) ($record['title'] ?? '')) !== '') {
            $items[] = semantic_scholar_item($record);
        }
    }

    return $items;
}

function semantic_scholar_recommendations(string $paperId): array
{
    $url = 'https://api.semanticscholar.org/recommendations/v1/papers/forpaper/'
        . rawurlencode($paperId) . '?' . http_build_query([
            'limit' => 6,
            'fields' => 'title,authors,year,venue,url,externalIds,citationCount,openAccessPdf',
        ]);
    $response = scholarly_api_get_json($url, ['api.semanticscholar.org'], 1800, semantic_scholar_headers());
    $items = [];
    foreach (($response['recommendedPapers'] ?? []) as $record) {
        if (is_array($record) && trim((string) ($record['title'] ?? '')) !== '') {
            $items[] = semantic_scholar_item($record);
        }
    }

    return $items;
}

function semantic_scholar_insights(array $paper): array
{
    scholarly_api_rate_limit('semantic_scholar_insights', 12, 60);
    $paperId = semantic_scholar_paper_id($paper);
    $fields = 'title,authors,year,venue,url,externalIds,citationCount,referenceCount,'
        . 'influentialCitationCount,openAccessPdf';
    $url = 'https://api.semanticscholar.org/graph/v1/paper/' . rawurlencode($paperId)
        . '?' . http_build_query(['fields' => $fields]);
    $record = scholarly_api_get_json($url, ['api.semanticscholar.org'], 1800, semantic_scholar_headers());
    $details = semantic_scholar_item($record);
    $resolvedId = (string) ($details['paper_id'] ?: $paperId);

    try {
        $citations = semantic_scholar_paper_list($resolvedId, 'citations');
    } catch (ScholarlyApiException $exception) {
        $citations = [];
    }
    try {
        $references = semantic_scholar_paper_list($resolvedId, 'references');
    } catch (ScholarlyApiException $exception) {
        $references = [];
    }
    try {
        $recommendations = semantic_scholar_recommendations($resolvedId);
    } catch (ScholarlyApiException $exception) {
        $recommendations = [];
    }

    return [
        'provider' => 'Semantic Scholar',
        'details' => $details,
        'citations' => $citations,
        'references' => $references,
        'recommendations' => $recommendations,
    ];
}

function openalex_insight_item(array $record): array
{
    $paper = openalex_paper_from_record($record);
    $bestLocation = is_array($record['best_oa_location'] ?? null) ? $record['best_oa_location'] : [];

    return [
        'paper_id' => normalize_openalex_work_id((string) ($record['id'] ?? '')),
        'title' => $paper['title'],
        'authors' => $paper['authors'],
        'year' => $paper['publication_year'],
        'venue' => $paper['venue'],
        'doi' => $paper['doi'],
        'url' => $paper['url'],
        'pdf_url' => scholarly_safe_http_url((string) ($bestLocation['pdf_url'] ?? '')),
        'citation_count' => max(0, (int) ($record['cited_by_count'] ?? 0)),
        'reference_count' => count(is_array($record['referenced_works'] ?? null) ? $record['referenced_works'] : []),
        'influential_citation_count' => 0,
    ];
}

function openalex_insight_records_by_ids(array $ids, int $limit): array
{
    $normalized = [];
    foreach (array_slice($ids, 0, $limit) as $id) {
        $id = normalize_openalex_work_id((string) $id);
        if (preg_match('/\AW\d+\z/', $id) === 1) {
            $normalized[] = $id;
        }
    }
    if ($normalized === []) {
        return [];
    }

    $parameters = openalex_query_parameters([
        'filter' => 'openalex_id:' . implode('|', $normalized),
        'per-page' => count($normalized),
    ]);
    $response = scholarly_api_get_json(
        'https://api.openalex.org/works?' . http_build_query($parameters),
        ['api.openalex.org'],
        1800
    );
    $items = [];
    foreach (($response['results'] ?? []) as $record) {
        if (is_array($record)) {
            $items[] = openalex_insight_item($record);
        }
    }

    return $items;
}

function openalex_insights(array $paper): array
{
    scholarly_api_rate_limit('openalex_insights', 20, 60);
    $doi = normalize_doi((string) ($paper['doi'] ?? ''));
    if (valid_doi($doi)) {
        $lookup = 'doi:' . $doi;
        $parameters = openalex_query_parameters([]);
        $url = 'https://api.openalex.org/works/' . $lookup;
        if ($parameters !== []) {
            $url .= '?' . http_build_query($parameters);
        }
        $record = scholarly_api_get_json($url, ['api.openalex.org'], 1800);
    } else {
        $results = openalex_search_papers((string) ($paper['title'] ?? ''), 1);
        $workId = normalize_openalex_work_id((string) ($results[0]['openalex_id'] ?? ''));
        if (preg_match('/\AW\d+\z/', $workId) !== 1) {
            throw new ScholarlyApiException('OpenAlex could not match this saved paper.');
        }
        $parameters = openalex_query_parameters([]);
        $url = 'https://api.openalex.org/works/' . rawurlencode($workId);
        if ($parameters !== []) {
            $url .= '?' . http_build_query($parameters);
        }
        $record = scholarly_api_get_json($url, ['api.openalex.org'], 1800);
    }

    $details = openalex_insight_item($record);
    $workId = (string) $details['paper_id'];
    $citationParameters = openalex_query_parameters([
        'filter' => 'cites:' . $workId,
        'per-page' => 8,
        'sort' => 'cited_by_count:desc',
    ]);
    $citationResponse = scholarly_api_get_json(
        'https://api.openalex.org/works?' . http_build_query($citationParameters),
        ['api.openalex.org'],
        1800
    );
    $citations = [];
    foreach (($citationResponse['results'] ?? []) as $citation) {
        if (is_array($citation)) {
            $citations[] = openalex_insight_item($citation);
        }
    }

    return [
        'provider' => 'OpenAlex',
        'details' => $details,
        'citations' => $citations,
        'references' => openalex_insight_records_by_ids(
            is_array($record['referenced_works'] ?? null) ? $record['referenced_works'] : [],
            8
        ),
        'recommendations' => openalex_insight_records_by_ids(
            is_array($record['related_works'] ?? null) ? $record['related_works'] : [],
            6
        ),
    ];
}

function scholarly_paper_insights(array $paper): array
{
    try {
        return semantic_scholar_insights($paper);
    } catch (ScholarlyApiException $exception) {
        $fallback = openalex_insights($paper);
        $fallback['provider_notice'] = 'Semantic Scholar was unavailable, so OpenAlex supplied this live result.';

        return $fallback;
    }
}

function unpaywall_open_access(string $doi): array
{
    $doi = normalize_doi($doi);
    if (!valid_doi($doi)) {
        throw new ScholarlyApiException('A valid DOI is required for the open-access lookup.');
    }
    $email = (string) app_config('api_contact_email', '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }
    scholarly_api_rate_limit('unpaywall_lookup', 20, 60);
    $url = 'https://api.unpaywall.org/v2/' . rawurlencode($doi) . '?email=' . rawurlencode($email);
    $record = scholarly_api_get_json($url, ['api.unpaywall.org'], 21600);
    $best = is_array($record['best_oa_location'] ?? null) ? $record['best_oa_location'] : [];

    return [
        'is_open_access' => (bool) ($record['is_oa'] ?? false),
        'status' => scholarly_text_limit((string) ($record['oa_status'] ?? ''), 30),
        'license' => scholarly_text_limit((string) ($best['license'] ?? ''), 100),
        'version' => scholarly_text_limit((string) ($best['version'] ?? ''), 80),
        'pdf_url' => scholarly_safe_http_url((string) ($best['url_for_pdf'] ?? '')),
        'landing_url' => scholarly_safe_http_url((string) ($best['url_for_landing_page'] ?? '')),
    ];
}

function datacite_dataset_from_record(array $record): array
{
    $attributes = is_array($record['attributes'] ?? null) ? $record['attributes'] : [];
    $titles = is_array($attributes['titles'] ?? null) ? $attributes['titles'] : [];
    $creators = [];
    foreach (($attributes['creators'] ?? []) as $creator) {
        $name = trim((string) ($creator['name'] ?? ''));
        if ($name !== '') {
            $creators[] = $name;
        }
    }
    $subjects = [];
    foreach (($attributes['subjects'] ?? []) as $subject) {
        $name = trim((string) ($subject['subject'] ?? ''));
        if ($name !== '') {
            $subjects[] = $name;
        }
    }
    $descriptions = [];
    foreach (($attributes['descriptions'] ?? []) as $description) {
        $text = trim(strip_tags((string) ($description['description'] ?? '')));
        if ($text !== '') {
            $descriptions[] = $text;
        }
    }
    $rights = is_array($attributes['rightsList'][0] ?? null) ? $attributes['rightsList'][0] : [];
    $doi = normalize_doi((string) (($attributes['doi'] ?? '') ?: ($record['id'] ?? '')));
    $sizes = is_array($attributes['sizes'] ?? null) ? array_map('strval', $attributes['sizes']) : [];
    $title = (string) ($titles[0]['title'] ?? '');
    $resourceType = (string) ($attributes['types']['resourceType'] ?? 'Dataset');

    return [
        'project_id' => '',
        'name' => scholarly_text_limit($title, 255),
        'domain' => scholarly_text_limit((string) ($subjects[0] ?? ''), 150),
        'source' => scholarly_text_limit((string) (($attributes['publisher'] ?? '') ?: 'DataCite'), 255),
        'url' => $doi !== '' ? 'https://doi.org/' . $doi : scholarly_safe_http_url((string) ($attributes['url'] ?? '')),
        'row_count' => '',
        'column_count' => '',
        'image_count' => '',
        'class_count' => '',
        'file_size' => scholarly_text_limit(implode(', ', array_slice($sizes, 0, 3)), 100),
        'license' => scholarly_text_limit((string) (($rights['rights'] ?? '') ?: ($rights['rightsIdentifier'] ?? '')), 255),
        'access_type' => 'public',
        'description' => scholarly_text_limit(implode("\n\n", array_slice($descriptions, 0, 2)), 65000),
        'status' => 'identified',
        'notes' => scholarly_text_limit(implode("\n", array_filter([
            $doi !== '' ? 'DataCite DOI: ' . $doi : '',
            $creators !== [] ? 'Creators: ' . implode(', ', array_slice($creators, 0, 20)) : '',
            ($attributes['publicationYear'] ?? '') !== '' ? 'Publication year: ' . (string) $attributes['publicationYear'] : '',
            $resourceType !== '' ? 'Resource type: ' . $resourceType : '',
        ])), 65000),
        'datacite_doi' => $doi,
        'creators' => scholarly_text_limit(implode(', ', $creators), 1000),
        'publication_year' => (string) ($attributes['publicationYear'] ?? ''),
    ];
}

function datacite_search_datasets(string $query, int $limit = 10): array
{
    $query = scholarly_text_limit($query, 200);
    if (strlen($query) < 2) {
        throw new ScholarlyApiException('Enter at least two characters to search DataCite.');
    }
    scholarly_api_rate_limit('datacite_search', 30, 60);
    $url = 'https://api.datacite.org/dois?' . http_build_query([
        'query' => $query,
        'resource-type-id' => 'dataset',
        'page' => ['size' => max(1, min(20, $limit))],
    ]);
    $response = scholarly_api_get_json($url, ['api.datacite.org'], 900);
    $datasets = [];
    foreach (($response['data'] ?? []) as $record) {
        if (is_array($record)) {
            $dataset = datacite_dataset_from_record($record);
            if ($dataset['name'] !== '' && $dataset['datacite_doi'] !== '') {
                $datasets[] = $dataset;
            }
        }
    }

    return $datasets;
}

function datacite_dataset_by_doi(string $doi): array
{
    $doi = normalize_doi($doi);
    if (!valid_doi($doi)) {
        throw new ScholarlyApiException('The selected DataCite DOI is invalid.');
    }
    scholarly_api_rate_limit('datacite_import', 30, 60);
    $response = scholarly_api_get_json(
        'https://api.datacite.org/dois/' . rawurlencode($doi),
        ['api.datacite.org']
    );
    $record = $response['data'] ?? null;
    if (!is_array($record)) {
        throw new ScholarlyApiException('DataCite did not return dataset metadata.');
    }

    return datacite_dataset_from_record($record);
}

function normalize_orcid_id(string $orcidId): string
{
    $orcidId = strtoupper(trim($orcidId));
    $orcidId = preg_replace('~\Ahttps?://orcid\.org/~i', '', $orcidId) ?? $orcidId;

    return $orcidId;
}

function valid_orcid_id(string $orcidId): bool
{
    return preg_match('/\A\d{4}-\d{4}-\d{4}-\d{3}[\dX]\z/', normalize_orcid_id($orcidId)) === 1;
}

function orcid_oauth_ready(): bool
{
    return (string) app_config('orcid_client_id', '') !== ''
        && (string) app_config('orcid_client_secret', '') !== ''
        && filter_var((string) app_config('orcid_redirect_uri', ''), FILTER_VALIDATE_URL) !== false;
}

function orcid_authorization_url(string $state): string
{
    if (!orcid_oauth_ready()) {
        throw new ScholarlyApiException('ORCID connection is not configured on this server.');
    }

    return 'https://orcid.org/oauth/authorize?' . http_build_query([
        'client_id' => (string) app_config('orcid_client_id'),
        'response_type' => 'code',
        'scope' => '/authenticate',
        'redirect_uri' => (string) app_config('orcid_redirect_uri'),
        'state' => $state,
    ]);
}

function orcid_exchange_code(string $code): array
{
    if (!orcid_oauth_ready() || trim($code) === '') {
        throw new ScholarlyApiException('ORCID authentication could not be completed.');
    }
    $response = scholarly_api_post_form_json('https://orcid.org/oauth/token', ['orcid.org'], [
        'client_id' => (string) app_config('orcid_client_id'),
        'client_secret' => (string) app_config('orcid_client_secret'),
        'grant_type' => 'authorization_code',
        'redirect_uri' => (string) app_config('orcid_redirect_uri'),
        'code' => trim($code),
    ]);
    $orcidId = normalize_orcid_id((string) ($response['orcid'] ?? ''));
    if (!valid_orcid_id($orcidId)) {
        throw new ScholarlyApiException('ORCID did not return a valid researcher identifier.');
    }

    return [
        'orcid_id' => $orcidId,
        'name' => scholarly_text_limit((string) ($response['name'] ?? ''), 100),
    ];
}
