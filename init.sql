CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) DEFAULT NULL,
    barcode VARCHAR(255) DEFAULT NULL,
    director VARCHAR(255),
    release_year INT,
    genre VARCHAR(100),
    rating DECIMAL(3, 1),
    tmdb_id INT DEFAULT NULL,
    tmdb_details JSON DEFAULT NULL
);

INSERT INTO movies (title, director, release_year, genre, rating, tmdb_id, tmdb_details) VALUES
('Inception', 'Christopher Nolan', 2010, 'Sci-Fi', 8.8, 27205, NULL),
('The Matrix', 'Lana Wachowski, Lilly Wachowski', 1999, 'Sci-Fi', 8.7, 603, NULL),
('Interstellar', 'Christopher Nolan', 2014, 'Sci-Fi', 8.6, 157336, NULL);
