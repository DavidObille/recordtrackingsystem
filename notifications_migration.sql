ALTER TABLE `document_requests`
  ADD COLUMN `user_id` int(11) DEFAULT NULL AFTER `id`;

UPDATE `document_requests` AS dr
JOIN `users` AS student
  ON dr.`email` = student.`email` AND student.`role` = 'student'
SET dr.`user_id` = student.`id`
WHERE dr.`user_id` IS NULL;

ALTER TABLE `document_requests`
  ADD KEY `user_id` (`user_id`),
  ADD CONSTRAINT `document_requests_user_fk`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `recipient_unread_created` (`recipient_id`, `is_read`, `created_at`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `notifications_recipient_fk`
    FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_request_fk`
    FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
