<?php

declare(strict_types=1);

function string_length_between(string $value, int $minimum, int $maximum): bool
{
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

    return $length >= $minimum && $length <= $maximum;
}

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_password(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

function valid_http_url(string $url): bool
{
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true);
}

function valid_year(int $year): bool
{
    return $year >= 1000 && $year <= 9999;
}

function valid_iso_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        return false;
    }

    $errors = DateTimeImmutable::getLastErrors();

    return $errors === false
        || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
}

function valid_date_order(?string $startDate, ?string $endDate): bool
{
    if ($startDate === null || $startDate === '' || $endDate === null || $endDate === '') {
        return true;
    }

    return valid_iso_date($startDate)
        && valid_iso_date($endDate)
        && $endDate >= $startDate;
}

function valid_metric(float $metric): bool
{
    return $metric >= 0.0 && $metric <= 1.0;
}

function valid_experiment_splits(?float $train, ?float $validation, ?float $test): bool
{
    if ($train === null && $validation === null && $test === null) {
        return true;
    }

    if ($train === null || $validation === null || $test === null) {
        return false;
    }

    foreach ([$train, $validation, $test] as $split) {
        if ($split < 0.0 || $split > 100.0) {
            return false;
        }
    }

    return abs(($train + $validation + $test) - 100.0) <= 0.01;
}
