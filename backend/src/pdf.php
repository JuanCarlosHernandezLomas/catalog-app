<?php
require __DIR__ . '/../vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

// ======= 1) Obtener productos (simula BD) =======
$products = [];
for ($i = 1; $i <= 45; $i++) {
  $products[] = [
    "id" => "AB-" . str_pad($i, 3, "0", STR_PAD_LEFT),
    "name" => "Producto Abarrotes $i",
    "price" => 10 + $i,
    "oldPrice" => 15 + $i,
    "category" => ($i % 2 === 0) ? "Abarrotes" : "Bebidas",
    "imageUrl" => "https://via.placeholder.com/600x450.png?text=Producto+$i"
  ];
}

$storeName = "ABARROTES EL BUEN PRECIO";
$whatsapp  = "+52 449 123 4567";
$title     = "CATÁLOGO DE OFERTAS";

// ======= 2) Construir HTML vistoso =======
$html = buildCatalogHTML($storeName, $whatsapp, $title, $products);

// ======= 3) Generar PDF con Chrome headless =======
$pdfName = "catalogo-abarrotes.pdf";

$pdfBinary = Browsershot::html($html)
  ->format('A4')
  ->margins(8, 8, 8, 8)
  ->showBackground()
  ->waitUntilNetworkIdle()
  ->pdf();

// Descargar
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.$pdfName.'"');
echo $pdfBinary;
exit;

// ================== TEMPLATE HTML ==================
function buildCatalogHTML($storeName, $whatsapp, $title, $products) {
  $perPage = 9; // 3x3
  $pages = array_chunk($products, $perPage);
  $featured = $products[0] ?? null;

  ob_start(); ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title><?= htmlspecialchars($title) ?></title>
  <style>
    @page { size: A4; margin: 8mm; }
    *{ box-sizing:border-box; font-family: Arial, Helvetica, sans-serif; }
    body{ margin:0; background:#fff; color:#111; }

    .page{ page-break-after: always; }

    .header{
      display:flex; justify-content:space-between; align-items:flex-start;
      gap:12px; margin-bottom:12px;
    }
    .brandBox{ display:flex; gap:10px; align-items:center; }
    .logo{
      width:44px; height:44px; border-radius:12px;
      background:#111; color:#fff; display:flex; align-items:center;
      justify-content:center; font-size:22px; font-weight:900;
    }
    .storeName{ font-weight:900; font-size:14px; }
    .meta{ font-size:12px; opacity:.8; }

    .promo{ text-align:right; }
    .promoSmall{ font-size:12px; font-weight:900; }
    .promoBig{ font-size:34px; font-weight:900; color:#e11d48; line-height:1; }
    .promoOff{ font-size:14px; font-weight:800; opacity:.85; }

    .featured{
      display:grid; grid-template-columns: 1.3fr 1fr;
      gap:12px;
      background:#f7f7f7;
      border-radius:16px;
      padding:12px;
      margin-bottom:12px;
      border:1px solid #e6e6e6;
    }
    .featured img{
      width:100%; height:240px; object-fit:cover;
      border-radius:14px; background:#ddd;
    }
    .priceRow{ display:flex; gap:10px; align-items:baseline; }
    .price{ font-size:22px; font-weight:900; }
    .oldPrice{ font-size:14px; text-decoration:line-through; opacity:.55; }
    .fName{ margin-top:6px; font-size:18px; font-weight:900; }
    .fDesc{ margin-top:6px; font-size:12px; opacity:.85; }
    .sku{ margin-top:10px; font-size:12px; font-weight:900; }

    .grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; }
    .card{
      background:#fff; border-radius:16px; overflow:hidden;
      border:1px solid #e6e6e6;
    }
    .cardImg{ height:110px; background:#eee; }
    .cardImg img{ width:100%; height:100%; object-fit:cover; }
    .cardBody{ padding:10px; }
    .cPriceRow{ display:flex; gap:8px; align-items:baseline; }
    .cPrice{ font-weight:900; font-size:13px; }
    .cOld{ font-size:11px; text-decoration:line-through; opacity:.55; }
    .cName{ margin-top:6px; font-weight:900; font-size:12px; }
    .cSku{ margin-top:4px; font-size:11px; opacity:.75; }

    .footer{
      margin-top:12px; background:#111; color:#fff;
      border-radius:16px; padding:10px 12px;
      display:flex; justify-content:space-between; font-size:11px;
    }
  </style>
</head>
<body>

<!-- PORTADA -->
<div class="page">
  <div class="header">
    <div class="brandBox">
      <div class="logo">🛒</div>
      <div>
        <div class="storeName"><?= htmlspecialchars($storeName) ?></div>
        <div class="meta">📲 WhatsApp: <?= htmlspecialchars($whatsapp) ?></div>
      </div>
    </div>

    <div class="promo">
      <div class="promoSmall">OFERTAS</div>
      <div class="promoBig">SUPER SALE</div>
      <div class="promoOff"><?= htmlspecialchars($title) ?></div>
    </div>
  </div>

  <?php if ($featured): ?>
  <div class="featured">
    <img src="<?= htmlspecialchars($featured['imageUrl']) ?>" alt="">
    <div>
      <div class="priceRow">
        <div class="price">$<?= number_format($featured['price'], 2) ?> MXN</div>
        <div class="oldPrice">$<?= number_format($featured['oldPrice'], 2) ?> MXN</div>
      </div>
      <div class="fName"><?= htmlspecialchars($featured['name']) ?></div>
      <div class="fDesc">Pide por WhatsApp enviando el <b>código</b> del producto.</div>
      <div class="sku">Código: <?= htmlspecialchars($featured['id']) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="footer">
    <div>📍 Entregas en la colonia • 🕘 8am–9pm</div>
    <div>📲 <?= htmlspecialchars($whatsapp) ?> • Envíame el código</div>
  </div>
</div>

<!-- PÁGINAS DE PRODUCTOS -->
<?php foreach ($pages as $idx => $pageItems): ?>
<div class="page">
  <div class="header">
    <div class="brandBox">
      <div class="logo">🛒</div>
      <div>
        <div class="storeName">Catálogo • Página <?= $idx + 1 ?></div>
        <div class="meta">📲 WhatsApp: <?= htmlspecialchars($whatsapp) ?></div>
      </div>
    </div>
    <div class="promo">
      <div class="promoSmall">PRODUCTOS</div>
      <div class="promoOff">Envíame el código</div>
    </div>
  </div>

  <div class="grid">
    <?php foreach ($pageItems as $p): ?>
      <div class="card">
        <div class="cardImg">
          <img src="<?= htmlspecialchars($p['imageUrl']) ?>" alt="">
        </div>
        <div class="cardBody">
          <div class="cPriceRow">
            <div class="cPrice">$<?= number_format($p['price'], 2) ?> MXN</div>
            <div class="cOld">$<?= number_format($p['oldPrice'], 2) ?> MXN</div>
          </div>
          <div class="cName"><?= htmlspecialchars($p['name']) ?></div>
          <div class="cSku">Código: <?= htmlspecialchars($p['id']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="footer">
    <div>📲 <?= htmlspecialchars($whatsapp) ?></div>
    <div>💳 Efectivo/Transferencia</div>
  </div>
</div>
<?php endforeach; ?>

</body>
</html>
<?php
  return ob_get_clean();
}
