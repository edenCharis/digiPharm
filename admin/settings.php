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
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $admin_id = $_SESSION['user_id'];
    $success_message = $_SESSION['flash_success'] ?? '';
    $error_message   = $_SESSION['flash_error']   ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    // Create app_settings table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS app_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'textarea', 'image', 'color', 'number') DEFAULT 'text',
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->query($createTableSQL);

    // Default settings data
    $defaultSettings = [
        ['setting_key' => 'app_name', 'setting_value' => 'PharmaSys', 'setting_type' => 'text', 'description' => 'Nom de l\'application'],
        ['setting_key' => 'app_icon', 'setting_value' => 'pill', 'setting_type' => 'text', 'description' => 'Icône de l\'application (Lucide icon name)'],
        ['setting_key' => 'pharmacy_name', 'setting_value' => 'Pharmacie Centrale', 'setting_type' => 'text', 'description' => 'Nom de la pharmacie'],
        ['setting_key' => 'pharmacy_address', 'setting_value' => '123 Avenue de la Santé, Brazzaville', 'setting_type' => 'textarea', 'description' => 'Adresse de la pharmacie'],
        ['setting_key' => 'pharmacy_phone', 'setting_value' => '+242 06 123 45 67', 'setting_type' => 'text', 'description' => 'Téléphone de la pharmacie'],
        ['setting_key' => 'pharmacy_email', 'setting_value' => 'contact@pharmacie-centrale.cg', 'setting_type' => 'text', 'description' => 'Email de la pharmacie'],
        ['setting_key' => 'pharmacy_license', 'setting_value' => 'PH-2024-001', 'setting_type' => 'text', 'description' => 'Numéro de licence'],
        ['setting_key' => 'primary_color', 'setting_value' => '#3b82f6', 'setting_type' => 'color', 'description' => 'Couleur principale'],
        ['setting_key' => 'secondary_color', 'setting_value' => 'var(--ds-green)', 'setting_type' => 'color', 'description' => 'Couleur secondaire'],
        ['setting_key' => 'currency', 'setting_value' => 'FCFA', 'setting_type' => 'text', 'description' => 'Devise utilisée'],
        ['setting_key' => 'timezone', 'setting_value' => 'Africa/Brazzaville', 'setting_type' => 'text', 'description' => 'Fuseau horaire'],
        ['setting_key' => 'language', 'setting_value' => 'fr', 'setting_type' => 'text', 'description' => 'Langue par défaut'],
        ['setting_key' => 'low_stock_threshold', 'setting_value' => '10', 'setting_type' => 'number', 'description' => 'Seuil de stock faible'],
        ['setting_key' => 'company_description', 'setting_value' => 'Votre pharmacie de confiance depuis 1995. Nous nous engageons à fournir des soins de santé de qualité à notre communauté.', 'setting_type' => 'textarea', 'description' => 'Description de l\'entreprise'],
        ['setting_key' => 'working_hours', 'setting_value' => 'Lun-Ven: 8h-18h, Sam: 8h-14h', 'setting_type' => 'text', 'description' => 'Heures d\'ouverture'],
        ['setting_key' => 'sfec_environment', 'setting_value' => 'sandbox', 'setting_type' => 'text', 'description' => 'Environnement SFEC (sandbox ou production)'],
        ['setting_key' => 'sfec_taxpayer_niu', 'setting_value' => '', 'setting_type' => 'text', 'description' => 'NIU (Numéro d\'Identification Unique) du contribuable'],
        ['setting_key' => 'sfec_sciet', 'setting_value' => '', 'setting_type' => 'text', 'description' => 'Identifiant SCIET fourni par SFEC'],
        ['setting_key' => 'sfec_api_key', 'setting_value' => '', 'setting_type' => 'password', 'description' => 'Clé API SFEC obtenue via le portail e-Facture']
    ];

    // Insert default settings if they don't exist
    $checkSettingSQL = "SELECT COUNT(*) as count FROM app_settings WHERE setting_key = ?";
    $insertSettingSQL = "INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)";
    
    foreach ($defaultSettings as $setting) {
        $result = $db->fetch($checkSettingSQL, [$setting['setting_key']]);
        if ($result['count'] == 0) {
            $db->query($insertSettingSQL, [
                $setting['setting_key'], 
                $setting['setting_value'], 
                $setting['setting_type'], 
                $setting['description']
            ]);
        }
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    try {
                        $db->beginTransaction();
                        
                        $updateSQL = "UPDATE app_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                        
                        foreach ($_POST as $key => $value) {
                            if ($key !== 'action' && !empty($key)) {
                                $db->query($updateSQL, [trim($value), $key]);
                            }
                        }
                        
                        $db->commit();
                        $success_message = "Paramètres mis à jour avec succès.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
                    }
                    break;

                case 'reset_to_defaults':
                    try {
                        $db->beginTransaction();
                        
                        foreach ($defaultSettings as $setting) {
                            $resetSQL = "UPDATE app_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                            $db->query($resetSQL, [$setting['setting_value'], $setting['setting_key']]);
                        }
                        
                        $db->commit();
                        $success_message = "Paramètres réinitialisés aux valeurs par défaut.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = "Erreur lors de la réinitialisation : " . $e->getMessage();
                    }
                    break;
            }
        }
    if ($success_message) $_SESSION['flash_success'] = $success_message;
    if ($error_message)   $_SESSION['flash_error']   = $error_message;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
    }

    // Get current settings
    $settingsSQL = "SELECT * FROM app_settings ORDER BY 
                    CASE setting_key 
                        WHEN 'app_name' THEN 1
                        WHEN 'app_icon' THEN 2
                        WHEN 'pharmacy_name' THEN 3
                        WHEN 'pharmacy_address' THEN 4
                        WHEN 'pharmacy_phone' THEN 5
                        WHEN 'pharmacy_email' THEN 6
                        WHEN 'pharmacy_license' THEN 7
                        WHEN 'primary_color' THEN 8
                        WHEN 'secondary_color' THEN 9
                        ELSE 10 
                    END, setting_key";
    $settings = $db->fetchAll($settingsSQL);

    // Group settings by category
    $settingsGroups = [
        'Application' => ['app_name', 'app_icon', 'primary_color', 'secondary_color', 'language', 'timezone'],
        'Pharmacie' => ['pharmacy_name', 'pharmacy_address', 'pharmacy_phone', 'pharmacy_email', 'pharmacy_license', 'working_hours', 'company_description'],
        'Système' => ['currency', 'low_stock_threshold'],
        'Facturation Électronique (SFEC)' => ['sfec_environment', 'sfec_taxpayer_niu', 'sfec_sciet', 'sfec_api_key']
    ];

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $settings = [];
}

// Helper function to get setting value
function getSettingValue($settings, $key) {
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting['setting_value'];
        }
    }
    return '';
}

// Helper function to get setting by key
function getSetting($settings, $key) {
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting;
        }
    }
    return null;
}

// Popular Lucide icons for app icon selection
$iconOptions = [
    'pill' => 'Pilule',
    'cross' => 'Croix médicale',
    'heart-pulse' => 'Pouls cardiaque',
    'stethoscope' => 'Stéthoscope',
    'shield-plus' => 'Bouclier médical',
    'activity' => 'Activité',
    'plus-circle' => 'Plus cercle',
    'home' => 'Maison',
    'building' => 'Bâtiment',
    'briefcase-medical' => 'Mallette médicale'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSettingValue($settings, 'app_name'); ?> - Personnalisation</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .customization-container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 2.5rem;
        }

        .page-header {
            margin-bottom: 1.75rem;
        }

        .page-title {
            font-family: 'Roboto', sans-serif;
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--ds-text-900);
            display: flex;
            align-items: center;
            gap: 0.625rem;
        }
        .page-title i { width: 22px; height: 22px; color: var(--ds-green); }

        .page-subtitle {
            color: var(--ds-text-600);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1.5rem;
            align-items: start;
        }

        .preview-panel { position: sticky; top: 1.5rem; }

        .section-card {
            background: var(--ds-surface);
            border: 1px solid var(--ds-border-light);
            border-radius: var(--ds-radius-lg);
            overflow: hidden;
        }

        .section-header {
            padding: 1rem 1.125rem;
            border-bottom: 1px solid var(--ds-border-light);
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--ds-text-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-header i { width: 17px; height: 17px; color: var(--ds-text-600); }
        .section-title { display: flex; align-items: center; gap: 0.5rem; }

        .section-content { padding: 1.125rem; }

        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--ds-border-light);
            margin-bottom: 0;
            overflow-x: auto;
        }
        .settings-tab {
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ds-text-600);
            padding: 14px 18px 12px;
            cursor: pointer;
            border: none;
            background: transparent;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-family: 'Roboto', sans-serif;
        }
        .settings-tab i { width: 16px; height: 16px; }
        .settings-tab:hover { color: var(--ds-text-900); }
        .settings-tab.active { color: var(--ds-green-dark); border-bottom-color: var(--ds-green); font-weight: 700; }

        .settings-form { display: flex; flex-direction: column; }

        .settings-group { display: none; }
        .settings-group.active { display: block; }

        .group-header {
            padding: 1.25rem 1.5rem 0.25rem;
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--ds-text-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .group-header i { width: 17px; height: 17px; color: var(--ds-text-600); }

        .group-content {
            padding: 1.25rem 1.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .form-group { display: flex; flex-direction: column; gap: 0.375rem; }

        .form-group label {
            font-weight: 500;
            color: var(--ds-text-900);
            font-size: 0.8125rem;
        }

        .form-group input:not([type="color"]),
        .form-group textarea,
        .form-group select {
            width: 100%;
            font-family: 'Roboto', sans-serif;
            padding: 0.6875rem 0.8125rem;
            border: 1px solid var(--ds-border);
            border-radius: var(--ds-radius-sm);
            font-size: 0.875rem;
            background: var(--ds-surface);
            color: var(--ds-text-900);
            transition: border-color 0.15s, padding 0.15s;
        }

        .form-group input[type="color"] {
            width: 44px;
            height: 44px;
            padding: 2px;
            border: 1px solid var(--ds-border);
            border-radius: var(--ds-radius-sm);
            cursor: pointer;
            flex-shrink: 0;
        }

        .form-group input:not([type="color"]):hover, .form-group textarea:hover, .form-group select:hover { border-color: var(--ds-text-400); }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border: 2px solid var(--ds-green);
            padding: 0.625rem 0.75rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .help-text {
            font-size: 0.75rem;
            color: var(--ds-text-600);
            margin-top: 0.125rem;
        }

        .color-input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: var(--ds-radius-sm);
            border: 1px solid var(--ds-border);
            cursor: pointer;
        }

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .icon-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.75rem 0.5rem;
            border: 1px solid var(--ds-border);
            border-radius: var(--ds-radius-sm);
            cursor: pointer;
            transition: all 0.15s;
            background: var(--ds-surface);
        }

        .icon-option:hover { border-color: var(--ds-text-400); background: var(--ds-surface-alt); }

        .icon-option.selected {
            border: 1.5px solid var(--ds-green);
            background: var(--ds-green-bg);
        }

        .icon-option i { margin-bottom: 0.25rem; color: var(--ds-text-600); }
        .icon-option.selected i { color: var(--ds-green-dark); }

        .icon-option span {
            font-size: 0.6875rem;
            text-align: center;
            color: var(--ds-text-600);
        }

        .btn-primary, .btn-secondary, .btn-danger {
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: var(--ds-radius-sm);
            font-weight: 500;
            font-size: 0.84375rem;
            font-family: 'Roboto', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.15s;
            justify-content: center;
        }
        .btn-primary i, .btn-secondary i, .btn-danger i { width: 16px; height: 16px; }

        .btn-primary { background: var(--ds-green); color: white; }
        .btn-primary:hover { background: var(--ds-green-dark); }

        .btn-secondary { background: var(--ds-surface); color: var(--ds-text-600); border: 1px solid var(--ds-border); }
        .btn-secondary:hover { background: var(--ds-surface-alt); border-color: var(--ds-text-400); }

        .btn-danger { background: var(--ds-surface); color: var(--ds-red); border: 1px solid var(--ds-border); }
        .btn-danger:hover { background: var(--ds-red-bg); border-color: var(--ds-red); }

        .action-buttons {
            display: flex;
            gap: 0.625rem;
            padding: 0.5rem 1.5rem 1.5rem;
        }

        .preview-card {
            background: var(--ds-surface);
            overflow: hidden;
        }

        .preview-header {
            background: var(--primary-color, var(--ds-green));
            color: white;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .preview-icon {
            width: 2.25rem;
            height: 2.25rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--ds-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-content { padding: 1.125rem; }

        .preview-field {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--ds-border-light);
            font-size: 0.8125rem;
        }

        .preview-field:last-child { border-bottom: none; }

        .preview-label { font-weight: 500; color: var(--ds-text-900); }

        .preview-value {
            color: var(--ds-text-600);
            text-align: right;
            max-width: 180px;
            word-wrap: break-word;
        }

        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            border-radius: var(--ds-radius-sm);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        .alert i { width: 18px; height: 18px; flex-shrink: 0; }

        .alert-success { background: var(--ds-green-bg); color: var(--ds-green-dark); border: 1px solid #b7e1c2; }
        .alert-danger { background: var(--ds-red-bg); color: var(--ds-red); border: 1px solid #f6c6c2; }

        @media (max-width: 900px) {
            .customization-container { padding: 1.25rem; }
            .settings-grid { grid-template-columns: 1fr; }
            .preview-panel { position: static; order: -1; }
            .icon-selector { grid-template-columns: repeat(3, 1fr); }
            .action-buttons { flex-direction: column; }
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
                <div class="customization-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="settings"></i>
                            Paramètres
                        </h1>
                        <p class="page-subtitle">Personnalisez l'application et configurez la facturation électronique.</p>
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

                    <?php
                    $groupIcons = [
                        'Application' => 'smartphone',
                        'Pharmacie' => 'building-2',
                        'Système' => 'sliders-horizontal',
                        'Facturation Électronique (SFEC)' => 'file-check-2',
                    ];
                    $groupSlugs = array_map(fn($g) => 'tab-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($g)), array_keys($settingsGroups));
                    $groupNames = array_keys($settingsGroups);
                    ?>

                    <!-- Settings Form -->
                    <form method="POST" class="settings-form" id="settingsForm">
                        <input type="hidden" name="action" value="update_settings">

                        <!-- Settings Grid -->
                        <div class="settings-grid">
                            <div class="section-card">
                                <nav class="settings-tabs" id="settingsTabs">
                                    <?php foreach ($groupNames as $i => $groupName): ?>
                                        <button type="button" class="settings-tab<?php echo $i === 0 ? ' active' : ''; ?>" data-target="<?php echo $groupSlugs[$i]; ?>">
                                            <i data-lucide="<?php echo $groupIcons[$groupName] ?? 'cog'; ?>"></i>
                                            <?php echo htmlspecialchars($groupName); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </nav>

                                <?php $i = 0; foreach ($settingsGroups as $groupName => $groupKeys): ?>
                                    <div class="settings-group<?php echo $i === 0 ? ' active' : ''; ?>" id="<?php echo $groupSlugs[$i]; ?>">
                                        <div class="group-header">
                                            <i data-lucide="<?php echo $groupIcons[$groupName] ?? 'cog'; ?>"></i>
                                            <?php echo htmlspecialchars($groupName); ?>
                                        </div>
                                        <div class="group-content">
                                            <?php foreach ($groupKeys as $key): ?>
                                                <?php $setting = getSetting($settings, $key); ?>
                                                <?php if ($setting): ?>
                                                    <div class="form-group">
                                                        <label for="<?php echo $key; ?>">
                                                            <?php echo htmlspecialchars($setting['description']); ?>
                                                        </label>
                                                        
                                                        <?php if ($key === 'app_icon'): ?>
                                                            <input type="hidden" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                            <div class="icon-selector">
                                                                <?php foreach ($iconOptions as $icon => $label): ?>
                                                                    <div class="icon-option <?php echo $setting['setting_value'] === $icon ? 'selected' : ''; ?>" 
                                                                         onclick="selectIcon('<?php echo $icon; ?>', this)">
                                                                        <i data-lucide="<?php echo $icon; ?>"></i>
                                                                        <span><?php echo $label; ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                        <?php elseif ($setting['setting_type'] === 'color'): ?>
                                                            <div class="color-input-group">
                                                                <input type="color" 
                                                                       name="<?php echo $key; ?>" 
                                                                       id="<?php echo $key; ?>"
                                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                       onchange="updateColorPreview('<?php echo $key; ?>', this.value)">
                                                                <input type="text" 
                                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                       readonly style="flex: 1;">
                                                            </div>
                                                            
                                                        <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                                            <textarea name="<?php echo $key; ?>" 
                                                                      id="<?php echo $key; ?>"
                                                                      onchange="updatePreview('<?php echo $key; ?>', this.value)"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                                      
                                                        <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                            <input type="number"
                                                                   name="<?php echo $key; ?>"
                                                                   id="<?php echo $key; ?>"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   onchange="updatePreview('<?php echo $key; ?>', this.value)">

                                                        <?php elseif ($setting['setting_type'] === 'password'): ?>
                                                            <input type="password"
                                                                   name="<?php echo $key; ?>"
                                                                   id="<?php echo $key; ?>"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   autocomplete="off">

                                                        <?php else: ?>
                                                            <input type="text" 
                                                                   name="<?php echo $key; ?>" 
                                                                   id="<?php echo $key; ?>"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   onchange="updatePreview('<?php echo $key; ?>', this.value)">
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($key === 'app_icon'): ?>
                                                            <div class="help-text">Choisissez l'icône qui apparaîtra dans l'en-tête de l'application</div>
                                                        <?php elseif ($key === 'low_stock_threshold'): ?>
                                                            <div class="help-text">Seuil en dessous duquel un produit est considéré en stock faible</div>
                                                        <?php elseif ($key === 'primary_color'): ?>
                                                            <div class="help-text">Couleur principale utilisée dans l'interface</div>
                                                        <?php elseif ($key === 'sfec_environment'): ?>
                                                            <div class="help-text">Valeurs acceptées : "sandbox" (test) ou "production" (factures réelles, nécessite NIU + clé API validés par le portail e-Facture)</div>
                                                        <?php elseif ($key === 'sfec_taxpayer_niu' || $key === 'sfec_sciet' || $key === 'sfec_api_key'): ?>
                                                            <div class="help-text">Tant que ce champ est vide, les ventes ne sont pas envoyées à SFEC (aucun impact sur l'encaissement)</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php $i++; endforeach; ?>

                                <div class="action-buttons">
                                    <button type="submit" class="btn-primary">
                                        <i data-lucide="save"></i>
                                        Enregistrer les Modifications
                                    </button>
                                    <button type="button" class="btn-secondary" onclick="previewChanges()">
                                        <i data-lucide="eye"></i>
                                        Aperçu des Changements
                                    </button>
                                    <button type="button" class="btn-danger" onclick="resetToDefaults()">
                                        <i data-lucide="refresh-cw"></i>
                                        Réinitialiser
                                    </button>
                                </div>
                            </div>

                            <!-- Preview Panel -->
                            <div class="preview-panel">
                                <div class="section-card">
                                    <div class="section-header">
                                        <i data-lucide="eye"></i>
                                        Aperçu
                                    </div>
                                    <div class="section-content">
                                        <div class="preview-card">
                                            <div class="preview-header" id="previewHeader">
                                                <div class="preview-icon" id="previewIcon">
                                                    <i data-lucide="<?php echo getSettingValue($settings, 'app_icon') ?: 'pill'; ?>"></i>
                                                </div>
                                                <div>
                                                    <h3 id="previewAppName"><?php echo htmlspecialchars(getSettingValue($settings, 'app_name')); ?></h3>
                                                    <p id="previewPharmacyName"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_name')); ?></p>
                                                </div>
                                            </div>
                                            <div class="preview-content">
                                                <div class="preview-field">
                                                    <span class="preview-label">Adresse:</span>
                                                    <span class="preview-value" id="previewAddress"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_address')); ?></span>
                                                </div>
                                                <div class="preview-field">
                                                    <span class="preview-label">Téléphone:</span>
                                                    <span class="preview-value" id="previewPhone"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_phone')); ?></span>
                                                </div>
                                                <div class="preview-field">
                                                    <span class="preview-label">Email:</span>
                                                    <span class="preview-value" id="previewEmail"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_email')); ?></span>
                                                </div>
                                                <div class="preview-field">
                                                    <span class="preview-label">Licence:</span>
                                                    <span class="preview-value" id="previewLicense"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_license')); ?></span>
                                                </div>
                                                <div class="preview-field">
                                                    <span class="preview-label">Devise:</span>
                                                    <span class="preview-value" id="previewCurrency"><?php echo htmlspecialchars(getSettingValue($settings, 'currency')); ?></span>
                                                </div>
                                                <div class="preview-field">
                                                    <span class="preview-label">Statut SFEC:</span>
                                                    <span class="preview-value"><?php echo getSettingValue($settings, 'sfec_api_key') ? 'Configuré' : 'Non configuré'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i data-lucide="alert-triangle" style="color: #ef4444; width: 3rem; height: 3rem; margin-bottom: 1rem;"></i>
            <h3 style="margin-bottom: 1rem;">Confirmer la Réinitialisation</h3>
            <p style="margin-bottom: 2rem; color: var(--ds-text-400);">Êtes-vous sûr de vouloir réinitialiser tous les paramètres aux valeurs par défaut ? Cette action est irréversible.</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset_to_defaults">
                    <button type="submit" class="btn-danger">
                        <i data-lucide="check"></i>
                        Confirmer
                    </button>
                </form>
                <button type="button" class="btn-secondary" onclick="closeResetModal()">
                    <i data-lucide="x"></i>
                    Annuler
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Initialize color previews
            updateColorPreview('primary_color', document.getElementById('primary_color')?.value);
            updateColorPreview('secondary_color', document.getElementById('secondary_color')?.value);

            // Form validation
            const settingsForm = document.getElementById('settingsForm');
            if (settingsForm) {
                settingsForm.addEventListener('submit', function(e) {
                    const appName = document.getElementById('app_name')?.value?.trim();
                    const pharmacyName = document.getElementById('pharmacy_name')?.value?.trim();

                    if (!appName || !pharmacyName) {
                        e.preventDefault();
                        alert('Le nom de l\'application et le nom de la pharmacie sont obligatoires.');
                        return false;
                    }
                });
            }

            // Sidebar overlay functionality
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            }

            // Settings tabs
            const tabs = document.querySelectorAll('.settings-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.settings-group').forEach(g => g.classList.remove('active'));
                    this.classList.add('active');
                    document.getElementById(this.dataset.target)?.classList.add('active');
                });
            });
        });

        // Icon selection functionality
        function selectIcon(iconName, element) {
            // Remove selected class from all options
            document.querySelectorAll('.icon-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Update hidden input
            document.getElementById('app_icon').value = iconName;
            
            // Update preview
            updatePreview('app_icon', iconName);
            
            // Reinitialize icons to show the change
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Color preview update
        function updateColorPreview(settingKey, color) {
            if (settingKey === 'primary_color') {
                document.documentElement.style.setProperty('--primary-color', color);
                const previewHeader = document.getElementById('previewHeader');
                if (previewHeader) {
                    previewHeader.style.background = color;
                }
            }
            
            // Update the text input next to color picker
            const colorInput = document.getElementById(settingKey);
            if (colorInput && colorInput.nextElementSibling) {
                colorInput.nextElementSibling.value = color;
            }
        }

        // Live preview updates
        function updatePreview(settingKey, value) {
            switch (settingKey) {
                case 'app_name':
                    const appNameEl = document.getElementById('previewAppName');
                    if (appNameEl) appNameEl.textContent = value || 'PharmaSys';
                    break;
                    
                case 'pharmacy_name':
                    const pharmacyNameEl = document.getElementById('previewPharmacyName');
                    if (pharmacyNameEl) pharmacyNameEl.textContent = value || 'Pharmacie Centrale';
                    break;
                    
                case 'pharmacy_address':
                    const addressEl = document.getElementById('previewAddress');
                    if (addressEl) addressEl.textContent = value || 'Adresse non définie';
                    break;
                    
                case 'pharmacy_phone':
                    const phoneEl = document.getElementById('previewPhone');
                    if (phoneEl) phoneEl.textContent = value || 'Téléphone non défini';
                    break;
                    
                case 'pharmacy_email':
                    const emailEl = document.getElementById('previewEmail');
                    if (emailEl) emailEl.textContent = value || 'Email non défini';
                    break;
                    
                case 'pharmacy_license':
                    const licenseEl = document.getElementById('previewLicense');
                    if (licenseEl) licenseEl.textContent = value || 'Licence non définie';
                    break;
                    
                case 'currency':
                    const currencyEl = document.getElementById('previewCurrency');
                    if (currencyEl) currencyEl.textContent = value || 'FCFA';
                    break;
                    
                case 'app_icon':
                    const iconEl = document.getElementById('previewIcon');
                    if (iconEl) {
                        iconEl.innerHTML = '<i data-lucide="' + value + '"></i>';
                        // Reinitialize icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                    break;
            }
        }

        // Preview all changes
        function previewChanges() {
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            
            let previewText = 'Aperçu des modifications:\n\n';
            for (let [key, value] of formData.entries()) {
                if (key !== 'action') {
                    const label = document.querySelector(`label[for="${key}"]`)?.textContent || key;
                    previewText += `${label}: ${value}\n`;
                }
            }
            
            alert(previewText);
        }

        // Reset to defaults confirmation
        function resetToDefaults() {
            const modal = document.getElementById('resetModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeResetModal() {
            const modal = document.getElementById('resetModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }
        }

        // Auto-save functionality (optional)
        let autoSaveTimeout;
        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Could implement auto-save here if desired
                console.log('Auto-save triggered');
            }, 5000);
        }

        // Add event listeners for auto-save on all inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('#settingsForm input, #settingsForm textarea, #settingsForm select');
            inputs.forEach(input => {
                input.addEventListener('input', scheduleAutoSave);
                input.addEventListener('change', scheduleAutoSave);
            });
        });
    </script>
</body>
</html>

<?php
} else {
    // Redirect to login if not admin
    header("Location: ../logout.php");
    exit();
}
?>