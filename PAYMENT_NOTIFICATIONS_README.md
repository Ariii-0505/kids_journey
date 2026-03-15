# Payment Notification System

This document explains how to set up and use the automatic payment notification system for the School Management System.

## Features

- **Manual Notifications**: Admins can send payment reminders to guardians from the Finance Management page
- **Automatic Notifications**:
  - Pending payments: Reminder sent 1 day before due date
  - Installment payments: Reminder sent 7 days before due date
  - Overdue payments: Notification sent when payment becomes overdue
- **Email Logging**: All notifications are logged in the `payment_notifications` table
- **Status Updates**: Payments are automatically marked as overdue when past due date

## Setup

### 1. Email Configuration

Ensure SMTP settings are configured in `includes/config.php`:

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

For Gmail, you need to:
1. Enable 2-Factor Authentication
2. Generate an App Password at https://myaccount.google.com/apppasswords
3. Use the App Password as SMTP_PASSWORD

### 2. Database Table

The `payment_notifications` table should already exist. If not, create it:

```sql
CREATE TABLE `payment_notifications` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `guardian_email` varchar(255) NOT NULL,
  `notification_type` enum('manual','automatic') NOT NULL DEFAULT 'manual',
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `payment_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_sent_at` (`sent_at`);
```

### 3. Cron Jobs

Set up the following cron jobs to run daily:

```bash
# Update payment statuses (run at midnight)
0 0 * * * /usr/bin/php /path/to/school-management-system/tools/update_payment_statuses.php

# Send automatic notifications (run at 9 AM)
0 9 * * * /usr/bin/php /path/to/school-management-system/tools/send_automatic_notifications.php
```

For Windows Task Scheduler:
1. Create a batch file `run_notifications.bat`:
   ```
   @echo off
   C:\xampp\php\php.exe C:\xampp\htdocs\school-management-system\tools\send_automatic_notifications.php
   ```

2. Create a batch file `run_status_updates.bat`:
   ```
   @echo off
   C:\xampp\php\php.exe C:\xampp\htdocs\school-management-system\tools\update_payment_statuses.php
   ```

3. Schedule them in Task Scheduler to run daily.

## Usage

### Manual Notifications

1. Go to Finance Management → Payment Management
2. Find the payment record in the table
3. Click the notification button (📧) in the Actions column
4. Confirm the notification in the popup
5. The system will send an email to the guardian

### Automatic Notifications

The system will automatically:
- Send reminders for pending payments 1 day before due date
- Send reminders for installment payments 7 days before due date
- Send overdue notifications when payments become overdue
- Update payment statuses to "overdue" when past due date

### Notification Indicators

- A green checkmark (✓) appears next to the notification button if a notification has been sent for that payment

## Email Content

### Subject
`Payment Reminder – Student Service Payment Due`

### Body
Contains:
- Student name
- Service/Program name
- Package/Category
- Amount due
- Due date
- Payment status
- Special message for overdue payments

## Troubleshooting

### Emails not sending
1. Check SMTP settings in `config.php`
2. Verify Gmail App Password is correct
3. Check PHP error logs
4. Test SMTP connection using the EmailHelper class

### Guardian email missing
- Update student guardian information in the system
- The system will show a warning if no guardian email is found

### Cron jobs not running
- Check file paths in cron configuration
- Ensure PHP executable path is correct
- Check system logs for errors

## Files Modified/Created

- `includes/EmailHelper.php` - Added general sendEmail method
- `public/administrator/finance.php` - Added notification functionality
- `tools/send_automatic_notifications.php` - New automatic notification script
- `tools/update_payment_statuses.php` - New status update script