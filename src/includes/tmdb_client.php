<?php
// src/includes/tmdb_client.php

/**
 * Fetches additional movie data from the TMDB API
 */
function fetchTmdbData($tmdbId, $config, $language = 'de-DE') {
    $token = isset($config['token']) ? $config['token'] : null;
    $apiKey = isset($config['api_key']) ? $config['api_key'] : null;

    // Use token (Bearer Auth) if it's set
    if ($token) {
        $url = "https://api.themoviedb.org/3/movie/{$tmdbId}?language={$language}&append_to_response=credits";
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
        // Fallback to API Key in the URL if no token is available
        $url = "https://api.themoviedb.org/3/movie/{$tmdbId}?api_key={$apiKey}&language={$language}&append_to_response=credits";
        $response = @file_get_contents($url);
    } else {
        return null;
    }

    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Searches for a movie by its barcode (EAN/UPC) via TMDB search
 * Note: TMDB doesn't have a direct barcode search, but sometimes
 * it's in the title or results for a query search.
 */
function searchTmdbByBarcode($barcode, $config, $language = 'de-DE') {
    $token = isset($config['token']) ? $config['token'] : null;
    $apiKey = isset($config['api_key']) ? $config['api_key'] : null;
    $encodedBarcode = urlencode($barcode);

    if ($token) {
        $url = "https://api.themoviedb.org/3/search/movie?query={$encodedBarcode}&language={$language}";
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
        $url = "https://api.themoviedb.org/3/search/movie?api_key={$apiKey}&query={$encodedBarcode}&language={$language}";
        $response = @file_get_contents($url);
    } else {
        return null;
    }

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!empty($data['results'])) {
        return $data['results'][0];
    }

    return null;
}

/**
 * Fallback: Search for a title by barcode using other public APIs
 */
function searchExternalTitleByBarcode($barcode, $config = []) {
    $encodedBarcode = urlencode($barcode);
    
    // 1. Try Google Books (Excellent for ISBN/Books)
    if (!empty($config['google_api_key'])) {
        $googleKey = $config['google_api_key'];
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$encodedBarcode}&key={$googleKey}";
        $response = @file_get_contents($url);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (!empty($data['items'][0]['volumeInfo']['title'])) {
                return $data['items'][0]['volumeInfo']['title'];
            }
        }
    }

    // 2. Try Discogs (Good for media/DVDs)
    $url = "https://api.discogs.com/database/search?barcode={$encodedBarcode}";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: MovieScanner/1.0',
                'Content-Type: application/json'
            ]
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if (!empty($data['results'][0]['title'])) {
            // Discogs titles are often "Artist - Title", we try to clean it
            $title = $data['results'][0]['title'];
            if (strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title);
                return trim(end($parts));
            }
            return trim($title);
        }
    }

    // 2. Try Open Library (Better for Books/ISBN, but sometimes has media)
    $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$encodedBarcode}&format=json&jscmd=data";
    $response = @file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        $key = "ISBN:{$barcode}";
        if (!empty($data[$key]['title'])) {
            return $data[$key]['title'];
        }
    }

    return null;
}
