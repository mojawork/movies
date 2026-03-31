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
 * Returns array ['title' => string, 'type' => 'book'|'movie'|null, 'author' => string|null, 'cover_url' => string|null]
 */
function searchExternalTitleByBarcode($barcode, $config = [], $year = null) {
    $encodedBarcode = urlencode($barcode);
    $yearQuery = !empty($year) ? " " . urlencode($year) : "";
    
    // 1. Try Google Books (Excellent for ISBN/Books)
    if (!empty($config['google_api_key'])) {
        $googleKey = $config['google_api_key'];
        // Search by ISBN, optionally with year in general query if needed, 
        // but ISBN is usually specific. We'll stick to ISBN for precision.
        $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$encodedBarcode}&key={$googleKey}";
        $response = @file_get_contents($url);
        if ($response !== false) {
            $data = json_decode($response, true);
            if (!empty($data['items'][0]['volumeInfo']['title'])) {
                $info = $data['items'][0]['volumeInfo'];
                return [
                    'title' => $info['title'],
                    'type' => 'book',
                    'author' => !empty($info['authors']) ? implode(', ', $info['authors']) : null,
                    'cover_url' => !empty($info['imageLinks']['thumbnail']) ? $info['imageLinks']['thumbnail'] : (!empty($info['imageLinks']['smallThumbnail']) ? $info['imageLinks']['smallThumbnail'] : null)
                ];
            }
        }
    }

    // 2. Try Discogs (Good for media/DVDs)
    $url = "https://api.discogs.com/database/search?barcode={$encodedBarcode}";
    if (!empty($year)) {
        $url .= "&year=" . urlencode($year);
    }
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
            $result = $data['results'][0];
            // Discogs titles are often "Artist - Title", we try to clean it
            $title = $result['title'];
            $author = null;
            if (strpos($title, ' - ') !== false) {
                $parts = explode(' - ', $title);
                $title = trim(end($parts));
                $author = trim($parts[0]);
            } else {
                $title = trim($title);
            }
            return [
                'title' => $title,
                'type' => 'movie',
                'author' => $author,
                'cover_url' => !empty($result['cover_image']) ? $result['cover_image'] : null
            ];
        }
    }

    // 3. Try Open Library (Better for Books/ISBN, but sometimes has media)
    $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$encodedBarcode}&format=json&jscmd=data";
    $response = @file_get_contents($url);
    if ($response !== false) {
        $data = json_decode($response, true);
        $key = "ISBN:{$barcode}";
        if (!empty($data[$key]['title'])) {
            $info = $data[$key];
            $author = null;
            if (!empty($info['authors'])) {
                $authorNames = array_map(function($a) { return $a['name']; }, $info['authors']);
                $author = implode(', ', $authorNames);
            }
            return [
                'title' => $info['title'],
                'type' => 'book',
                'author' => $author,
                'cover_url' => !empty($info['cover']['large']) ? $info['cover']['large'] : (!empty($info['cover']['medium']) ? $info['cover']['medium'] : null)
            ];
        }
    }

    return null;
}
