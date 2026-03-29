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
