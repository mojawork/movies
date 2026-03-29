<?php
// index.php

echo "<h1>Service List</h1>";
echo "<ul>";
echo "<li><a href='index.php'>Home (index.php)</a></li>";
echo "<li><a href='api/movies.php'>Movies API (List/Details)</a></li>";
echo "<li><a href='api/movies.php?id=1'>Example: Details for ID 1</a></li>";
echo "<li><strong>New:</strong> Add Movie via POST to <code>api/add_movie.php</code> (e.g., <code>curl -X POST -d 'title=John Wick' http://localhost:8000/api/add_movie.php</code>)</li>";
echo "<li><a href='movies.json'>Movies JSON (Legacy)</a></li>";
echo "<li><strong>Action:</strong> <form action='api/clear_db.php' method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to clear the database?\");'><button type='submit'>Clear Database</button></form></li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Add a Movie</h3>";
echo "<form action='api/add_movie.php' method='POST'>";
echo "<input type='text' name='title' placeholder='Movie Title' required>";
echo "<button type='submit'>Add Movie</button>";
echo "</form>";

echo "<hr>";
echo "Hello, World! This is a PHP server in a Docker container.";

// PHPInfo output
// phpinfo();
