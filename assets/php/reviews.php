<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function character_count(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? PHP_INT_MAX : $count;
}

function allow_review_submission(string $ipAddress): bool
{
    $filePath = sys_get_temp_dir() . '/swe-review-' . hash('sha256', __DIR__ . '|' . $ipAddress) . '.json';
    $file = @fopen($filePath, 'c+');
    if ($file === false || !flock($file, LOCK_EX)) {
        if (is_resource($file)) {
            fclose($file);
        }
        return false;
    }

    @chmod($filePath, 0600);
    $contents = stream_get_contents($file);
    $state = $contents === false ? [] : json_decode($contents, true);
    if (!is_array($state) || !isset($state['started'], $state['count']) || time() - (int) $state['started'] >= 900) {
        $state = ['started' => time(), 'count' => 0];
    }

    if ((int) $state['count'] >= 5) {
        flock($file, LOCK_UN);
        fclose($file);
        return false;
    }

    $state['count'] = (int) $state['count'] + 1;
    $encoded = json_encode($state);
    $saved = $encoded !== false && rewind($file) && ftruncate($file, 0)
        && fwrite($file, $encoded) === strlen($encoded) && fflush($file);
    flock($file, LOCK_UN);
    fclose($file);

    return $saved;
}

function read_reviews(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $file = @fopen($path, 'rb');
    if ($file === false || !flock($file, LOCK_SH)) {
        if (is_resource($file)) {
            fclose($file);
        }
        throw new RuntimeException('Review storage is unavailable.');
    }

    $contents = stream_get_contents($file);
    flock($file, LOCK_UN);
    fclose($file);

    if ($contents === false || trim($contents) === '') {
        return [];
    }

    $reviews = json_decode($contents, true);
    if (!is_array($reviews)) {
        throw new RuntimeException('Review storage could not be read.');
    }

    return $reviews;
}

function store_review(string $path, array $review): array
{
    $file = @fopen($path, 'c+');
    if ($file === false || !flock($file, LOCK_EX)) {
        if (is_resource($file)) {
            fclose($file);
        }
        throw new RuntimeException('Review storage is unavailable.');
    }

    $contents = stream_get_contents($file);
    $reviews = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true);
    if (!is_array($reviews)) {
        flock($file, LOCK_UN);
        fclose($file);
        throw new RuntimeException('Review storage could not be read.');
    }

    array_unshift($reviews, $review);
    $encoded = json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || !rewind($file) || !ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
        flock($file, LOCK_UN);
        fclose($file);
        throw new RuntimeException('Review could not be saved.');
    }

    flock($file, LOCK_UN);
    fclose($file);
    return $reviews;
}

$storagePath = __DIR__ . '/reviews.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    try {
        respond(200, ['reviews' => read_reviews($storagePath)]);
    } catch (RuntimeException $error) {
        respond(500, ['error' => $error->getMessage()]);
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    respond(405, ['error' => 'This method is not supported.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 12000) {
    respond(413, ['error' => 'The review is too long.']);
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || strlen($rawInput) > 12000) {
    respond(413, ['error' => 'The review is too long.']);
}

$input = json_decode($rawInput, true);
if (!is_array($input)) {
    respond(400, ['error' => 'Please submit a valid review.']);
}

$name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : '';
$reviewText = isset($input['review']) && is_string($input['review']) ? trim($input['review']) : '';
$rating = filter_var($input['rating'] ?? null, FILTER_VALIDATE_INT);
$website = isset($input['website']) && is_string($input['website']) ? trim($input['website']) : '';

if ($website !== '') {
    respond(400, ['error' => 'Please submit a valid review.']);
}

if ($name === '') {
    $name = 'Anonymous';
}

if (character_count($name) > 80 || $reviewText === '' || character_count($reviewText) > 2000) {
    respond(422, ['error' => 'Please enter a name under 80 characters and a review under 2,000 characters.']);
}

if ($rating === false || $rating < 1 || $rating > 5) {
    respond(422, ['error' => 'Please choose a rating from 1 to 5 stars.']);
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!allow_review_submission($ipAddress)) {
    respond(429, ['error' => 'Too many reviews were submitted from this connection. Please try again later.']);
}

$newReview = [
    'name' => $name,
    'rating' => $rating,
    'review' => $reviewText,
    'createdAt' => gmdate('c'),
];

try {
    $reviews = store_review($storagePath, $newReview);
    respond(201, ['review' => $newReview, 'reviews' => $reviews]);
} catch (RuntimeException $error) {
    respond(500, ['error' => $error->getMessage()]);
}