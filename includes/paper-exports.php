<?php

declare(strict_types=1);

function paper_export_value(array $paper, string $field): string
{
    return trim((string) ($paper[$field] ?? ''));
}

function paper_export_authors(string $authors): array
{
    return array_values(array_filter(array_map(
        static fn (string $author): string => trim($author),
        preg_split('/\s*,\s*/u', $authors) ?: []
    )));
}

function bibtex_escape(string $value): string
{
    return strtr(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), [
        '\\' => '\\textbackslash{}',
        '{' => '\\{',
        '}' => '\\}',
        '&' => '\\&',
        '%' => '\\%',
        '#' => '\\#',
        '_' => '\\_',
    ]);
}

function paper_bibtex_key(array $paper): string
{
    $authors = paper_export_authors(paper_export_value($paper, 'authors'));
    $firstAuthor = $authors[0] ?? 'research';
    $authorParts = preg_split('/\s+/u', $firstAuthor) ?: [];
    $surname = (string) (end($authorParts) ?: 'research');
    $year = paper_export_value($paper, 'publication_year') ?: 'nd';
    $titleWords = preg_split('/\s+/u', paper_export_value($paper, 'title')) ?: [];
    $titleWord = $titleWords[0] ?? 'paper';
    $key = preg_replace('/[^a-z0-9]+/i', '', $surname . $year . $titleWord) ?: 'paper';

    return $key . (int) ($paper['id'] ?? 0);
}

function paper_to_bibtex(array $paper): string
{
    $fields = [
        'title' => paper_export_value($paper, 'title'),
        'author' => implode(' and ', paper_export_authors(paper_export_value($paper, 'authors'))),
        'journal' => paper_export_value($paper, 'venue'),
        'year' => paper_export_value($paper, 'publication_year'),
        'volume' => paper_export_value($paper, 'volume'),
        'number' => paper_export_value($paper, 'issue'),
        'pages' => paper_export_value($paper, 'pages'),
        'doi' => normalize_doi(paper_export_value($paper, 'doi')),
        'url' => paper_export_value($paper, 'url'),
        'keywords' => paper_export_value($paper, 'keywords'),
        'abstract' => paper_export_value($paper, 'summary'),
    ];

    $lines = ['@article{' . paper_bibtex_key($paper) . ','];
    foreach ($fields as $name => $value) {
        if ($value !== '') {
            $lines[] = '  ' . $name . ' = {' . bibtex_escape($value) . '},';
        }
    }
    $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1], ',');
    $lines[] = '}';

    return implode("\n", $lines);
}

function ris_line(string $tag, string $value): string
{
    $value = trim(preg_replace('/[\r\n]+/u', ' ', $value) ?? $value);

    return $tag . '  - ' . $value;
}

function paper_to_ris(array $paper): string
{
    $lines = [ris_line('TY', 'JOUR')];
    $mapping = [
        'TI' => 'title',
        'PY' => 'publication_year',
        'JO' => 'venue',
        'VL' => 'volume',
        'IS' => 'issue',
        'DO' => 'doi',
        'UR' => 'url',
        'AB' => 'summary',
    ];

    foreach (paper_export_authors(paper_export_value($paper, 'authors')) as $author) {
        $lines[] = ris_line('AU', $author);
    }
    foreach ($mapping as $tag => $field) {
        $value = paper_export_value($paper, $field);
        if ($field === 'doi') {
            $value = normalize_doi($value);
        }
        if ($value !== '') {
            $lines[] = ris_line($tag, $value);
        }
    }

    $pages = paper_export_value($paper, 'pages');
    if ($pages !== '') {
        $pageParts = preg_split('/\s*[-–—]\s*/u', $pages, 2) ?: [];
        $lines[] = ris_line('SP', $pageParts[0] ?? $pages);
        if (isset($pageParts[1]) && $pageParts[1] !== '') {
            $lines[] = ris_line('EP', $pageParts[1]);
        }
    }

    foreach (array_filter(array_map('trim', explode(',', paper_export_value($paper, 'keywords')))) as $keyword) {
        $lines[] = ris_line('KW', $keyword);
    }
    $lines[] = ris_line('ER', '');

    return implode("\r\n", $lines);
}

function export_papers(array $papers, string $format): string
{
    $records = array_map(
        $format === 'bibtex' ? 'paper_to_bibtex' : 'paper_to_ris',
        $papers
    );

    return implode($format === 'bibtex' ? "\n\n" : "\r\n\r\n", $records) . "\n";
}
