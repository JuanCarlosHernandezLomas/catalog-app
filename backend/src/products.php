<?php
header("Content-Type: application/json; charset=utf-8");

// ✅ Aquí normalmente haces query a MySQL y traes imageUrl de tu BD
$products = [];
for ($i = 1; $i <= 45; $i++) {
  $products[] = [
    "id" => "AB-" . str_pad($i, 3, "0", STR_PAD_LEFT),
    "name" => "Producto Abarrotes $i",
    "price" => 10 + $i,
    "oldPrice" => 15 + $i,
    "category" => ($i % 2 === 0) ? "Abarrotes" : "Bebidas",
    // ✅ solo URL (no se guarda imagen en el proyecto)
    "imageUrl" => "https://via.placeholder.com/600x450.png?text=Producto+$i"
  ];
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
