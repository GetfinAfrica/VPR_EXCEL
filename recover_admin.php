<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();

$message = '';
$error = '';
$mode = $_GET['mode'] ?? 'request';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_recovery'])) {
    // Generic response avoids revealing account details.
    try {
        $raw = create_recovery_token();
        $link = base_url() . '/recover_admin.php?mode=reset&token=' . urlencode($raw);
        send_security_email(
            'VPR Admin Password Recovery',
            "A VPR admin password recovery request was received.\n\nRecovery link (valid for 15 minutes):\n" . $link .
            "\n\nIf you did not request this, ignore this email. The existing password remains unchanged."
        );
        audit_admin('recovery_requested');
    } catch (Throwable $e) {}
    $message = 'If email delivery is available on this hosting account, a recovery link has been sent to the VPR security email.';
}

if ($mode === 'reset' && $token !== '') {
    $row = verify_recovery_token($token);
    if (!$row) {
        $error = 'This recovery link is invalid, expired, or has already been used.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
        $pw = (string)$_POST['new_password'];
        $confirm = (string)($_POST['confirm_password'] ?? '');
        if (strlen($pw) < 12) {
            $error = 'Use a password of at least 12 characters.';
        } elseif ($pw !== $confirm) {
            $error = 'The two passwords do not match.';
        } elseif (!set_admin_password($pw)) {
            $error = 'The new password could not be saved. Check that the data folder is writable.';
        } else {
            mark_recovery_used($token);
            db()->exec("DELETE FROM login_attempts");
            audit_admin('password_reset');
            send_security_email(
                'VPR Admin Password Changed',
                "The VPR admin password was changed using the recovery system.\n\nTime: " . date('c') .
                "\n\nIf you did not perform this change, immediately secure your hosting account."
            );
            $message = 'Admin password changed successfully. Login blocks have also been cleared.';
            $mode = 'done';
        }
    }
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VPR Admin Recovery</title>
<style>
body{font-family:Arial;background:#f5f7f9;color:#14202b;padding:40px 16px}
.box{max-width:520px;margin:auto;background:#fff;border:1px solid #dfe5ea;border-radius:14px;padding:26px;box-shadow:0 10px 30px rgba(6,44,82,.08)}
.logo{width:100%;max-width:340px;display:block;margin:0 auto 18px}
input,button{width:100%;padding:12px;margin-top:12px;box-sizing:border-box}
button{background:#062c52;color:#fff;border:0;border-radius:8px;font-weight:700}
.ok{color:#166534}.err{color:#b91c1c}.muted{color:#66727e;font-size:13px;line-height:1.5}
a{color:#062c52}
</style></head><body><div class="box">
<img class="logo" src="assets/vpr-logo.png" alt="VPR">
<h2>Admin Recovery</h2>

<?php if($message): ?><p class="ok"><?=htmlspecialchars($message)?></p><?php endif;?>
<?php if($error): ?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?>

<?php if($mode === 'request'): ?>
<p class="muted">Use this only if you have forgotten the admin password or the login has been blocked. VPR will send a one-time recovery link to the configured security email address.</p>
<form method="post">
  <input type="hidden" name="request_recovery" value="1">
  <button type="submit">Send Recovery Link</button>
</form>
<?php elseif($mode === 'reset' && !$error): ?>
<form method="post">
  <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
  <label>New admin password</label>
  <input type="password" name="new_password" minlength="12" required>
  <label>Confirm new password</label>
  <input type="password" name="confirm_password" minlength="12" required>
  <button type="submit">Change Admin Password</button>
</form>
<?php endif; ?>

<p style="margin-top:18px"><a href="admin2000.php">Return to Admin Login</a></p>
</div></body></html>
