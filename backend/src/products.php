<?php
header("Content-Type: application/json; charset=utf-8");


require __DIR__ . "/db.php";

$pdo = db();

// Trae productos + categoría
$sql = "
SELECT
  p.id, p.code, p.name, p.description,
  p.price, p.old_price,
  p.unit, p.presentation,
  p.product_type,
  p.image_url,
  p.active,
  c.name AS category
FROM product p
LEFT JOIN category c ON c.id = p.category_id
WHERE p.active = 1
ORDER BY c.name, p.name
";

$products = $pdo->query($sql)->fetchAll();

// Si es bolo, trae sus items
// (para no hacer mil queries, lo hacemos en 2 pasos)
$boloIds = array_map(fn($x) => $x["id"], array_filter($products, fn($p) => $p["product_type"] === "bolo"));

$boloItemsByProductId = [];

if (count($boloIds) > 0) {
  $in = implode(",", array_fill(0, count($boloIds), "?"));
  $stmt = $pdo->prepare("SELECT bolo_product_id, item_name, quantity, unit, sort_order
                         FROM bolo_item
                         WHERE bolo_product_id IN ($in)
                         ORDER BY bolo_product_id, sort_order");
  $stmt->execute($boloIds);
  $rows = $stmt->fetchAll();

  foreach ($rows as $r) {
    $pid = $r["bolo_product_id"];
    if (!isset($boloItemsByProductId[$pid])) $boloItemsByProductId[$pid] = [];
    $boloItemsByProductId[$pid][] = [
      "name" => $r["item_name"],
      "quantity" => $r["quantity"],
      "unit" => $r["unit"]
    ];
  }
}

// Normaliza a formato JSON cómodo para React
$out = array_map(function ($p) use ($boloItemsByProductId) {
  return [
    "id" => $p["id"],
    "code" => $p["code"],
    "name" => $p["name"],
    "category" => $p["category"],
    "description" => $p["description"],
    "price" => (float)$p["price"],
    "oldPrice" => $p["old_price"] !== null ? (float)$p["old_price"] : null,
    "unit" => $p["unit"],
    "presentation" => $p["presentation"],
    "type" => $p["product_type"], // normal | bolo
    "imageUrl" => $p["image_url"],
    "includes" => $p["product_type"] === "bolo" ? ($boloItemsByProductId[$p["id"]] ?? []) : []
  ];
}, $products);

echo json_encode($out, JSON_UNESCAPED_UNICODE);
