<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/db.php';

use Spatie\Browsershot\Browsershot;

$storeName = "ABARROTES EL BUEN PRECIO";
$whatsapp  = "+52 449 123 4567";
$title     = "CATÁLOGO DE OFERTAS";

try {
  $pdo = db();

  // 1) Traer productos + categoría
  $sql = "
    SELECT
      p.id, p.code, p.name, p.description,
      p.price, p.old_price,
      p.unit, p.presentation,
      p.product_type,
      p.image_url,
      c.name AS category
    FROM product p
    LEFT JOIN category c ON c.id = p.category_id
    WHERE p.active = 1
    ORDER BY c.name, p.name
  ";

  $rows = $pdo->query($sql)->fetchAll();

  // Normalizar a estructura para el template
  $products = array_map(function ($p) {
    return [
      "dbId" => (int)$p["id"],
      "code" => $p["code"],
      "name" => $p["name"],
      "category" => $p["category"] ?? "",
      "description" => $p["description"],
      "price" => (float)$p["price"],
      "oldPrice" => $p["old_price"] !== null ? (float)$p["old_price"] : null,
      "unit" => $p["unit"],
      "presentation" => $p["presentation"],
      "type" => $p["product_type"], // normal|bolo
      "imageUrl" => $p["image_url"] ?: "https://via.placeholder.com/600x450.png?text=Sin+imagen",
      "includes" => []
    ];
  }, $rows);

  // 2) Traer items de bolos en un solo query
  $boloDbIds = array_map(
    fn($x) => $x["dbId"],
    array_filter($products, fn($p) => $p["type"] === "bolo")
  );

  $boloItemsById = [];
  if (count($boloDbIds) > 0) {
    $in = implode(",", array_fill(0, count($boloDbIds), "?"));
    $stmt = $pdo->prepare("
      SELECT bolo_product_id, item_name, quantity, unit, sort_order
      FROM bolo_item
      WHERE bolo_product_id IN ($in)
      ORDER BY bolo_product_id, sort_order
    ");
    $stmt->execute($boloDbIds);
    $items = $stmt->fetchAll();

    foreach ($items as $it) {
      $pid = (int)$it["bolo_product_id"];
      if (!isset($boloItemsById[$pid])) $boloItemsById[$pid] = [];
      $boloItemsById[$pid][] = [
        "name" => $it["item_name"],
        "quantity" => $it["quantity"],
        "unit" => $it["unit"]
      ];
    }
  }

  // 3) Pegar includes al producto bolo
  foreach ($products as &$p) {
    if ($p["type"] === "bolo") {
      $p["includes"] = $boloItemsById[$p["dbId"]] ?? [];
    }
  }
  unset($p);

  // 4) Construir HTML
  $html = buildCatalogHTML($storeName, $whatsapp, $title, $products);

  // 5) Generar PDF
  $pdfName = "catalogo-abarrotes.pdf";

  $pdfBinary = Browsershot::html($html)
    ->format('A4')
    ->margins(8, 8, 8, 8)
    ->showBackground()
    ->waitUntilNetworkIdle()
    ->pdf();

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $pdfName . '"');
  echo $pdfBinary;
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  header("Content-Type: application/json; charset=utf-8");
  echo json_encode([
    "error" => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ================== TEMPLATE HTML ==================
function buildCatalogHTML($storeName, $whatsapp, $title, $products)
{

  // 0) Helper: slug seguro para IDs
  $slug = function ($text) {
    $text = trim((string)$text);
    $text = mb_strtolower($text, 'UTF-8');
    // reemplaza espacios por guión
    $text = preg_replace('/\s+/', '-', $text);
    // quita todo lo que no sea letra/numero/guion
    $text = preg_replace('/[^a-z0-9\-áéíóúñ]/u', '', $text);
    // limpia guiones dobles
    $text = preg_replace('/-+/', '-', $text);
    return $text ?: 'otros';
  };

  // 1) Producto destacado (el primero)
  $featured = $products[0] ?? null;

  // 2) Agrupar por categoría
  $byCategory = [];
  foreach ($products as $p) {
    $cat = $p['category'] ?: 'Otros';
    if (!isset($byCategory[$cat])) $byCategory[$cat] = [];
    $byCategory[$cat][] = $p;
  }

  // 3) Paginación por categoría (9 productos por página)
  $perPage = 9;

  ob_start(); ?>
  <!doctype html>
  <html>

  <head>
    <meta charset="utf-8" />
    <title><?= htmlspecialchars($title) ?></title>
    <style>
      @page {
        size: A4;
        margin: 8mm;
      }

      * {
        box-sizing: border-box;
        font-family: Arial, Helvetica, sans-serif;
      }

      body {
        margin: 0;
        color: #111;
      }

      /* Fondo general elegante */
      body {
        background:
          radial-gradient(800px 500px at 12% 10%, rgba(251, 191, 36, .28), transparent 60%),
          radial-gradient(900px 520px at 90% 12%, rgba(249, 115, 22, .22), transparent 62%),
          radial-gradient(700px 420px at 30% 85%, rgba(236, 72, 153, .14), transparent 58%),
          linear-gradient(180deg, #fffdf7, #fff7ed 55%, #ffffff);
      }

      .page {
        page-break-after: always;
        border-radius: 22px;
        padding: 10px;
        background:
          linear-gradient(180deg, rgba(255, 255, 255, .92), rgba(255, 255, 255, .86)),
          radial-gradient(circle at 20% 20%, rgba(249, 115, 22, .07) 0 6px, transparent 7px),
          radial-gradient(circle at 70% 25%, rgba(236, 72, 153, .07) 0 6px, transparent 7px),
          radial-gradient(circle at 40% 80%, rgba(34, 197, 94, .06) 0 6px, transparent 7px);
        border: 1px solid rgba(0, 0, 0, .05);
      }

      /* ===== HEADER PRO ===== */
      .header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
        padding: 10px 12px;
        border-radius: 18px;
        background: rgba(255, 255, 255, .9);
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 10px 25px rgba(0, 0, 0, .06);
      }

      .brandBox {
        display: flex;
        gap: 10px;
        align-items: center;
      }

      .logo {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(135deg, #111827, #0b1220);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        font-weight: 900;
        box-shadow: 0 10px 22px rgba(0, 0, 0, .22);
      }

      .storeName {
        font-weight: 900;
        font-size: 14px;
      }

      .meta {
        font-size: 12px;
        opacity: .82;
      }

      .promo {
        text-align: right;
      }

      .promoSmall {
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .08em;
        opacity: .85;
      }

      .promoBig {
        font-size: 34px;
        font-weight: 900;
        background: linear-gradient(90deg, #e11d48, #fb7185);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        line-height: 1;
      }

      .promoOff {
        font-size: 13px;
        font-weight: 900;
        opacity: .9;
      }

      /* ===== PORTADA / DESTACADO ===== */
      .featured {
        display: grid;
        grid-template-columns: 1.35fr 1fr;
        gap: 12px;
        background: rgba(255, 255, 255, .92);
        border-radius: 20px;
        padding: 12px;
        margin-bottom: 12px;
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 18px 35px rgba(0, 0, 0, .06);
      }

      .featured img {
        width: 100%;
        height: 250px;
        object-fit: cover;
        border-radius: 16px;
        background: #eaeaea;
        border: 1px solid rgba(0, 0, 0, .06);
      }

      .priceRow {
        display: flex;
        gap: 10px;
        align-items: baseline;
        flex-wrap: wrap;
        margin-top: 2px;
      }

      .price {
        font-size: 26px;
        font-weight: 900;
      }

      .oldPrice {
        font-size: 14px;
        text-decoration: line-through;
        opacity: .55;
      }

      .chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: linear-gradient(135deg, #111827, #0b1220);
        color: #fff;
        font-size: 11px;
        font-weight: 900;
        margin-top: 8px;
        box-shadow: 0 10px 18px rgba(0, 0, 0, .18);
      }

      .chip::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #fb7185;
      }

      .fName {
        margin-top: 10px;
        font-size: 18px;
        font-weight: 900;
      }

      .fMeta {
        margin-top: 6px;
        font-size: 12px;
        opacity: .9;
      }

      .sku {
        margin-top: 10px;
        display: inline-block;
        padding: 6px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 900;
        background: #f4f4ff;
        border: 1px solid rgba(99, 102, 241, .18);
      }

      /* ===== ÍNDICE ===== */
      .indexTitle {
        font-size: 30px;
        font-weight: 900;
        margin: 10px 0 16px;
        display: flex;
        gap: 10px;
        align-items: center;
      }

      .indexList {
        margin: 0;
        padding: 0;
        list-style: none;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, .06);
        background: rgba(255, 255, 255, .92);
        box-shadow: 0 18px 35px rgba(0, 0, 0, .06);
      }

      .indexItem {
        padding: 16px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid rgba(0, 0, 0, .06);
      }

      .indexItem:last-child {
        border-bottom: none;
      }

      .indexLeft {
        display: flex;
        align-items: center;
        gap: 10px;
      }

      .indexDot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        background: linear-gradient(135deg, #e11d48, #fb7185);
        box-shadow: 0 8px 14px rgba(225, 29, 72, .25);
      }

      .indexLink {
        text-decoration: none;
        color: #111;
        font-weight: 900;
        font-size: 16px;
      }

      .indexCount {
        font-size: 12px;
        font-weight: 900;
        padding: 6px 10px;
        border-radius: 999px;
        background: #f4f4ff;
        border: 1px solid rgba(99, 102, 241, .18);
        opacity: .95;
      }

      .indexHint {
        margin-top: 10px;
        font-size: 12px;
        opacity: .8;
      }

      /* ===== GRID + CARDS ===== */
      .grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
      }

      .card {
        border-radius: 20px;
        overflow: hidden;
        background: rgba(255, 255, 255, .95);
        border: 1px solid rgba(0, 0, 0, .06);
        box-shadow: 0 16px 30px rgba(0, 0, 0, .06);
        position: relative;
      }

      /* borde degradado */
      .card::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: 20px;
        padding: 1px;
        background: linear-gradient(135deg, rgba(225, 29, 72, .30), rgba(59, 130, 246, .22));
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
      }

      .cardImg {
        width: calc(100% - 20px);
        height: 140px;
        margin: 10px 10px 0;
        background: linear-gradient(180deg, #f3f4f6, #ffffff);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, .06);
      }

      .cardImg img {
        width: 100%;
        height: 100%;
        object-fit: contain;
      }

      /* contenido */
      .cardBody {
        padding: 10px 12px 12px;
      }

      .cTop {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        align-items: flex-start;
      }

      .cCat {
        font-size: 10px;
        font-weight: 900;
        padding: 5px 9px;
        border-radius: 999px;
        background: #111827;
        color: #fff;
        opacity: .95;
      }

      .cPriceRow {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        align-items: baseline;
        flex-wrap: wrap;
      }

      .cPrice {
        font-weight: 900;
        font-size: 16px;
      }

      .cOld {
        font-size: 11px;
        text-decoration: line-through;
        opacity: .55;
      }

      .cName {
        margin-top: 8px;
        font-weight: 900;
        font-size: 12.5px;
      }

      .cMeta {
        margin-top: 4px;
        font-size: 11px;
        opacity: .85;
      }

      .cSku {
        margin-top: 8px;
        font-size: 11px;
        opacity: .8;
        font-weight: 900;
      }

      /* Includes estilo tarjetita */
      .includes {
        margin-top: 10px;
        background: #f7f7ff;
        border-radius: 14px;
        padding: 9px 10px;
        font-size: 10.8px;
        border: 1px solid rgba(99, 102, 241, .18);
      }

      .includesTitle {
        font-weight: 900;
        margin-bottom: 4px;
      }

      .includes ul {
        margin: 0;
        padding-left: 16px;
      }

      .includes li {
        margin: 2px 0;
      }

      /* ===== Footer más pro ===== */
      .footer {
        margin-top: 12px;
        color: #fff;
        border-radius: 18px;
        padding: 10px 12px;
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        background: linear-gradient(135deg, #111827, #0b1220);
        box-shadow: 0 18px 35px rgba(0, 0, 0, .12);
      }

      /* Botón "Volver al índice" */
      .backBtn {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 999px;
        background: #111827;
        color: #fff !important;
        text-decoration: none;
        font-weight: 900;
        font-size: 12px;
        box-shadow: 0 10px 18px rgba(0, 0, 0, .15);
      }

      /* Para que el salto por ancla se vea arriba */
      .sectionAnchor {
        scroll-margin-top: 20px;
      }

      .card,
      .featured,
      .includes {
        break-inside: avoid;
        page-break-inside: avoid;
      }

      /* PERMITE que el grid se distribuya normal */
      .grid {
        break-inside: auto;
      }

      .header,
      .footer {
        break-inside: auto;
      }
    </style>
  </head>

  <body>

    <!-- 1) PORTADA -->
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
              <?php if (!is_null($featured['oldPrice'])): ?>
                <div class="oldPrice">$<?= number_format($featured['oldPrice'], 2) ?> MXN</div>
              <?php endif; ?>
            </div>

            <div class="chip"><?= htmlspecialchars($featured['category'] ?: 'PRODUCTO') ?></div>
            <div class="fName"><?= htmlspecialchars($featured['name']) ?></div>

            <div class="fMeta">
              <?= htmlspecialchars($featured['unit']) ?>
              <?php if (!empty($featured['presentation'])): ?>
                • <?= htmlspecialchars($featured['presentation']) ?>
              <?php endif; ?>
            </div>

            <div class="sku">Código: <?= htmlspecialchars($featured['code']) ?></div>

            <?php if ($featured['type'] === 'bolo' && !empty($featured['includes'])): ?>
              <div class="includes">
                <div class="includesTitle">Incluye:</div>
                <ul>
                  <?php foreach ($featured['includes'] as $it): ?>
                    <li><?= htmlspecialchars($it['name']) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="footer">
        <div>📍 Entregas en la colonia • 🕘 8am–9pm</div>
        <div>📲 <?= htmlspecialchars($whatsapp) ?> • Envíame el código</div>
      </div>
    </div>

    <!-- 2) ÍNDICE (clickeable) -->
    <div class="page" id="indice">
      <div class="header">
        <div class="brandBox">
          <div class="logo">📑</div>
          <div>
            <div class="storeName">ÍNDICE</div>
            <div class="meta">Toca una categoría para ir a sus productos</div>
          </div>
        </div>
        <div class="promo">
          <div class="promoSmall">CATÁLOGO</div>
          <div class="promoOff"><?= htmlspecialchars($storeName) ?></div>
        </div>
      </div>

      <div class="indexTitle">📌 Categorías</div>

      <ul class="indexList">
        <?php foreach ($byCategory as $cat => $_items): ?>
          <?php $catId = "cat-" . $slug($cat); ?>
          <li class="indexItem">
            <div class="indexLeft">
              <span class="indexDot"></span>
              <a class="indexLink" href="#<?= htmlspecialchars($catId) ?>">
                <?= htmlspecialchars($cat) ?>
              </a>
            </div>
            <span class="indexCount"><?= count($_items) ?> productos</span>
          </li>

        <?php endforeach; ?>
      </ul>

      <div class="indexHint">
        Nota: El PDF es interactivo. Los enlaces del índice funcionan al abrirlo en un lector de PDF (Chrome/Acrobat).
      </div>

      <div class="footer">
        <div>📲 <?= htmlspecialchars($whatsapp) ?></div>
        <div>Catálogo interactivo</div>
      </div>
    </div>

    <!-- 3) SECCIONES POR CATEGORÍA -->
    <?php foreach ($byCategory as $cat => $items): ?>
      <?php
      $catId = "cat-" . $slug($cat);
      $chunks = array_chunk($items, $perPage);
      ?>

      <?php foreach ($chunks as $pageIndex => $pageItems): ?>
        <div class="page">
          <!-- Anchor solo en la primera página de la categoría -->
          <?php if ($pageIndex === 0): ?>
            <div id="<?= htmlspecialchars($catId) ?>" class="sectionAnchor"></div>
          <?php endif; ?>

          <div class="header">
            <div class="brandBox">
              <div class="logo">🛒</div>
              <div>
                <div class="storeName"><?= htmlspecialchars($cat) ?></div>
                <div class="meta">📲 WhatsApp: <?= htmlspecialchars($whatsapp) ?></div>
              </div>
            </div>

            <div class="promo">
              <div class="promoSmall">PRODUCTOS</div>
              <div class="promoOff">
                <a class="backBtn" href="#indice">← Volver al índice</a>
              </div>
            </div>
          </div>

          <div class="grid">
            <?php foreach ($pageItems as $p): ?>
              <div class="card">
                <div class="cardImg">
                  <img src="<?= htmlspecialchars($p['imageUrl']) ?>" alt="">
                </div>
                <div class="cardBody">
                  <div class="cTop">
                    <div class="cCat"><?= htmlspecialchars($p['category'] ?: '') ?></div>
                  </div>

                  <div class="cPriceRow">
                    <div class="cPrice">$<?= number_format($p['price'], 2) ?> MXN</div>
                    <?php if (!is_null($p['oldPrice'])): ?>
                      <div class="cOld">$<?= number_format($p['oldPrice'], 2) ?> MXN</div>
                    <?php endif; ?>
                  </div>

                  <div class="cName"><?= htmlspecialchars($p['name']) ?></div>

                  <div class="cMeta">
                    <?= htmlspecialchars($p['unit']) ?>
                    <?php if (!empty($p['presentation'])): ?>
                      • <?= htmlspecialchars($p['presentation']) ?>
                    <?php endif; ?>
                  </div>

                  <div class="cSku">Código: <?= htmlspecialchars($p['code']) ?></div>

                  <?php if ($p['type'] === 'bolo' && !empty($p['includes'])): ?>
                    <div class="includes">
                      <div class="includesTitle">Incluye:</div>
                      <ul>
                        <?php foreach ($p['includes'] as $it): ?>
                          <li><?= htmlspecialchars($it['name']) ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>
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
    <?php endforeach; ?>

  </body>

  </html>
<?php
  return ob_get_clean();
}
