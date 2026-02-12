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

        if (!in_array($status, ['verified', 'rejected'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
            break;
        }

        $sql = "UPDATE valuation_requests SET status = ?, admin_remarks = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $status, $remarks, $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "Request #$id updated to $status."]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        break;
}

$conn->close();
?>
