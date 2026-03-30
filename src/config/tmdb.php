<?php
$token = getenv('TMDB_TOKEN');
$apiKey = getenv('TMDB_API_KEY');
$googleApiKey = getenv('GOOGLE_API_KEY');

return [
    'token' => $token ?: null,
    'api_key' => $apiKey ?: null,
    'google_api_key' => $googleApiKey ?: null,
];
