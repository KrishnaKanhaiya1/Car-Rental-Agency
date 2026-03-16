<?php
declare(strict_types=1);

const APP_NAME = 'Car Rental Agency';
const APP_ENV = 'production';

function detect_base_path(): string
{
    $projectRoot = realpath(__DIR__ . '/..');
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;

    if ($projectRoot === false || $documentRoot === false) {
        return '';
    }

    $projectRootNormalized = str_replace('\\', '/', $projectRoot);
    $documentRootNormalized = str_replace('\\', '/', $documentRoot);

    if (DIRECTORY_SEPARATOR === '\\') {
        $projectRootNormalized = strtolower($projectRootNormalized);
        $documentRootNormalized = strtolower($documentRootNormalized);
    }

    if (strpos($projectRootNormalized, $documentRootNormalized) !== 0) {
        return '';
    }

    $relativePath = substr($projectRootNormalized, strlen($documentRootNormalized));
    return rtrim($relativePath, '/');
}

define('BASE_PATH', detect_base_path());
