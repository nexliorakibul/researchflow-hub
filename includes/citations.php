<?php

declare(strict_types=1);

function citation_value(array $paper, string $field): string
{
    return trim((string) ($paper[$field] ?? ''));
}

function citation_finish(string $citation): string
{
    $citation = rtrim(trim($citation), " \t\n\r\0\x0B.,;");

    return $citation === '' ? '' : $citation . '.';
}

function citation_doi_url(string $doi): string
{
    if ($doi === '') {
        return '';
    }

    return preg_match('/\Ahttps?:\/\//i', $doi) === 1
        ? $doi
        : 'https://doi.org/' . ltrim($doi, '/');
}

function generate_ieee_citation(array $paper): string
{
    $authors = citation_value($paper, 'authors');
    $title = citation_value($paper, 'title');
    $venue = citation_value($paper, 'venue');
    $volume = citation_value($paper, 'volume');
    $issue = citation_value($paper, 'issue');
    $pages = citation_value($paper, 'pages');
    $year = citation_value($paper, 'publication_year');
    $doi = citation_value($paper, 'doi');
    $url = citation_value($paper, 'url');

    $parts = [];
    if ($authors !== '') {
        $parts[] = $authors;
    }
    if ($title !== '') {
        $parts[] = '"' . $title . '"';
    }
    if ($venue !== '') {
        $parts[] = $venue;
    }
    if ($volume !== '') {
        $parts[] = 'vol. ' . $volume;
    }
    if ($issue !== '') {
        $parts[] = 'no. ' . $issue;
    }
    if ($pages !== '') {
        $parts[] = 'pp. ' . $pages;
    }
    if ($year !== '') {
        $parts[] = $year;
    }

    $citation = citation_finish(implode(', ', $parts));
    if ($doi !== '') {
        $citation .= ' doi: ' . citation_finish($doi);
    }
    if ($url !== '') {
        $citation .= ' [Online]. Available: ' . citation_finish($url);
    }

    return trim($citation);
}

function generate_apa_citation(array $paper): string
{
    $authors = citation_value($paper, 'authors');
    $title = citation_value($paper, 'title');
    $venue = citation_value($paper, 'venue');
    $volume = citation_value($paper, 'volume');
    $issue = citation_value($paper, 'issue');
    $pages = citation_value($paper, 'pages');
    $year = citation_value($paper, 'publication_year');
    $doi = citation_value($paper, 'doi');
    $url = citation_value($paper, 'url');

    $citation = '';
    if ($authors !== '') {
        $citation .= citation_finish($authors) . ' ';
    }
    if ($year !== '') {
        $citation .= '(' . $year . '). ';
    }
    if ($title !== '') {
        $citation .= citation_finish($title) . ' ';
    }

    $publication = [];
    if ($venue !== '') {
        $publication[] = $venue;
    }
    if ($volume !== '') {
        $publication[] = $volume . ($issue !== '' ? '(' . $issue . ')' : '');
    } elseif ($issue !== '') {
        $publication[] = 'Issue ' . $issue;
    }
    if ($pages !== '') {
        $publication[] = $pages;
    }
    if ($publication !== []) {
        $citation .= citation_finish(implode(', ', $publication)) . ' ';
    }
    if ($doi !== '') {
        $citation .= citation_doi_url($doi);
    } elseif ($url !== '') {
        $citation .= $url;
    }

    return citation_finish($citation);
}

function generate_harvard_citation(array $paper): string
{
    $authors = citation_value($paper, 'authors');
    $title = citation_value($paper, 'title');
    $venue = citation_value($paper, 'venue');
    $volume = citation_value($paper, 'volume');
    $issue = citation_value($paper, 'issue');
    $pages = citation_value($paper, 'pages');
    $year = citation_value($paper, 'publication_year');
    $doi = citation_value($paper, 'doi');
    $url = citation_value($paper, 'url');

    $citation = '';
    if ($authors !== '') {
        $citation .= rtrim($authors, '.,;') . ' ';
    }
    if ($year !== '') {
        $citation .= '(' . $year . ') ';
    }
    if ($title !== '') {
        $citation .= "'" . $title . "'";
    }

    $publication = [];
    if ($venue !== '') {
        $publication[] = $venue;
    }
    if ($volume !== '') {
        $publication[] = 'vol. ' . $volume;
    }
    if ($issue !== '') {
        $publication[] = 'no. ' . $issue;
    }
    if ($pages !== '') {
        $publication[] = 'pp. ' . $pages;
    }
    if ($publication !== []) {
        $citation .= ($citation !== '' ? ', ' : '') . implode(', ', $publication);
    }
    $citation = citation_finish($citation);
    if ($doi !== '') {
        $citation .= ' doi: ' . citation_finish($doi);
    }
    if ($url !== '') {
        $citation .= ' Available at: ' . citation_finish($url);
    }

    return trim($citation);
}

function generate_paper_citations(array $paper): array
{
    return [
        'ieee' => generate_ieee_citation($paper),
        'apa' => generate_apa_citation($paper),
        'harvard' => generate_harvard_citation($paper),
    ];
}

function citation_missing_core_fields(array $paper): array
{
    $labels = [
        'authors' => 'Authors',
        'publication_year' => 'Publication year',
        'venue' => 'Venue / publisher',
    ];
    $missing = [];
    foreach ($labels as $field => $label) {
        if (citation_value($paper, $field) === '') {
            $missing[] = $label;
        }
    }

    return $missing;
}
