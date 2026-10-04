<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/data.php';       // court array
require __DIR__ . '/functions.php';  // helper functions

$courts = get_courts();
$_SESSION['bookings'] ??= [];
$errors = [];
$old = [];

// ===== 1. FORM PROCESSING (Week 8): validation, upload, PRG =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old   = $_POST; // sticky values
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $court = filter_var($_POST['court'] ?? '', FILTER_VALIDATE_INT);
    $date  = trim($_POST['date'] ?? '');
    $start = filter_var($_POST['start'] ?? '', FILTER_VALIDATE_INT);
    $hours = filter_var($_POST['hours'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

    if (empty($name)) {
        $errors[] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($court === false || !in_array($court, array_keys($courts), true)) {
        $errors[] = 'Choose a valid court.';
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date || $date < date('Y-m-d')) {
        $errors[] = 'Choose a valid date (today or later).';
    }
    if ($start === false || $hours === false) {
        $errors[] = 'Choose a start time and 1 to 5 hours.';
    }

    if (!$errors) {
        $c = $courts[$court];
        if ($start < $c['open'] || $start + $hours > $c['close']) {
            $errors[] = "{$c['name']} is open " . format_hour($c['open']) . ' to ' . format_hour($c['close']) . '.';
        } elseif (!is_available($_SESSION['bookings'], $court, $date, $start, $hours)) {
            $errors[] = 'That time slot is already booked.';
        }
    }

    // Binary upload: payment receipt
    $f = $_FILES['receipt'] ?? null;
    $ext = '';
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload your payment receipt (image).';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $errors[] = 'Receipt must be a JPG or PNG.';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Receipt must be 2 MB or smaller.';
        } elseif (getimagesize($f['tmp_name']) === false) {
            $errors[] = 'Receipt is not a real image.';
        }
    }

    if (!$errors) {
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $saved = bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($f['tmp_name'], "$dir/$saved");

        $total = calculate_total($courts[$court], $start, $hours);
        add_booking($_SESSION['bookings'], [
            'name' => $name, 'email' => $email, 'court' => $court, 'date' => $date,
            'start' => $start, 'hours' => $hours, 'total' => $total, 'receipt' => $saved,
        ]);

        $mine = count(array_filter($_SESSION['bookings'], fn($b) => $b['email'] === $email));
        $msg  = "Booked {$courts[$court]['name']} on $date. ";
        $msg .= 'Total: PHP ' . number_format($total, 2) . ' (incl. 12% VAT). ';
        $msg .= 'Member tier: ' . member_tier($mine) . '.';
        $_SESSION['flash'] = $msg;

        header('Location: index.php?date=' . urlencode($date)); // PRG
        exit();
    }
}

// ===== 2. PAGE DATA: flash message, filters, sorting =====
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Filters (GET)
$view_date = $_GET['date'] ?? date('Y-m-d');
$vd = DateTime::createFromFormat('Y-m-d', $view_date);
if (!$vd || $vd->format('Y-m-d') !== $view_date) {
    $view_date = date('Y-m-d');
}
$sports = array_unique(array_column($courts, 'sport'));
sort($sports);
$sport = $_GET['sport'] ?? 'All';

// Sorted bookings (spaceship operator)
$list = $_SESSION['bookings'];
usort($list, fn($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);

// ===== 3. STICKY FORM HELPER =====
function old(string $k): string { global $old; return e((string)($old[$k] ?? '')); }

// ===== 4. LOAD THE HTML VIEW =====
require __DIR__ . '/view.php';