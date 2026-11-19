<?php
$zip = new ZipArchive();
if ($zip->open('otp-update.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $files = [
        'config/verification.php',
        'includes/init.php',
        'app/Controllers/Customer/DashboardController.php',
        'app/Controllers/CustomerVerificationController.php',
        'app/Controllers/AuthController.php',
        'app/Services/AuthService.php',
        'app/Services/CustomerOtpService.php',
        'app/Services/SmslenzClient.php',
        'app/Services/MailService.php',
        'resources/views/auth/customer-otp.php',
        'verify-customer.php',
        'public/verify-customer.php',
        'diagnose.php',
        'public/diagnose.php'
    ];
    foreach ($files as $f) {
        $zip->addFile($f, $f);
    }
    $zip->close();
    echo "Created otp-update.zip (" . filesize('otp-update.zip') . " bytes) with " . count($files) . " files.\n";
} else {
    echo "Failed to create zip\n";
}
