<?php
// CORS para dev (ajusta en prod)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Router simple
if ($path === '/api/products') {
  require __DIR__ . '/../src/products.php';
  exit;
}

if ($path === '/api/catalog/pdf') {
  require __DIR__ . '/../src/pdf.php';
  exit;
}

http_response_code(404);
header("Content-Type: application/json");
echo json_encode(["error" => "Not found", "path" => $path]);
