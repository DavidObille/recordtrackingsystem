<?php

function addNotification($con, $user_id, $message, $request_id = null) {
    $query = "INSERT INTO notifications (recipient_id, message, request_id)
              VALUES (?, ?, ?)";
    $stmt = $con->prepare($query);
    $stmt->bind_param("isi", $user_id, $message, $request_id);
    $stmt->execute();
    $stmt->close();
}

function notifyAdmins($con, $message, $request_id = null) {
    $query = "INSERT INTO notifications (recipient_id, message, request_id)
              SELECT id, ?, ? FROM users WHERE role = 'admin'";
    $stmt = $con->prepare($query);
    $stmt->bind_param("si", $message, $request_id);
    $stmt->execute();
    $stmt->close();
}

function getNotifications($con, $user_id) {
    $query = "SELECT id, message, is_read, created_at
              FROM notifications
              WHERE recipient_id = ?
              ORDER BY created_at DESC, id DESC
              LIMIT 10";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
    return $notifications;
}

function getUnreadNotificationCount($con, $user_id) {
    $query = "SELECT COUNT(*) AS total
              FROM notifications
              WHERE recipient_id = ? AND is_read = 0";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)$row['total'];
}

function markNotificationRead($con, $notification_id, $user_id) {
    $query = "UPDATE notifications
              SET is_read = 1
              WHERE id = ? AND recipient_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

function markAllNotificationsRead($con, $user_id) {
    $query = "UPDATE notifications
              SET is_read = 1
              WHERE recipient_id = ? AND is_read = 0";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}
