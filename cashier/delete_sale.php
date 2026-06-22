<?php
session_start();

// Check authentication
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "CASHIER" || $_SESSION["id"] != session_id()) {
    header("Location: ../logout.php");
    exit;
}

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Connexion à la base de données non trouvée');
    }
    
    $saleId = isset($_GET['id']) ? $_GET['id'] : 0;
    
   
    
    $cashierId = $_SESSION["user_id"];
    
    // Verify the sale belongs to this cashier's register
    $verifyQuery = "SELECT s.id FROM sale s 
                    JOIN cash_register cr ON s.cash_register_id = cr.id 
                    WHERE s.id = ?";
    
    $sale = $db->fetch($verifyQuery, [$saleId]);
    
    if (!$sale) {
        $_SESSION['error'] = 'Vente non trouvée ou accès refusé';
        header("Location: completed-sales.php");
        exit;
    }
    
    // Begin transaction
    $db->query("START TRANSACTION");
    
    try {
        // Delete sale items first (foreign key constraint)
        $deleteItemsQuery = "DELETE FROM saleitem WHERE saleId = ?";
        $db->query($deleteItemsQuery, [$saleId]);
        
        // Delete the sale
        $deleteSaleQuery = "DELETE FROM sale WHERE id = ?";
        $db->query($deleteSaleQuery, [$saleId]);
        
        // Commit transaction
        $db->query("COMMIT");
        
        $_SESSION['success'] = 'Vente supprimée avec succès';
        header("Location: completed-sales.php");
        exit;
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        throw $e;
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        try {
            $db->query("ROLLBACK");
        } catch (Exception $rollbackError) {
            // Ignore
        }
    }
    
    error_log('Delete sale error: ' . $e->getMessage());
    $_SESSION['error'] = 'Erreur lors de la suppression: ' . $e->getMessage();
    header("Location: completed-sales.php");
    exit;
}
?>