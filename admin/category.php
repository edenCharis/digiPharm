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

    $admin_id = $_SESSION['user_id'];
    $success_message = '';
    $error_message = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = trim($_POST['name']);
                    
                    if (!empty($name)) {
                        // Get the last ID from the database and increment
                        $stmt = $pdo->query("SELECT id FROM category ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
                        $lastId = $stmt->fetch(PDO::FETCH_COLUMN);
                        $id = $lastId ? (string)((int)$lastId + 1) : "1";
                        $insertSQL = "INSERT INTO category (id, name, createdAt, updatedAt) VALUES (?, ?, NOW(), NOW())";
                        $stmt = $pdo->prepare($insertSQL);
                        $result = $stmt->execute([$id, $name]);
                        
                        if ($result) {
                            $success_message = "Catégorie ajoutée avec succès.";
                        } else {
                            $error_message = "Erreur lors de l'ajout de la catégorie.";
                        }
                    } else {
                        $error_message = "Veuillez remplir tous les champs obligatoires.";
                    }
                    break;

                case 'edit':
                    $id = (string)($_POST['id']);
                    $name = trim($_POST['name']);
                    
                    if (!empty($name) && $id > 0) {
                        $updateSQL = "UPDATE category SET name = ?, updatedAt = NOW() WHERE id = ?";
                        $stmt = $pdo->prepare($updateSQL);
                        $result = $stmt->execute([$name, $id]);
                        
                        if ($result) {
                            $success_message = "Catégorie modifiée avec succès.";
                        } else {
                            $error_message = "Erreur lors de la modification de la catégorie.";
                        }
                    } else {
                        $error_message = "Données invalides pour la modification.";
                    }
                    break;

                case 'delete':
                    $id = (string)($_POST['id']);
                    
                    if ($id > 0) {
                        // Check if category is used in products
                        $checkSQL = "SELECT COUNT(*) as count FROM product WHERE categoryId = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$id]);
                        $checkResult = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($checkResult && $checkResult['count'] > 0) {
                            $error_message = "Impossible de supprimer cette catégorie car elle est utilisée par des produits.";
                        } else {
                            $deleteSQL = "DELETE FROM category WHERE id = ?";
                            $stmt = $pdo->prepare($deleteSQL);
                            $result = $stmt->execute([$id]);
                            
                            if ($result) {
                                $success_message = "Catégorie supprimée avec succès.";
                            } else {
                                $error_message = "Erreur lors de la suppression de la catégorie.";
                            }
                        }
                    }
                    break;
            }
        }
    }

    // Get categories with pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $whereClause = '';
    $params = [];
    
    if (!empty($search)) {
        $whereClause = "WHERE name LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm];
    }

    // Get total count
    $countSQL = "SELECT COUNT(*) as total FROM category $whereClause";
    $stmt = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCategories = $countResult ? $countResult['total'] : 0;
    $totalPages = ceil($totalCategories / $limit);

    // Get categories
    $categoriesSQL = "SELECT * FROM category $whereClause ORDER BY createdAt DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($categoriesSQL);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($categories === false) {
        $categories = [];
    }

    // Get category for editing (if requested)
    $editingCategory = null;
    if (isset($_GET['edit_id'])) {
        $editId = intval($_GET['edit_id']);
        $editSQL = "SELECT * FROM category WHERE id = ?";
        $stmt = $pdo->prepare($editSQL);
        $stmt->execute([$editId]);
        $editingCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper functions
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Il y a quelques secondes';
    if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Il y a ' . floor($time/3600) . 'h ' . floor(($time%3600)/60) . 'min';
    return 'Il y a ' . floor($time/86400) . ' jour(s)';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Gestion des Catégories</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .categories-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .categories-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .categories-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .category-filters {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            min-width: 150px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .btn-apply-filters {
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            height: fit-content;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-apply-filters:hover {
            background: #2563eb;
            color: white;
        }

        .category-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
        }

        .summary-card.total {
            border-left-color: #10b981;
        }

        .summary-card.active {
            border-left-color: #3b82f6;
        }

        .summary-card.recent {
            border-left-color: #f59e0b;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .categories-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add-category {
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-add-category:hover {
            background: #2563eb;
        }

        .section-content {
            padding: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        .category-name {
            font-weight: 600;
            color: #1f2937;
        }

        .category-date {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            width: 36px;
            height: 36px;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            margin: 0;
            list-style: none;
            padding: 0;
        }

        .page-item {
            display: flex;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            color: #374151;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .page-link:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-item.active .page-link {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            border-radius: 12px 12px 0 0;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1f2937;
            font-weight: 700;
        }

        .form-control {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .no-data i {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-export:hover {
            background: #4b5563;
        }

        @media (max-width: 768px) {
            .categories-container {
                padding: 1rem;
            }
            
            .category-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .category-summary {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
            }

            .data-table {
                font-size: 0.875rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'header.php'; ?>
            
            <!-- Content Area -->
            <main class="content-area">
                <!-- Page Header -->
                <div class="categories-header">
                    <h1 class="categories-title">
                        <i data-lucide="folder"></i>
                        Gestion des Catégories
                    </h1>
                </div>

                <!-- Alerts -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i data-lucide="check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i data-lucide="alert-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <form method="GET" class="category-filters">
                    <div class="filter-group">
                        <label for="search">Rechercher</label>
                        <input type="text" 
                               id="search"
                               name="search" 
                               placeholder="Nom de catégorie..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <button type="submit" class="btn-apply-filters">
                        <i data-lucide="search"></i>
                        Rechercher
                    </button>
                    
                    <?php if (!empty($search)): ?>
                        <a href="?" class="btn-apply-filters" style="background: #6b7280;">
                            <i data-lucide="x"></i>
                            Effacer
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Export Buttons -->
                <div class="export-buttons">
                    <button onclick="exportToCSV()" class="btn-export">
                        <i data-lucide="download"></i>
                        Exporter CSV
                    </button>
                    <button onclick="printReport()" class="btn-export">
                        <i data-lucide="printer"></i>
                        Imprimer
                    </button>
                </div>

                <!-- Categories Section -->
                <div class="categories-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i data-lucide="list"></i>
                            Liste des Catégories
                        </h2>
                        <button class="btn-add-category" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i data-lucide="plus"></i>
                            Ajouter Catégorie
                        </button>
                    </div>

                    <div class="section-content">
                        <?php if (!empty($categories)): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom</th>
                                            <th>Date Création</th>
                                            <th>Dernière MAJ</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                                <td class="category-name"><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td class="category-date"><?php echo timeAgo($category['createdAt']); ?></td>
                                                <td class="category-date"><?php echo timeAgo($category['updatedAt']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-action btn-edit" 
                                                                onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                                                title="Modifier">
                                                            <i data-lucide="edit"></i>
                                                        </button>
                                                        <button class="btn-action btn-delete" 
                                                                onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                                title="Supprimer">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination-info">
                                        Affichage de <?php echo ($offset + 1); ?> à <?php echo min($offset + $limit, $totalCategories); ?> 
                                        sur <?php echo $totalCategories; ?> catégories
                                    </div>
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                                                    <i data-lucide="chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
                                                    <i data-lucide="chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i data-lucide="folder"></i>
                                <h3>Aucune catégorie trouvée</h3>
                                <p>Commencez par ajouter une nouvelle catégorie.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="plus"></i>
                        Ajouter une Catégorie
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="categoryName" class="form-label">Nom de la Catégorie *</label>
                            <input type="text" class="form-control" id="categoryName" name="name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="edit"></i>
                        Modifier la Catégorie
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="editCategoryId">
                        <div class="mb-3">
                            <label for="editCategoryName" class="form-label">Nom de la Catégorie *</label>
                            <input type="text" class="form-control" id="editCategoryName" name="name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i data-lucide="alert-triangle"></i>
                        Confirmer la Suppression
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer la catégorie <strong id="deleteCategoryName"></strong> ?</p>
                    <p class="text-muted small">Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" id="deleteCategoryForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteCategoryId">
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i>
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Edit category function
        function editCategory(category) {
            document.getElementById('editCategoryId').value = category.id;
            document.getElementById('editCategoryName').value = category.name;
            
            const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
            editModal.show();
        }

        // Delete category function
        function deleteCategory(id, name) {
            document.getElementById('deleteCategoryId').value = id;
            document.getElementById('deleteCategoryName').textContent = name;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
        deleteModal.show();
    }

    // Export to CSV function
    function exportToCSV() {
        const table = document.querySelector('.data-table');
        let csv = [];
        const rows = table.querySelectorAll('tr');

        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                let cellText = cols[j].innerText.replace(/"/g, '""');
                row.push('"' + cellText + '"');
            }
            csv.push(row.join(','));
        }

        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');
        downloadLink.download = 'categories_' + new Date().toISOString().slice(0, 10) + '.csv';
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }

    // Print report function
    function printReport() {
        const printWindow = window.open('', '_blank');
        const table = document.querySelector('.data-table').outerHTML;
        const totalCategories = <?php echo $totalCategories; ?>;
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Rapport des Catégories - PharmaSys</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #1f2937; text-align: center; }
                    .header-info { text-align: center; margin-bottom: 30px; color: #6b7280; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                    th { background-color: #f9fafb; font-weight: bold; }
                    tr:nth-child(even) { background-color: #f9fafb; }
                    .footer { margin-top: 30px; text-align: center; color: #6b7280; font-size: 12px; }
                    .actions { display: none; }
                </style>
            </head>
            <body>
                <h1>Rapport des Catégories</h1>
                <div class="header-info">
                    <p>Date d'impression: ${new Date().toLocaleDateString('fr-FR')}</p>
                    <p>Total des catégories: ${totalCategories}</p>
                </div>
                ${table}
                <div class="footer">
                    <p>PharmaSys - Système de Gestion Pharmaceutique</p>
                </div>
                <style>
                    .action-buttons { display: none !important; }
                    th:last-child, td:last-child { display: none !important; }
                </style>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }

    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }, 5000);
        });

        // Initialize tooltips if needed
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Form validation
    document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
        const name = document.getElementById('categoryName').value.trim();
        
        if (!name) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
        }
    });

    document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
        const name = document.getElementById('editCategoryName').value.trim();
        
        if (!name) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
        }
    });

    // Search form auto-submit on Enter
    document.getElementById('search').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });

    // Clear form when modals are hidden
    document.getElementById('addCategoryModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('addCategoryForm').reset();
    });

    document.getElementById('editCategoryModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('editCategoryForm').reset();
    });

    // Responsive table handling
    function handleResponsiveTable() {
        const table = document.querySelector('.data-table');
        
        if (window.innerWidth < 768) {
            // Add mobile styling for better readability
            if (table) table.style.fontSize = '0.8rem';
        } else {
            if (table) table.style.fontSize = '';
        }
    }

    // Handle window resize
    window.addEventListener('resize', handleResponsiveTable);
    handleResponsiveTable(); // Initial call

    // Smooth scrolling for pagination
    document.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        });
    });

    // Enhanced search functionality
    let searchTimeout;
    document.getElementById('search').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 3 || query.length === 0) {
            searchTimeout = setTimeout(() => {
                // Auto-submit form for real-time search (optional)
                // this.form.submit();
            }, 500);
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K to focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('search').focus();
        }
        
        // Ctrl/Cmd + N to add new category
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const addModal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            addModal.show();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            });
        }
    });

    // Loading states for forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                
                // Re-enable after 5 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 5000);
            }
        });
    });

    // Confirm before navigation if form has unsaved changes
    let formChanged = false;
    
    document.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('change', function() {
            formChanged = true;
        });
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            formChanged = false; // Reset flag on submit
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            const message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
            e.returnValue = message;
            return message;
        }
    });

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Focus search input if no categories found
        <?php if (empty($categories) && empty($search)): ?>
            setTimeout(() => {
                document.getElementById('search').focus();
            }, 500);
        <?php endif; ?>
        
        // Highlight search terms in results
        <?php if (!empty($search)): ?>
            const searchTerm = <?php echo json_encode($search); ?>;
            if (searchTerm) {
                const regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                document.querySelectorAll('.category-name').forEach(element => {
                    element.innerHTML = element.innerHTML.replace(regex, '<mark>$1</mark>');
                });
            }
        <?php endif; ?>
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
