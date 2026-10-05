<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

if (($_SESSION["role"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Access denied. Administrators only.");
}

include("./connection/config.php");
include("./helpers/SystemOperators.php");
require_once("./helpers/NotificationService.php");

$con = connection();
$so = new SystemOperators();
$success_message = '';
$search = trim($_GET['search'] ?? '');
$requests = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnUpdate'])) {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $status = trim(filter_input(INPUT_POST, 'status', FILTER_UNSAFE_RAW) ?? '');
    $claiming_area = trim(filter_input(INPUT_POST, 'claiming_area', FILTER_UNSAFE_RAW) ?? '');
    $allowed_statuses = ['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'];

    if ($request_id && $request_id > 0 && in_array($status, $allowed_statuses, true) && $claiming_area !== '') {
        $lookup = $con->prepare(
            "SELECT user_id, email, file_no, status, claiming_area
             FROM document_requests
             WHERE id = ?"
        );
        $lookup->bind_param("i", $request_id);
        $lookup->execute();
        $existing_request = $lookup->get_result()->fetch_assoc();
        $lookup->close();

        if ($existing_request) {
            $old_status = $so->decrypt($existing_request['status']) ?: 'Pending';
            $old_area = $so->decrypt($existing_request['claiming_area'] ?? '') ?: '';
            $changes = [];

            if ($old_status !== $status) {
                $changes[] = "status changed from $old_status to $status";
            }
            if ($old_area !== $claiming_area) {
                $old_area_text = $old_area !== '' ? $old_area : 'not assigned';
                $changes[] = "claiming area changed from $old_area_text to $claiming_area";
            }

            $recipient_id = $existing_request['user_id'] !== null
                ? (int)$existing_request['user_id']
                : null;

            if ($recipient_id === null) {
                $student_lookup = $con->prepare(
                    "SELECT id FROM users WHERE email = ? AND role = 'student'"
                );
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
                $update = $con->prepare(
                    "UPDATE document_requests
                     SET status = ?, claiming_area = ?
                     WHERE id = ?"
                );
                $update->bind_param("ssi", $enc_status, $enc_area, $request_id);
                $update->execute();
                $update->close();

                if ($changes && $recipient_id !== null) {
                    $reference = $so->decrypt($existing_request['file_no']);
                    $message = "Your request $reference was updated: " . implode('; ', $changes) . '.';
                    addNotification($con, $recipient_id, $message, $request_id);
                }

                $con->commit();
                $redirect_url = 'admin_dashboard.php?success=1';
                if ($search !== '') {
                    $redirect_url .= '&search=' . urlencode($search);
                }
                header("Location: $redirect_url");
                exit();
            } catch (mysqli_sql_exception $e) {
                $con->rollback();
                error_log($e->getMessage());
            }
        } else {
            error_log("Document request not found: " . $request_id);
        }
    }
}

if (isset($_GET['success'])) {
    $success_message = 'Request updated successfully.';
}

$result = $con->query("SELECT * FROM document_requests ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
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

    <div class="admin-search">
        <form method="GET" action="admin_dashboard.php">
            <input
                type="text"
                name="search"
                placeholder="Search requests..."
                value="<?= htmlspecialchars($search) ?>"
            >
            <button type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a href="admin_dashboard.php" class="clear-search">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($success_message): ?>
        <div class="success-alert"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <h3>Submitted Requests</h3>
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
                <?php
                $shown_requests = 0;
                foreach ($requests as $request):
                    $file_no = $so->decrypt($request['file_no']);
                    $student_no = $so->decrypt($request['student_no']);
                    $first = $so->decrypt($request['firstname']);
                    $middle = $so->decrypt($request['middlename']) ?: '';
                    $last = $so->decrypt($request['lastname']);
                    $full_name = trim("$first $middle $last");
                    $program = $so->decrypt($request['program']);
                    $doc_type = $so->decrypt($request['doc_type']);
                    $purpose = $so->decrypt($request['purpose']);
                    $claiming_area = $so->decrypt($request['claiming_area'] ?? '') ?: '';
                    $status = $so->decrypt($request['status']) ?: 'Pending';

                    $search_text = "$file_no $student_no $full_name $program $doc_type $purpose $claiming_area $status";
                    if ($search !== '' && stripos($search_text, $search) === false) {
                        continue;
                    }

                    $shown_requests++;
                    $form_id = 'request-update-' . (int)$request['id'];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($file_no) ?></strong></td>
                        <td><?= htmlspecialchars($student_no) ?></td>
                        <td><?= htmlspecialchars($full_name) ?></td>
                        <td><?= htmlspecialchars($program) ?></td>
                        <td><?= htmlspecialchars($doc_type) ?></td>
                        <td><?= htmlspecialchars($purpose) ?></td>
                        <td>
                            <input
                                class="request-area"
                                type="text"
                                name="claiming_area"
                                form="<?= $form_id ?>"
                                placeholder="Claiming area"
                                value="<?= htmlspecialchars($claiming_area) ?>"
                                required
                            >
                        </td>
                        <td>
                            <form class="request-form" id="<?= $form_id ?>" method="POST" action="admin_dashboard.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                <select name="status" required>
                                    <?php foreach (['Pending', 'Processing', 'Approved', 'Ready for Claiming', 'Completed', 'Rejected'] as $option): ?>
                                        <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="btnUpdate">Save Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($shown_requests === 0): ?>
                    <tr>
                        <td class="empty-requests" colspan="8">No matching document requests.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php $con->close(); ?>
</body>
</html>
