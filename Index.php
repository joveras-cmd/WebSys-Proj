<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/functions.php';

$courts = get_courts();
$_SESSION['bookings'] ??= [];
$errors = [];
$old = [];

// ---------- Form processing (Week 8) ----------
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

function old(string $k): string { global $old; return e((string)($old[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Court Reservation</title>
<style>
 body{
    font-family:system-ui,sans-serif;
    max-width:960px;
    margin:0 auto;
    padding:16px;
    color:#1d2433;
    background:#F5EDEB}
 
 h1{margin-bottom:4px} 
 section{
        background:#E3E3E3;
        border-radius:10px;
        padding:16px;
        margin:16px 0;
        box-shadow:0 1px 3px #0001}

 table{
        width:100%;
        border-collapse:collapse} 
 
 th,td{
        padding:8px;
        text-align:left;
        border-bottom:1px solid #e5e9f0}

 tr.even{background:#f3f6fb} 

 .free{color:#0a7d3b} 

 .taken{color:#b42318}

 .err{
        background:#fde8e8;
        color:#8a1c1c;
        padding:10px;
        border-radius:6px}

.ok{
        background:#e3f6ea;
        color:#14532d;
        padding:10px;
        border-radius:6px}
        
 label{
        display:block;
        margin:10px 0 4px;
        font-weight:600} 
        
        input,select{
            padding:8px;
            width:100%;
            box-sizing:border-box}

 .grid{
        display:grid;   
        grid-template-columns:repeat(auto-fit,minmax(200px,1fr));g
        ap:0 16px}

 button{
        margin-top:14px;
        padding:10px 18px;
        background:#1d4ed8;
        color:#fff;
        border:0;
        border-radius:6px;
        cursor:pointer}

 .slots span{
        display:inline-block;
        margin:2px 4px 2px 0;
        padding:2px 6px;
        border-radius:4px;
        background:#eef2f7;
        font-size:.85rem}

</style>
</head>
<body>
<h1>Court Reservation</h1>
<p>Browse courts, check open time slots, and book by the hour.</p>

<?php if ($flash): ?><p class="ok"><?= e($flash) ?></p><?php endif; ?>

<section>
 <h2>Courts</h2>
 <form method="get">
  <label for="sport">Sport</label>
  <select name="sport" id="sport" onchange="this.form.submit()">
   <option>All</option>
   <?php foreach ($sports as $s): ?>
    <option <?= $sport === $s ? 'selected' : '' ?>><?= e($s) ?></option>
   <?php endforeach; ?>
  </select>
  <input type="hidden" name="date" value="<?= e($view_date) ?>">
 </form>
 <table>
  <tr><th>Court</th><th>Sport</th><th>Rate / hr</th><th>Hours</th><th>Features</th><th>Status</th></tr>
  <?php foreach ($courts as $id => $c): ?>
   <?php if ($sport !== 'All' && $c['sport'] !== $sport) continue; ?>
   <?php $taken = count(booked_hours($_SESSION['bookings'], $id, $view_date)); ?>
   <tr class="<?= row_class() ?>">
    <td><?= e($c['name']) ?></td>
    <td><?= e($c['sport']) ?></td>
    <td>PHP <?= number_format($c['rate'], 2) ?></td>
    <td><?= e(format_hour($c['open'])) ?> - <?= e(format_hour($c['close'])) ?></td>
    <td><?= e(implode(' | ', explode(',', $c['tags']))) ?></td>
    <td>
     <?php if ($taken === 0): ?>Fully open
     <?php elseif ($taken < $c['close'] - $c['open']): ?>Partly booked
     <?php else: ?>Fully booked
     <?php endif; ?>
    </td>
   </tr>
  <?php endforeach; ?>
 </table>
</section>

<section>
 <h2>Time slot availability</h2>
 <form method="get">
  <label for="vdate">Date</label>
  <input type="date" id="vdate" name="date" value="<?= e($view_date) ?>" onchange="this.form.submit()">
  <input type="hidden" name="sport" value="<?= e($sport) ?>">
 </form>
 <?php foreach ($courts as $id => $c): ?>
  <?php if ($sport !== 'All' && $c['sport'] !== $sport) continue; ?>
  <?php $taken = booked_hours($_SESSION['bookings'], $id, $view_date); ?>
  <p><strong><?= e($c['name']) ?></strong></p>
  <div class="slots">
   <?php for ($h = $c['open']; $h < $c['close']; $h++): ?>
    <?php $busy = in_array($h, $taken, true); ?>
    <span class="<?= $busy ? 'taken' : 'free' ?>"><?= e(format_hour($h)) ?> <?= $busy ? '(booked)' : '' ?></span>
   <?php endfor; ?>
  </div>
 <?php endforeach; ?>
</section>

<section>
 <h2>Reserve a court</h2>
 <p>Peak rates apply 5 PM onward (x1.25 until 9 PM, x1.10 after). Discounts: 3 hrs = 10%, 4+ hrs = 15%. 12% VAT added.</p>
 <?php if ($errors): ?>
  <div class="err"><ul>
   <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
  </ul></div>
 <?php endif; ?>
 <form method="post" action="" enctype="multipart/form-data">
  <div class="grid">
   <div><label for="name">Full name</label>
    <input id="name" name="name" value="<?= old('name') ?>"></div>
   <div><label for="email">Email</label>
    <input id="email" name="email" value="<?= old('email') ?>"></div>
   <div><label for="court">Court</label>
    <select id="court" name="court">
     <option value="">Select...</option>
     <?php foreach ($courts as $id => $c): ?>
      <option value="<?= $id ?>" <?= (string)($old['court'] ?? '') === (string)$id ? 'selected' : '' ?>>
       <?= e($c['name'] . ' - ' . $c['sport']) ?>
      </option>
     <?php endforeach; ?>
    </select></div>
   <div><label for="date">Date</label>
    <input type="date" id="date" name="date" value="<?= old('date') ?>"></div>
   <div><label for="start">Start time</label>
    <select id="start" name="start">
     <?php for ($h = 7; $h <= 21; $h++): ?>
      <option value="<?= $h ?>" <?= (string)($old['start'] ?? '') === (string)$h ? 'selected' : '' ?>><?= e(format_hour($h)) ?></option>
     <?php endfor; ?>
    </select></div>
   <div><label for="hours">Hours</label>
    <select id="hours" name="hours">
     <?php for ($i = 1; $i <= 5; $i++): ?>
      <option value="<?= $i ?>" <?= (string)($old['hours'] ?? '') === (string)$i ? 'selected' : '' ?>><?= $i ?></option>
     <?php endfor; ?>
    </select></div>
  </div>
  <label for="receipt">Payment receipt (JPG/PNG, max 2 MB)</label>
  <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png">
  <button type="submit">Reserve</button>
 </form>
</section>

<section>
 <h2>Reservations</h2>
 <?php if (empty($list)): ?>
  <p>No reservations yet.</p>
 <?php else: ?>
  <table>
   <tr><th>#</th><th>Name</th><th>Court</th><th>Date</th><th>Time</th><th>Total</th></tr>
   <?php foreach ($list as $b): ?>
    <tr class="<?= row_class() ?>">
     <td><?= $b['id'] ?></td>
     <td><?= e($b['name']) ?></td>
     <td><?= e($courts[$b['court']]['name']) ?></td>
     <td><?= e($b['date']) ?></td>
     <td><?= e(format_hour($b['start'])) ?> (<?= $b['hours'] ?> hr)</td>
     <td>PHP <?= number_format($b['total'], 2) ?></td>
    </tr>
   <?php endforeach; ?>
  </table>
 <?php endif; ?>
</section>
</body>
</html>