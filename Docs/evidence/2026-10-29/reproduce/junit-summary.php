<?php

$xml = simplexml_load_file(dirname(__DIR__).'/testing/phpunit-results.xml');
if (! $xml) throw new RuntimeException('Actual JUnit evidence is required.');
$suite = $xml->testsuite[0];
$summary = [];
foreach (['tests', 'assertions', 'failures', 'errors', 'skipped', 'time'] as $field) $summary[$field] = (float) $suite[$field];
$classes = [];
foreach ($suite->testsuite as $type) {
    foreach ($type->testsuite as $class) {
        $row = ['name' => (string) $class['name']];
        foreach (['tests', 'assertions', 'failures', 'errors', 'skipped'] as $field) $row[$field] = (int) $class[$field];
        $classes[] = $row;
    }
}
echo json_encode(['summary' => $summary, 'classes' => $classes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
