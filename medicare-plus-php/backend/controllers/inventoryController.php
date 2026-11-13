<?php
// controllers/inventoryController.php
// Member 4's module. The /low-stock route is the "stock alert" extra
// feature from the blueprint — it compares quantity_in_stock against each
// medicine's own reorder_level rather than a single hardcoded threshold.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listInventory(): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->query(
            'SELECT i.inventory_id, m.name AS medicine_name, m.category,
                    i.quantity_in_stock, i.reorder_level, i.expiry_date
             FROM Inventory i
             JOIN Medicine m ON i.medicine_id = m.medicine_id
             ORDER BY m.name'
        );
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch inventory.']);
    }
}

function lowStock(): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->query(
            'SELECT m.name AS medicine_name, i.quantity_in_stock, i.reorder_level
             FROM Inventory i
             JOIN Medicine m ON i.medicine_id = m.medicine_id
             WHERE i.quantity_in_stock <= i.reorder_level
             ORDER BY i.quantity_in_stock ASC'
        );
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch low-stock items.']);
    }
}

function listMedicines(): void {
    $pdo=getDb();
    try { $s=$pdo->query('SELECT medicine_id,name,manufacturer,unit_price,category FROM Medicine ORDER BY name'); jsonResponse(200,$s->fetchAll()); }
    catch(Exception $e){ error_log($e->getMessage()); jsonResponse(500,['message'=>'Could not fetch medicines.']); }
}
