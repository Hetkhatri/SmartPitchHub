<?php
header('Content-Type: application/json');
require_once '../db.php'; // Adjust path if needed

$sql = "
    SELECT 
        p.id, p.startup_name as name, p.industry as category, p.description, 
        p.funding_goal, p.stage, p.pitch_logo as logo, p.likes, p.views, 
        p.interested_investors as interestedInvestors, p.is_approved as isAdminApproved,
        p.round_status, p.expiry_date,
        COALESCE((SELECT SUM(amount) FROM investments WHERE pitch_id = p.id AND status = "completed"), 0) as amount_raised,
        CASE WHEN ov.status = 'verified' THEN 1 ELSE 0 END as isFounderVerified
    FROM pitches p
    JOIN entrepreneurs e ON p.entrepreneur_id = e.id
    LEFT JOIN otp_verifications ov ON e.email = ov.email
    WHERE p.is_approved = 1
    ORDER BY p.created_at DESC
";

$result = $conn->query($sql);
$pitches = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $raised = (float)$row['amount_raised'];
        $goal = (float)$row['funding_goal'];
        $row['progress'] = ($goal > 0) ? min(100, round(($raised / $goal) * 100)) : 0;
        $row['raisedAmount'] = '₹' . number_format($raised);
        $row['fundingRequired'] = '₹' . number_format($row['funding_goal']);
        $row['id'] = (int)$row['id'];
        $row['likes'] = (int)$row['likes'];
        $row['views'] = (int)$row['views'];
        $row['interestedInvestors'] = (int)$row['interestedInvestors'];
        $row['isAdminApproved'] = (bool)$row['isAdminApproved'];
        $row['isFounderVerified'] = (bool)$row['isFounderVerified'];
        unset($row['amount_raised']); 
        $pitches[] = $row;
    }
}

echo json_encode($pitches);
?>