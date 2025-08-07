-- Create application_status_history table
CREATE TABLE IF NOT EXISTS `application_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `status` enum('pending','shortlisted','interview','approved','rejected') NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `changed_by` (`changed_by`),
  CONSTRAINT `application_status_history_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create trigger to automatically add history when application status changes
DELIMITER //
CREATE TRIGGER IF NOT EXISTS `application_status_change` 
AFTER UPDATE ON `applications`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `application_status_history` 
        (`application_id`, `status`, `notes`, `changed_by`) 
        VALUES 
        (NEW.id, NEW.status, NEW.admin_notes, @admin_user_id);
    END IF;
END //
DELIMITER ;

-- Add admin_notes column to applications table if it doesn't exist
ALTER TABLE `applications` 
ADD COLUMN IF NOT EXISTS `admin_notes` text DEFAULT NULL AFTER `additional_info`;
