<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    // Check if database connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    // Check if this is for a delivery or regular products
    $delivery_id = isset($_GET['delivery_id']) ? trim($_GET['delivery_id']) : null;
    $deliveryInfo = null;
    $products = [];

    if ($delivery_id) {
        // Get delivery information
        $deliverySQL = "SELECT d.*, s.name as supplierName 
                        FROM delivery d 
                        LEFT JOIN supplier s ON d.supplierId = s.id 
                        WHERE d.id = ?";
        $stmt = $pdo->prepare($deliverySQL);
        $stmt->execute([$delivery_id]);
        $deliveryInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$deliveryInfo) {
            throw new Exception("Livraison non trouvée");
        }

        // Get products from delivery with quantities
        $productsSQL = "SELECT p.id, p.name, p.code, p.sellingPrice, p.expiryDate, di.quantity
                       FROM delivery_items di
                       JOIN product p ON di.productId = p.id
                       WHERE di.deliveryId = ?
                       ORDER BY p.name ASC";
        $stmt = $pdo->prepare($productsSQL);
        $stmt->execute([$delivery_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Regular product list with filters
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
        $supplierFilter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;

        $whereClause = '';
        $params = [];
        $conditions = [];
        
        if (!empty($search)) {
            $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.code LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if ($categoryFilter > 0) {
            $conditions[] = "p.categoryId = ?";
            $params[] = $categoryFilter;
        }
        
        if ($supplierFilter > 0) {
            $conditions[] = "p.supplierId = ?";
            $params[] = $supplierFilter;
        }
        
        if (!empty($conditions)) {
            $whereClause = "WHERE " . implode(" AND ", $conditions);
        }

        // Get all products (or filtered products) with quantity = 1 for each
        $productsSQL = "SELECT p.id, p.name, p.code, p.sellingPrice, p.expiryDate, 1 as quantity
                       FROM product p 
                       $whereClause 
                       ORDER BY p.name ASC";
        $stmt = $pdo->prepare($productsSQL);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($products === false) {
        $products = [];
    }

    // Calculate total labels (sum of quantities)
    $totalLabels = 0;
    foreach ($products as $product) {
        $totalLabels += intval($product['quantity']);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $products = [];
    $totalLabels = 0;
}

function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' XAF';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression des Codes-Barres<?php echo $delivery_id ? ' - Livraison #' . htmlspecialchars($delivery_id) : ''; ?> - PharmaSys</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: var(--ds-surface-alt);
            padding: 20px;
        }

        .no-print {
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .delivery-header {
            background: var(--ds-green);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .delivery-header h1 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .delivery-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .delivery-detail-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 6px;
        }

        .delivery-detail-label {
            font-size: 0.75rem;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .delivery-detail-value {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-print {
            background: #3b82f6;
            color: white;
        }

        .btn-print:hover {
            background: #2563eb;
        }

        .btn-back {
            background: var(--ds-text-400);
            color: white;
        }

        .btn-back:hover {
            background: var(--ds-text-600);
        }

        .btn-secondary {
            background: #8b5cf6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7c3aed;
        }

        .info-box {
            margin-top: 15px;
            padding: 15px;
            background: #dbeafe;
            border-radius: 6px;
            color: #1e40af;
        }

        .info-box.delivery-info {
            background: #f3e8ff;
            color: #6b21a8;
        }

        #debug {
            background: #fef3c7;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 12px;
            font-family: monospace;
        }

        .barcode-container {
            background: white;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(5, 38mm);
            grid-template-rows: repeat(13, 21.2mm);
            gap: 0;
            width: 190mm;
            margin: 0 auto;
        }

        .barcode-label {
            border: 0.5px solid var(--ds-border);
            border-radius: 0;
            padding: 1mm 1mm 0.5mm 1mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            background: white;
            width: 38mm;
            height: 21.2mm;
            page-break-inside: avoid;
            overflow: hidden;
            position: relative;
            gap: 0.2mm;
        }

        .delivery-badge {
            position: absolute;
            top: 1px;
            right: 1px;
            background: #8b5cf6;
            color: white;
            font-size: 5px;
            padding: 0.5px 3px;
            border-radius: 2px;
            font-weight: 700;
            z-index: 10;
        }

        .product-name {
            font-size: 6.5px;
            font-weight: 700;
            color: var(--ds-text-900);
            text-align: center;
            margin: 0;
            padding: 0 0.5mm;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            width: 100%;
            line-height: 1;
            max-height: 13px;
            flex-shrink: 0;
        }

        .barcode-section {
            display: flex;
            flex-direction: row;
            width: 100%;
            align-items: center;
            gap: 0.3mm;
            margin: 0.2mm 0;
            padding: 0;
            flex-shrink: 0;
        }

        .product-code-text {
            font-size: 5px;
            color: var(--ds-text-900);
            font-family: monospace;
            font-weight: 600;
            writing-mode: vertical-lr;
            transform: rotate(180deg);
            margin: 0;
            padding: 0;
            white-space: nowrap;
            flex-shrink: 0;
            height: 16px;
            display: flex;
            align-items: center;
        }

        .barcode-svg {
            height: 16px;
            width: auto;
            display: block;
            margin: 0;
            padding: 0;
            flex: 1;
        }

        .product-info {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 2px;
            margin: 0;
            padding: 0 0.5mm;
            flex-shrink: 0;
        }

        .product-price {
            font-size: 7px;
            font-weight: 700;
            color: var(--ds-green);
            text-align: center;
            white-space: nowrap;
            margin: 0;
            padding: 0;
        }

        .product-expiry {
            font-size: 5px;
            color: var(--ds-text-400);
            text-align: center;
            white-space: nowrap;
            margin: 0;
            padding: 0;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            #debug {
                display: none !important;
            }

            body {
                padding: 0;
                margin: 0;
                background: white;
            }

            .barcode-container {
                padding: 0;
                gap: 0;
                width: 190mm;
            }

            .barcode-label {
                border: 0.3px solid #999;
                border-radius: 0;
                padding: 1mm 1mm 0.5mm 1mm;
                width: 38mm;
                height: 21.2mm;
                margin: 0;
                gap: 0.2mm;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }

        @media screen and (max-width: 768px) {
            .barcode-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .barcode-label {
                width: auto;
                height: auto;
                min-height: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <?php if ($delivery_id && $deliveryInfo): ?>
        <div class="delivery-header">
            <h1>
                <span>📦</span>
                Codes-Barres - Livraison #<?php echo htmlspecialchars($delivery_id); ?>
            </h1>
            <div class="delivery-details">
                <div class="delivery-detail-item">
                    <div class="delivery-detail-label">Fournisseur</div>
                    <div class="delivery-detail-value"><?php echo htmlspecialchars($deliveryInfo['supplierName'] ?? 'N/A'); ?></div>
                </div>
                <div class="delivery-detail-item">
                    <div class="delivery-detail-label">Date de Livraison</div>
                    <div class="delivery-detail-value"><?php echo date('d/m/Y', strtotime($deliveryInfo['deliveryDate'])); ?></div>
                </div>
                <div class="delivery-detail-item">
                    <div class="delivery-detail-label">ASD</div>
                    <div class="delivery-detail-value"><?php echo number_format($deliveryInfo['ASD'], 0, ',', ' '); ?></div>
                </div>
                <div class="delivery-detail-item">
                    <div class="delivery-detail-label">Produits Uniques</div>
                    <div class="delivery-detail-value"><?php echo count($products); ?></div>
                </div>
                <div class="delivery-detail-item">
                    <div class="delivery-detail-label">Étiquettes Totales</div>
                    <div class="delivery-detail-value"><?php echo $totalLabels; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="toolbar">
            <button onclick="window.print()" class="btn btn-print">
                🖨️ Imprimer les Codes-Barres
            </button>
            <?php if ($delivery_id): ?>
            <a href="delivery_items.php?id=<?php echo htmlspecialchars($delivery_id); ?>" class="btn btn-secondary">
                ← Retour à la Livraison
            </a>
            <?php else: ?>
            <a href="products.php" class="btn btn-back">
                ← Retour aux Produits
            </a>
            <?php endif; ?>
        </div>

        <div class="info-box <?php echo $delivery_id ? 'delivery-info' : ''; ?>">
            <strong>ℹ️ Information:</strong>
            <ul style="margin-left: 20px; margin-top: 10px;">
                <?php if ($delivery_id): ?>
                <li>Livraison #<?php echo htmlspecialchars($delivery_id); ?> - <?php echo count($products); ?> produit(s) différent(s)</li>
                <li><?php echo $totalLabels; ?> étiquette(s) au total (basé sur les quantités)</li>
                <li>Chaque étiquette porte le numéro de livraison</li>
                <?php else: ?>
                <li><?php echo count($products); ?> produit(s) sélectionné(s) pour l'impression</li>
                <li>1 étiquette par produit</li>
                <?php endif; ?>
                <li><strong>Format FRAMD-110:</strong> 5 étiquettes par ligne × 13 lignes = <strong>65 étiquettes par page A4</strong></li>
                <li>Dimensions: 38mm × 21.2mm par étiquette</li>
            </ul>
        </div>
        <div id="debug" class="no-print"></div>
    </div>

    <?php if (!empty($products)): ?>
        <div class="barcode-container" id="barcodeContainer">
            <?php 
            $labelIndex = 0;
            foreach ($products as $product): 
                $quantity = intval($product['quantity']);
                for ($i = 0; $i < $quantity; $i++):
                    $labelIndex++;
            ?>
                <div class="barcode-label">
                    <?php if ($delivery_id): ?>
                    <div class="delivery-badge">#<?php echo htmlspecialchars($delivery_id); ?></div>
                    <?php endif; ?>
                    
                    <div class="product-name" title="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </div>
                    
                    <div class="barcode-section">
                        <div class="product-code-text"><?php echo htmlspecialchars($product['code']); ?></div>
                        <svg class="barcode-svg" id="barcode-<?php echo $product['id']; ?>-<?php echo $i; ?>"></svg>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-price"><?php echo formatPrice($product['sellingPrice']); ?></div>
                        <?php if (!empty($product['expiryDate'])): ?>
                            <div class="product-expiry">
                                Exp: <?php echo date('d/m/y', strtotime($product['expiryDate'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php 
                endfor;
            endforeach; 
            ?>
        </div>
    <?php else: ?>
        <div class="no-products">
            <h3>❌ Aucun produit à imprimer</h3>
            <?php if ($delivery_id): ?>
                <p>Cette livraison ne contient aucun produit.</p>
                <p><a href="delivery_items.php?id=<?php echo htmlspecialchars($delivery_id); ?>" style="color: #3b82f6;">Retour à la livraison</a></p>
            <?php else: ?>
                <p>Veuillez retourner à la liste des produits et réessayer.</p>
                <p><a href="products.php" style="color: #3b82f6;">Retour aux produits</a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        const debugDiv = document.getElementById('debug');
        const isDelivery = <?php echo $delivery_id ? 'true' : 'false'; ?>;
        
        function log(message) {
            console.log(message);
            if (debugDiv) {
                debugDiv.innerHTML += message + '<br>';
            }
        }
        
        log('=== Script Started ===');
        log('Mode: ' + (isDelivery ? 'Livraison' : 'Produits normaux'));
        log('Format: FRAMD-110 (5 columns × 13 rows = 65 labels per page)');
        
        if (typeof JsBarcode === 'undefined') {
            log('ERROR: JsBarcode library not loaded!');
            alert('Erreur: La bibliothèque JsBarcode n\'a pas pu être chargée.');
        } else {
            log('✓ JsBarcode library loaded successfully');
        }
        
        window.addEventListener('load', function() {
            log('Page fully loaded, starting barcode generation...');
            
            let successCount = 0;
            let errorCount = 0;
            
            const products = [
                <?php 
                foreach ($products as $product): 
                    $quantity = intval($product['quantity']);
                    for ($i = 0; $i < $quantity; $i++):
                ?>
                {
                    id: '<?php echo $product['id']; ?>',
                    index: <?php echo $i; ?>,
                    code: '<?php echo addslashes($product['code']); ?>',
                    name: '<?php echo addslashes($product['name']); ?>'
                },
                <?php 
                    endfor;
                endforeach; 
                ?>
            ];
            
            log(`Total labels to process: ${products.length}`);
            
            products.forEach((product, labelNum) => {
                try {
                    const element = document.getElementById(`barcode-${product.id}-${product.index}`);
                    
                    if (!element) {
                        log(`ERROR: Element not found for product ${product.id}-${product.index}`);
                        errorCount++;
                        return;
                    }
                    
                    log(`Processing ${labelNum + 1}/${products.length}: ${product.name} (${product.code})`);
                    
                    JsBarcode(element, product.code, {
                        format: "CODE128",
                        width: 1.1,
                        height: 16,
                        displayValue: false,
                        margin: 0,
                        fontSize: 5,
                        background: "#ffffff",
                        lineColor: "#000000"
                    });
                    
                    successCount++;
                    
                } catch (e) {
                    log(`ERROR generating barcode for ${product.name}: ${e.message}`);
                    errorCount++;
                }
            });
            
            log(`=== Generation Complete ===`);
            log(`✓ Success: ${successCount}`);
            log(`✗ Errors: ${errorCount}`);
            
            if (errorCount > 0) {
                alert(`Attention: ${errorCount} code(s)-barres n'ont pas pu être générés.`);
            } else {
                log('✓ All barcodes generated successfully!');
                setTimeout(() => {
                    if (debugDiv) {
                        debugDiv.style.display = 'none';
                    }
                }, 3000);
            }
        });
    </script>
</body>
</html>
<?php
} else {
    header("Location: ../login.php");
    exit();
}
?>