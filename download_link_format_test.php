<?php

declare(strict_types=1);

function fail_test(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail_test($message);
    }
}

require_once __DIR__ . '/includes/functions.php';

if (!defined('BASE_URI')) {
    define('BASE_URI', 'http://localhost:8080/');
}

$html_link = make_download_link(['id' => 3]);
assert_true(str_contains($html_link, '&amp;id=3'), 'HTML download link should contain encoded ampersand.');

if (!function_exists('make_download_link_raw')) {
    fail_test('Missing function make_download_link_raw().');
}

$raw_link = make_download_link_raw(['id' => 3]);
assert_true(str_contains($raw_link, '&id=3'), 'Raw download link should contain plain ampersand.');
assert_true(!str_contains($raw_link, '&amp;id=3'), 'Raw download link should not contain HTML-encoded ampersand.');

fwrite(STDOUT, "PASS\n");
