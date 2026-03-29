<?php
// src/api/movies.php

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
$tmdbConfig = require_once __DIR__ . '/../config/tmdb.php';
require_once __DIR__ . '/../includes/tmdb_client.php';

// Check if an ID is provided in the URL parameter
if (isset($_GET['id'])) {
    $movieId = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT * FROM movies WHERE id = ?');
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch();
    
    if ($movie) {
        // If tmdb_details are already in the DB, decode them
        if (!empty($movie['tmdb_details'])) {
            $movie['tmdb_details'] = json_decode($movie['tmdb_details'], true);
        } else {
            // If tmdb_details in DB is NULL or empty, initialize field
            $movie['tmdb_details'] = null;
        }
        
        // If TMDB ID is present and NO details in DB (or missing language overviews), try to load additional data live
        if (!empty($movie['tmdb_id']) && (empty($movie['tmdb_details']) || !isset($movie['tmdb_details']['overview_en']))) {
            $tmdbDataDe = fetchTmdbData($movie['tmdb_id'], $tmdbConfig, 'de-DE');
            $tmdbDataEn = fetchTmdbData($movie['tmdb_id'], $tmdbConfig, 'en-US');
            
            $tmdbData = $tmdbDataDe ?: $tmdbDataEn; // Use either as base

            if ($tmdbData) {
                $movie['tmdb_details'] = [
                    'overview_de' => isset($tmdbDataDe['overview']) ? $tmdbDataDe['overview'] : 'Keine Beschreibung verfügbar.',
                    'overview_en' => isset($tmdbDataEn['overview']) ? $tmdbDataEn['overview'] : 'No description available.',
                    'poster_url' => isset($tmdbData['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $tmdbData['poster_path'] : null,
                    'tagline' => isset($tmdbData['tagline']) ? $tmdbData['tagline'] : '',
                    'runtime' => isset($tmdbData['runtime']) ? $tmdbData['runtime'] : 0
                ];
                
                // For backward compatibility or single overview field if needed
                $movie['tmdb_details']['overview'] = $movie['tmdb_details']['overview_de'];
                
                // Extract additional data if not already present in the database
                $updateFields = ['tmdb_details = ?'];
                $params = [json_encode($movie['tmdb_details'])];

                if (empty($movie['director']) && !empty($tmdbData['credits']['crew'])) {
                    foreach ($tmdbData['credits']['crew'] as $crewMember) {
                        if ($crewMember['job'] === 'Director') {
                            $movie['director'] = $crewMember['name'];
                            $updateFields[] = 'director = ?';
                            $params[] = $movie['director'];
                            break;
                        }
                    }
                }
                
                if (empty($movie['release_year']) && !empty($tmdbData['release_date'])) {
                    $movie['release_year'] = (int)substr($tmdbData['release_date'], 0, 4);
                    $updateFields[] = 'release_year = ?';
                    $params[] = $movie['release_year'];
                }

                if (empty($movie['genre']) && !empty($tmdbData['genres'])) {
                    $genreNames = array_map(function($g) { return $g['name']; }, $tmdbData['genres']);
                    $movie['genre'] = implode(', ', $genreNames);
                    $updateFields[] = 'genre = ?';
                    $params[] = $movie['genre'];
                }

                if (empty($movie['rating']) && !empty($tmdbData['vote_average'])) {
                    $movie['rating'] = $tmdbData['vote_average'];
                    $updateFields[] = 'rating = ?';
                    $params[] = $movie['rating'];
                }

                // Save the newly loaded details to the database
                $params[] = $movieId;
                $updateStmt = $pdo->prepare('UPDATE movies SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
                $updateStmt->execute($params);
            } else {
                // If no API key/token is present or the request failed
                if (empty($tmdbConfig['api_key']) && empty($tmdbConfig['token'])) {
                    $movie['tmdb_warning'] = "No valid API key or session token configured in the .env file.";
                }
            }
        }
        echo json_encode($movie);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Movie not found.']);
    }
} else {
    // List all movies
    $stmt = $pdo->query('SELECT * FROM movies');
    $movies = $stmt->fetchAll();
    
    // Also decode tmdb_details in the list and fill missing ones automatically
    foreach ($movies as &$m) {
        if (!empty($m['tmdb_details'])) {
            $m['tmdb_details'] = json_decode($m['tmdb_details'], true);
        } else {
            $m['tmdb_details'] = null;
            
            // AUTOMATIC FILLING: If tmdb_id exists but details are missing (or overview_en is missing), fetch them
            if (!empty($m['tmdb_id']) && (empty($m['tmdb_details']) || !isset($m['tmdb_details']['overview_en'])) && (!empty($tmdbConfig['api_key']) || !empty($tmdbConfig['token']))) {
                $tmdbDataDe = fetchTmdbData($m['tmdb_id'], $tmdbConfig, 'de-DE');
                $tmdbDataEn = fetchTmdbData($m['tmdb_id'], $tmdbConfig, 'en-US');
                
                $tmdbData = $tmdbDataDe ?: $tmdbDataEn;

                if ($tmdbData) {
                    $m['tmdb_details'] = [
                        'overview_de' => isset($tmdbDataDe['overview']) ? $tmdbDataDe['overview'] : 'Keine Beschreibung verfügbar.',
                        'overview_en' => isset($tmdbDataEn['overview']) ? $tmdbDataEn['overview'] : 'No description available.',
                        'poster_url' => isset($tmdbData['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $tmdbData['poster_path'] : null,
                        'tagline' => isset($tmdbData['tagline']) ? $tmdbData['tagline'] : '',
                        'runtime' => isset($tmdbData['runtime']) ? $tmdbData['runtime'] : 0
                    ];
                    
                    // Backward compatibility
                    $m['tmdb_details']['overview'] = $m['tmdb_details']['overview_de'];
                    
                    // Extract additional data if not already present in the database
                    $updateFields = ['tmdb_details = ?'];
                    $params = [json_encode($m['tmdb_details'])];

                    if (empty($m['director']) && !empty($tmdbData['credits']['crew'])) {
                        foreach ($tmdbData['credits']['crew'] as $crewMember) {
                            if ($crewMember['job'] === 'Director') {
                                $m['director'] = $crewMember['name'];
                                $updateFields[] = 'director = ?';
                                $params[] = $m['director'];
                                break;
                            }
                        }
                    }
                    
                    if (empty($m['release_year']) && !empty($tmdbData['release_date'])) {
                        $m['release_year'] = (int)substr($tmdbData['release_date'], 0, 4);
                        $updateFields[] = 'release_year = ?';
                        $params[] = $m['release_year'];
                    }

                    if (empty($m['genre']) && !empty($tmdbData['genres'])) {
                        $genreNames = array_map(function($g) { return $g['name']; }, $tmdbData['genres']);
                        $m['genre'] = implode(', ', $genreNames);
                        $updateFields[] = 'genre = ?';
                        $params[] = $m['genre'];
                    }

                    if (empty($m['rating']) && !empty($tmdbData['vote_average'])) {
                        $m['rating'] = $tmdbData['vote_average'];
                        $updateFields[] = 'rating = ?';
                        $params[] = $m['rating'];
                    }

                    // Save the newly loaded details to the database for future calls
                    $params[] = $m['id'];
                    $updateStmt = $pdo->prepare('UPDATE movies SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
                    $updateStmt->execute($params);
                }
            }
        }
    }
    
    echo json_encode($movies);
}
