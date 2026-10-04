<?php
declare(strict_types=1);

// ===== SECURITY (Week 8) =====
// XSS protection helper
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ===== PRICING (Weeks 4 and 5) =====
// Week 5: multi-way branching with match (peak hours cost more)
function peak_multiplier(int $hour): float
{
    return match (true) {
        $hour >= 17 && $hour < 21 => 1.25,
        $hour >= 21               => 1.10,
        default                   => 1.0,
    };
}

// Week 5: if / elseif / else for the duration discount
function discount_rate(int $hours): float
{
    if ($hours >= 4) {
        return 0.15;
    } elseif ($hours >= 3) {
        return 0.10;
    }
    return 0.0;
}

// Week 4: weighted hourly rate, discount, then 12% VAT
function calculate_total(array $court, int $start, int $hours): float
{
    $subtotal = 0.0;
    for ($h = $start; $h < $start + $hours; $h++) {
        $subtotal += $court['rate'] * peak_multiplier($h);
    }
    $discounted = $subtotal * (1 - discount_rate($hours));
    return round($discounted * 1.12, 2);
}

// Week 5: switch for the member tier shown after booking
function member_tier(int $bookings): string
{
    switch (true) {
        case $bookings >= 10: return 'Gold';
        case $bookings >= 5:  return 'Silver';
        default:              return 'Bronze';
    }
}

// ===== AVAILABILITY (Weeks 6 and 7) =====
function booked_hours(array $bookings, int $court, string $date): array
{
    $taken = [];
    foreach ($bookings as $b) {
        if ($b['court'] === $court && $b['date'] === $date) {
            for ($h = $b['start']; $h < $b['start'] + $b['hours']; $h++) {
                $taken[] = $h;
            }
        }
    }
    return $taken;
}

function is_available(array $bookings, int $court, string $date, int $start, int $hours): bool
{
    $taken = booked_hours($bookings, $court, $date);
    for ($h = $start; $h < $start + $hours; $h++) {
        if (in_array($h, $taken, true)) {
            return false;
        }
    }
    return true;
}

// ===== HELPERS (Week 7) =====
// Week 7: pass-by-reference (&) to add a booking to the list
function add_booking(array &$bookings, array $booking): void
{
    $booking['id'] = count($bookings) + 1;
    $bookings[] = $booking;
}

// Week 7: static variable keeps its value between calls (zebra striping)
function row_class(): string
{
    static $i = 0;
    return (++$i % 2) ? 'odd' : 'even';
}

function format_hour(int $h): string
{
    return date('g:00 A', mktime($h, 0));
}