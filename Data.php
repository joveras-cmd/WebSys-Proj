<?php
declare(strict_types=1);

// ===== COURT DATA (Week 7: multidimensional associative array) =====
// Week 7: multidimensional associative array (court catalog)
function get_courts(): array
{
    return [
        1 => ['name' => 'Court A', 'sport' => 'Basketball', 'rate' => 350.0, 'open' => 8, 'close' => 22, 'tags' => 'indoor,lights,scoreboard'],
        2 => ['name' => 'Court B', 'sport' => 'Basketball', 'rate' => 250.0, 'open' => 8, 'close' => 22, 'tags' => 'outdoor,lights'],
        3 => ['name' => 'Court C', 'sport' => 'Badminton',  'rate' => 200.0, 'open' => 8, 'close' => 21, 'tags' => 'indoor,wooden floor'],
        4 => ['name' => 'Court D', 'sport' => 'Tennis',     'rate' => 400.0, 'open' => 7, 'close' => 20, 'tags' => 'outdoor,clay'],
    ];
}