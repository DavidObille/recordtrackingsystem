<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();
$success_message = '';
$requests = [];

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnUpdate'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
	$status = trim(filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW) ?? '');
	$claiming_area = trim(filter_input(INPUT_POST, 'claiming_area', FILTER_UNSAFE_RAW) ?? '');

	$allowed_statuses = ['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'];

    if ($request_id && $request_id > 0 && in_array($status, $allowed_statuses, true) && $claiming_area !== '') {
        $lookup = $con->prepare("SELECT user_id, email, file_no, status, claiming_area FROM document_requests WHERE id = ?");
        $lookup->bind_param("i", $request_id);
        $lookup->execute();
        $existing_request = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if (!$existing_request) {
            error_log("Document request not found: " . $request_id);
        } else {
            $old_status = $so->decrypt($existing_request['status']) ?: 'Pending';
            $old_area = $so->decrypt($existing_request['claiming_area'] ?? '') ?: '';
            $changes = [];
            if ($old_status !== $status) {
                $changes[] = "status changed from $old_status to $status";
            }
            if ($old_area !== $claiming_area) {
                $changes[] = "claiming area changed from " . ($old_area !== '' ? $old_area : 'not assigned') . " to $claiming_area";
            }
            $recipient_id = $existing_request['user_id'] !== null ? (int)$existing_request['user_id'] : null;
            if ($recipient_id === null) {
                $student_lookup = $con->prepare("SELECT id FROM users WHERE email = ? AND role = 'student'");
                $student_lookup->bind_param("s", $existing_request['email']);
                $student_lookup->execute();
                $student = $student_lookup->get_result()->fetch_assoc();
                $student_lookup->close();
                $recipient_id = $student ? (int)$student['id'] : null;
            }

		$enc_status = $so->encrypt($status);
		$enc_area = $so->encrypt($claiming_area);

            try {
                $con->begin_transaction();
                $stmt = $con->prepare("UPDATE document_requests SET status = ?, claiming_area = ? WHERE id = ?");
                $stmt->bind_param("ssi", $enc_status, $enc_area, $request_id);
                $stmt->execute();
                $stmt->close();

                if ($changes && $recipient_id !== null) {
                    $reference = $so->decrypt($existing_request['file_no']);
                    $message = "Your request $reference was updated: " . implode('; ', $changes) . '.';
                    addNotification($con, $recipient_id, $message, (int)$request_id);
                }

                $con->commit();
                header("Location: admin_dashboard.php?success=1");
                exit;
            } catch (mysqli_sql_exception $e) {
                $con->rollback();
                error_log($e->getMessage());
            }
        }
	}
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    $success_message = 'Request updated successfully.';
}

// Fetch all document requests
$query = "SELECT * FROM document_requests ORDER BY id DESC";
if ($result = $con->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Document Requests</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>
<body>

    <div class="admin-topbar">
        <div>
            <h2>Admin Dashboard</h2>
            <p>Review submitted document requests and update their status and claiming area.</p>
        </div>
        <?php include("./helpers/notification_center.php"); ?>
    </div>

    <?php if ($success_message): ?>
        <div class="success-alert"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <h3>Submitted Requests (<?= count($requests) ?>)</h3>
    
    <div class="table-wrap">
        <table class="request-table" border="1" cellpadding="8" cellspacing="0">
            <thead>
                <tr>
                    <th>Ref No.</th>
                    <th>Student No.</th>
                    <th>Student Name</th>
                    <th>Program</th>
                    <th>File Type</th>
                    <th>Purpose</th>
                    <th>Claiming Area</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td class="empty-requests" colspan="8">No document requests submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <?php
                            // Decrypt fields up front for cleaner HTML rendering
                            $status = $so->decrypt($request['status']) ?: 'Pending';
                            $claiming_area = $so->decrypt($request['claiming_area'] ?? '') ?: '';
                            $form_id = 'request-update-' . (int)$request['id'];
                            $first = $so->decrypt($request['firstname']);
                            $middle = $so->decrypt($request['middlename']) ?: '';
                            $last = $so->decrypt($request['lastname']);
                            $full_name = trim("$first $middle $last");
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($so->decrypt($request['file_no'])) ?></strong></td>
                            <td><?= htmlspecialchars($so->decrypt($request['student_no'])) ?></td>
                            <td><?= htmlspecialchars($full_name) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['program'])) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['doc_type'])) ?></td>
                            <td><?= htmlspecialchars($so->decrypt($request['purpose'])) ?></td>
                            <td>
                                <input class="request-area" type="text" name="claiming_area" form="<?= $form_id ?>" placeholder="Claiming area" value="<?= htmlspecialchars($claiming_area) ?>" required>
                            </td>
                            <td>
                                <form class="request-form" id="<?= $form_id ?>" method="POST" action="admin_dashboard.php">
                                    <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                    <select name="status" required>
                                        <?php 
                                        $options = ['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'];
                                        foreach ($options as $opt): 
                                        ?>
                                            <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <button type="submit" name="btnUpdate">Save Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $con->close(); ?>
</body>
</html>