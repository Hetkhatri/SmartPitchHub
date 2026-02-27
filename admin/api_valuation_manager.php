<?php
// admin/api_valuation_manager.php
header('Content-Type: application/json');
session_start();

// 1. ADMIN AUTHENTICATION CHECK
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Admin login required.']);
    exit;
}

require_once '../db.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch_all':
        // Fetch all valuation requests with entrepreneur details
        $sql = "SELECT v.*, e.name as entrepreneur_name, e.email as entrepreneur_email 
                FROM valuation_requests v 
                JOIN entrepreneurs e ON v.entrepreneur_id = e.id 
                ORDER BY v.created_at DESC";
        $result = $conn->query($sql);
        
        $requests = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $requests[] = $row;
            }
            echo json_encode(['status' => 'success', 'data' => $requests]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    case 'fetch_detail':
        $id = intval($_GET['id'] ?? 0);
        $sql = "SELECT v.*, e.name as entrepreneur_name, e.email as entrepreneur_email, e.contact as entrepreneur_contact
                FROM valuation_requests v 
                JOIN entrepreneurs e ON v.entrepreneur_id = e.id 
                WHERE v.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($data = $res->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
        }
        break;

    case 'update_status':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'POST method required.']);
            break;
        }

        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? ''; // 'verified' or 'rejected'
        $remarks = $_POST['remarks'] ?? '';
        $modifiedValuation = $_POST['modified_valuation'] ?? null;

        if (!in_array($status, ['verified', 'rejected'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
            break;
        }

        $conn->begin_transaction();
        try {
            // 1. Get original request info
            $get_req = $conn->prepare("SELECT v.*, e.total_shares FROM valuation_requests v JOIN entrepreneurs e ON v.entrepreneur_id = e.id WHERE v.id = ?");
            $get_req->bind_param("i", $id);
            $get_req->execute();
            $req_data = $get_req->get_result()->fetch_assoc();
            
            if (!$req_data) throw new Exception("Request not found.");

            $finalValuation = ($status === 'verified') ? ($modifiedValuation ?: $req_data['valuation_ask']) : null;

            // 2. Update Valuation Request
            $sql = "UPDATE valuation_requests SET status = ?, admin_remarks = ?, approved_valuation = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $status, $remarks, $finalValuation, $id);
            $stmt->execute();

            // 3. SMART SYNC: Update linked pitch if any
            $pitch_sql = "SELECT id, funding_goal FROM pitches WHERE valuation_request_id = ? OR (entrepreneur_id = ? AND is_approved = 0) ORDER BY created_at DESC LIMIT 1";
            $p_stmt = $conn->prepare($pitch_sql);
            $p_stmt->bind_param("ii", $id, $req_data['entrepreneur_id']);
            $p_stmt->execute();
            $pitch = $p_stmt->get_result()->fetch_assoc();

            $pitchMessage = "";
            if ($pitch) {
                $pitchId = $pitch['id'];
                
                if ($status === 'verified') {
                    $fundingGoal = $pitch['funding_goal'];
                    $totalShares = $req_data['total_shares'] ?: 50000;
                    
                    // Recalculate Share Price & Count based on Approved Valuation
                    $newSharePrice = $finalValuation / $totalShares;
                    $newSharesToIssue = floor($fundingGoal / $newSharePrice);

                    // Update Pitch with Approved Valuation & Auto-Approve Pitch
                    $up_pitch = $conn->prepare("UPDATE pitches SET valuation = ?, share_price = ?, shares_issued = ?, is_approved = 1, admin_feedback = 'Valuation Verified. Pitch Auto-Approved.' WHERE id = ?");
                    $up_pitch->bind_param("dddi", $finalValuation, $newSharePrice, $newSharesToIssue, $pitchId);
                    $up_pitch->execute();
                    $pitchMessage = " & Pitch #$pitchId Auto-Approved";
                } else if ($status === 'rejected') {
                    // Update Pitch to Rejected as well
                    $up_pitch = $conn->prepare("UPDATE pitches SET is_approved = 2, admin_feedback = ? WHERE id = ?");
                    $reject_msg = "Valuation Basis Rejected: " . $remarks;
                    $up_pitch->bind_param("si", $reject_msg, $pitchId);
                    $up_pitch->execute();
                    $pitchMessage = " & Pitch #$pitchId Rejected";
                }
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => "Valuation #$id $status$pitchMessage."]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

$conn->close();
?>
