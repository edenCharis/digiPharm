<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();
require_once dirname(__DIR__, 2) . '/analytics/config/db.php';
$adb = analytics_db();

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'save';

if ($action === 'toggle') {
    $id        = (int) ($_POST['id'] ?? 0);
    $is_active = (int) ($_POST['is_active'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'ID manquant']); exit; }
    $adb->prepare("UPDATE ai_users SET is_active=? WHERE id=?")->execute([$is_active, $id]);
    echo json_encode(['ok'=>true, 'message'=>'Statut mis à jour']);
    exit;
}

// Save (create or update)
$id          = (int)   ($_POST['id']           ?? 0);
$email       = trim(   $_POST['email']         ?? '');
$display     = trim(   $_POST['display_name']  ?? '');
$password    = trim(   $_POST['password']      ?? '');
$pharmacy_id = (int)   ($_POST['pharmacy_id']  ?? 0);
$role        = in_array($_POST['role'] ?? '', ['admin','viewer']) ? $_POST['role'] : 'viewer';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'error'=>'Email invalide']); exit;
}
if (!$pharmacy_id) {
    echo json_encode(['ok'=>false,'error'=>'Pharmacie requise']); exit;
}

try {
    if ($id) {
        // Update
        if ($password) {
            $adb->prepare("UPDATE ai_users SET email=?, display_name=?, pharmacy_id=?, role=?, password=? WHERE id=?")
                ->execute([$email, $display ?: null, $pharmacy_id, $role, password_hash($password, PASSWORD_BCRYPT), $id]);
        } else {
            $adb->prepare("UPDATE ai_users SET email=?, display_name=?, pharmacy_id=?, role=? WHERE id=?")
                ->execute([$email, $display ?: null, $pharmacy_id, $role, $id]);
        }
        echo json_encode(['ok'=>true,'message'=>'Utilisateur mis à jour']);
    } else {
        // Create
        if (!$password) { echo json_encode(['ok'=>false,'error'=>'Mot de passe requis pour la création']); exit; }
        $adb->prepare("INSERT INTO ai_users (email, display_name, pharmacy_id, role, password, is_active) VALUES (?,?,?,?,?,1)")
            ->execute([$email, $display ?: null, $pharmacy_id, $role, password_hash($password, PASSWORD_BCRYPT)]);
        echo json_encode(['ok'=>true,'message'=>'Utilisateur créé']);
    }
} catch (PDOException $e) {
    $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Cet email est déjà utilisé' : 'Erreur base de données';
    echo json_encode(['ok'=>false,'error'=>$msg]);
}
