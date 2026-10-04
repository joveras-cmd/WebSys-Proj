<?php /* VIEW: HTML only. Variables come from index.php */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Court Reservation</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Court Reservation</h1>
<p>Browse courts, check open time slots, and book by the hour.</p>

<!-- ===== SUCCESS MESSAGE ===== -->
<?php if ($flash): ?><p class="ok"><?= e($flash) ?></p><?php endif; ?>

<!-- ===== COURTS ===== -->
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

<!-- ===== TIME SLOT AVAILABILITY ===== -->
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

<!-- ===== RESERVE A COURT ===== -->
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
  <label for="receipt">Payment receipt (JPG/PNG, max 5 MB)</label>
  <input type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png">
  <button type="submit">Reserve</button>
 </form>
</section>

<!-- ===== RESERVATIONS ===== -->
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