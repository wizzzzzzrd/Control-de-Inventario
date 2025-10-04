<?php
// app/models/transfer.php
require_once __DIR__ . '/baseModel.php';

class TransferModel extends BaseModel {

    /**
     * Realiza el traspaso de 'fromBranch' a 'toBranch' en unidades de consumo (piezas).
     * Retorna array ['ok'=>bool, 'msg'=>string]
     *
     * @param int $productId
     * @param int $fromBranchId
     * @param int $toBranchId
     * @param float $qtyConsum (piezas)
     * @param float|null $qtyPurchase (cajas, opcional)
     * @param int|null $userId
     */
    public function createTransfer(int $productId, int $fromBranchId, int $toBranchId, float $qtyConsum, ?float $qtyPurchase = null, ?int $userId = null) {
        $db = $this->db;
        try {
            $db->begin_transaction();

            // 1) Lock row de stock en bodega (from)
            $sql = "SELECT qty_consumption_unit, COALESCE(boxes_count,0) AS boxes_count FROM product_branch_stock WHERE product_id = ? AND branch_id = ? FOR UPDATE";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $productId, $fromBranchId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rowFrom = $res->fetch_assoc();
            $stmt->close();

            if (!$rowFrom) {
                $db->rollback();
                return ['ok'=>false, 'msg'=>'No hay stock registrado en la bodega para ese producto.'];
            }

            $available = (float)$rowFrom['qty_consumption_unit'];
            if ($available < $qtyConsum) {
                $db->rollback();
                return ['ok'=>false, 'msg'=> "Stock insuficiente en bodega (disponible: $available)"];
            }

            // 2) Restar de bodega
            $newFromQty = $available - $qtyConsum;
            $sqlUp = "UPDATE product_branch_stock SET qty_consumption_unit = ?, last_updated_at = NOW() WHERE product_id = ? AND branch_id = ?";
            $stmt = $db->prepare($sqlUp);
            $stmt->bind_param('dii', $newFromQty, $productId, $fromBranchId);
            $stmt->execute();
            $stmt->close();

            // 3) Aumentar en sucursal destino (si no existe crear)
            $sqlCheck = "SELECT qty_consumption_unit FROM product_branch_stock WHERE product_id = ? AND branch_id = ? FOR UPDATE";
            $stmt = $db->prepare($sqlCheck);
            $stmt->bind_param('ii', $productId, $toBranchId);
            $stmt->execute();
            $res2 = $stmt->get_result();
            $rowTo = $res2->fetch_assoc();
            $stmt->close();

            if ($rowTo) {
                $newToQty = (float)$rowTo['qty_consumption_unit'] + $qtyConsum;
                $sqlUp2 = "UPDATE product_branch_stock SET qty_consumption_unit = ?, last_updated_at = NOW() WHERE product_id = ? AND branch_id = ?";
                $stmt = $db->prepare($sqlUp2);
                $stmt->bind_param('dii', $newToQty, $productId, $toBranchId);
                $stmt->execute();
                $stmt->close();
            } else {
                $sqlIns = "INSERT INTO product_branch_stock (product_id, branch_id, qty_consumption_unit, boxes_count, last_updated_at)
                           VALUES (?, ?, ?, NULL, NOW())";
                $stmt = $db->prepare($sqlIns);
                $stmt->bind_param('iid', $productId, $toBranchId, $qtyConsum);
                $stmt->execute();
                $stmt->close();
            }

            // 4) Registrar movimientos (transfer_out y transfer_in)
            $sqlMov = "INSERT INTO stock_movements (product_id, from_branch_id, to_branch_id, movement_type, qty_consumption, qty_purchase, user_id, created_at, note)
                       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            $note = "Traspaso bodega->sucursal";

            // transfer_out
            $typeOut = 'transfer_out';
            $stmt = $db->prepare($sqlMov);
            // types: i i i s d d i s
            $stmt->bind_param('iiisdiss', $productId, $fromBranchId, $toBranchId, $typeOut, $qtyConsum, $qtyPurchase, $userId, $note);
            $stmt->execute();
            $stmt->close();

            // transfer_in
            $typeIn = 'transfer_in';
            $stmt = $db->prepare($sqlMov);
            $stmt->bind_param('iiisdiss', $productId, $fromBranchId, $toBranchId, $typeIn, $qtyConsum, $qtyPurchase, $userId, $note);
            $stmt->execute();
            $stmt->close();

            // 5) Registrar en table transfers
            $sqlT = "INSERT INTO transfers (product_id, from_branch_id, to_branch_id, qty_consumption, qty_purchase, created_by, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
            $stmt = $db->prepare($sqlT);
            // types: i i i d d i
            $stmt->bind_param('iiiddi', $productId, $fromBranchId, $toBranchId, $qtyConsum, $qtyPurchase, $userId);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            return ['ok'=>true, 'msg'=>'Traspaso realizado correctamente'];
               } catch (Exception $e) {
            // Intentar rollback de forma segura (sin asumir propiedades internas)
            if (isset($db) && $db instanceof mysqli) {
                try {
                    $db->rollback();
                } catch (Throwable $ex) {
                    // ignorar errores al hacer rollback
                    error_log("Rollback failed: " . $ex->getMessage());
                }
            }
            error_log("Transfer error: " . $e->getMessage());
            return ['ok'=>false, 'msg'=>'Error interno al procesar el traspaso'];
        }

    }
}
