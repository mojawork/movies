<?php
// src/api/add_movie.php

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
/** @var PDO $pdo */
// TMDB config and client might be used for automatic detail fetching later
$tmdbConfig = require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../includes/tmdb_client.php';

// Accept both POST and JSON input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$title = isset($input['title']) ? trim($input['title']) : (isset($_POST['title']) ? trim($_POST['title']) : null);
if ($title === '') $title = null; // Ensure empty string is null

$barcode = isset($input['barcode']) ? trim($input['barcode']) : (isset($_POST['barcode']) ? trim($_POST['barcode']) : null);
$year = isset($input['year']) ? trim($input['year']) : (isset($_POST['year']) ? trim($_POST['year']) : null);

if (empty($title) && empty($barcode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Title or Barcode is required.']);
    exit;
}

try {
    // Optional: Try to find a TMDB ID for this title or barcode automatically
    $tmdbId = null;
    $mediaType = 'movie'; // Default
    $director = null;
    $coverUrl = null;

    if (!empty($tmdbConfig['api_key']) || !empty($tmdbConfig['token'])) {
        if (!empty($barcode)) {
            $tmdbMovie = searchTmdbByBarcode($barcode, $tmdbConfig);
            if ($tmdbMovie) {
                $tmdbId = $tmdbMovie['id'];
                $mediaType = 'movie';
                // If title is missing, use the one from TMDB
                if (empty($title)) {
                    $title = $tmdbMovie['title'];
                }
                // Try to get cover from TMDB details
                $details = fetchTmdbData($tmdbId, $tmdbConfig);
                if ($details && !empty($details['poster_path'])) {
                    $coverUrl = "https://image.tmdb.org/t/p/w500" . $details['poster_path'];
                }
            } else {
                // Fallback: search for a title via external services
                $externalData = searchExternalTitleByBarcode($barcode, $tmdbConfig, $year);
                if ($externalData) {
                    $externalTitle = $externalData['title'];
                    $mediaType = $externalData['type'];
                    $director = isset($externalData['author']) ? $externalData['author'] : null;
                    $coverUrl = isset($externalData['cover_url']) ? $externalData['cover_url'] : null;

                    // ONLY search TMDB if it's NOT a book
                    if ($mediaType !== 'book') {
                        // Use this found title to search TMDB again
                        $titleSearch = urlencode($externalTitle);
                        $yearQuery = !empty($year) ? "&year=" . urlencode($year) : "";
                        $token = isset($tmdbConfig['token']) ? $tmdbConfig['token'] : null;
                        $apiKey = isset($tmdbConfig['api_key']) ? $tmdbConfig['api_key'] : null;
                        
                        if ($token) {
                            $url = "https://api.themoviedb.org/3/search/movie?query={$titleSearch}{$yearQuery}&language=de-DE";
                            $opts = [
                                'http' => [
                                    'method' => 'GET',
                                    'header' => [
                                        'Authorization: Bearer ' . $token,
                                        'Content-Type: application/json'
                                    ]
                                ]
                            ];
                            $context = stream_context_create($opts);
                            $response = @file_get_contents($url, false, $context);
                        } elseif ($apiKey) {
                            $url = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$titleSearch}{$yearQuery}&language=de-DE";
                            $response = @file_get_contents($url);
                        } else {
                            $response = false;
                        }

                        if ($response !== false) {
                            $data = json_decode($response, true);
                            if (!empty($data['results'][0]['id'])) {
                                $tmdbId = $data['results'][0]['id'];
                                if (empty($title)) {
                                    $title = $data['results'][0]['title'];
                                }
                                // If we found a TMDB ID, get the official poster
                                $details = fetchTmdbData($tmdbId, $tmdbConfig);
                                if ($details && !empty($details['poster_path'])) {
                                    $coverUrl = "https://image.tmdb.org/t/p/w500" . $details['poster_path'];
                                }
                            }
                        }
                    }
                    
                    // Fallback title if TMDB search failed or skipped
                    if (empty($title)) {
                        $title = $externalTitle;
                    }
                }
            }
        }
        
        // If still no tmdbId and title is available, and it's not a book, try title search
        if (!$tmdbId && !empty($title) && $mediaType !== 'book') {
            $token = isset($tmdbConfig['token']) ? $tmdbConfig['token'] : null;
            $apiKey = isset($tmdbConfig['api_key']) ? $tmdbConfig['api_key'] : null;
            $encodedTitle = urlencode($title);
            $yearQuery = !empty($year) ? "&year=" . urlencode($year) : "";
            
            if ($token) {
                $url = "https://api.themoviedb.org/3/search/movie?query={$encodedTitle}{$yearQuery}&language=de-DE";
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => [
                            'Authorization: Bearer ' . $token,
                            'Content-Type: application/json'
                        ]
                    ]
                ];
                $context = stream_context_create($opts);
                $response = @file_get_contents($url, false, $context);
            } elseif ($apiKey) {
                $url = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$encodedTitle}{$yearQuery}&language=de-DE";
                $response = @file_get_contents($url);
            } else {
                $response = false;
            }

            if ($response !== false) {
                $data = json_decode($response, true);
                if (!empty($data['results'][0]['id'])) {
                    $tmdbId = $data['results'][0]['id'];
                    // If we found a TMDB ID, get the official poster
                    $details = fetchTmdbData($tmdbId, $tmdbConfig);
                    if ($details && !empty($details['poster_path'])) {
                        $coverUrl = "https://image.tmdb.org/t/p/w500" . $details['poster_path'];
                    }
                }
            }
        }
    }

    if (empty($title)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Film/Buch konnte nicht gefunden werden. Bitte gib zusätzlich den Titel an.',
            'barcode' => $barcode,
            'tmdb_found' => false
        ]);
        exit;
    }

    // Check if we should search even if title was manually provided
    if (!empty($title) && empty($tmdbId) && $mediaType !== 'book') {
        // ... (existing search logic already handles this if tmdbId is null)
    }

    // Insert the movie into the database
    $stmt = $pdo->prepare('INSERT INTO movies (title, barcode, tmdb_id, media_type, director, cover_url) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $barcode, $tmdbId, $mediaType, $director, $coverUrl]);
    
    $newId = $pdo->lastInsertId();

    $tmdbFound = !empty($tmdbId);

    echo json_encode([
        'success' => true,
        'message' => $tmdbFound ? 'Movie added successfully.' : 'Movie added with barcode, but TMDB details not found.',
        'id' => $newId,
        'title' => $title,
        'barcode' => $barcode,
        'tmdb_id' => $tmdbId,
        'tmdb_found' => $tmdbFound
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
