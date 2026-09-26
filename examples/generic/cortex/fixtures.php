<?php

// Synthetic, independently labelled concierge observations.
$training = [
    ['breakfast restaurant directions', 'directions'],
    ['where breakfast restaurant', 'directions'],
    ['find elevator directions', 'directions'],
    ['where elevator location', 'directions'],
    ['transport luggage bags', 'luggage'],
    ['bring luggage upstairs', 'luggage'],
    ['carry bags upstairs', 'luggage'],
    ['luggage delivery transport', 'luggage'],
    ['reservation checkin arrival', 'checkin'],
    ['confirm reservation booking', 'checkin'],
    ['checkin booking room', 'checkin'],
    ['reservation room arrival', 'checkin'],
];
$holdout = [
    ['breakfast location', 'directions'],
    ['find elevator', 'directions'],
    ['transport bags upstairs', 'luggage'],
    ['bring luggage', 'luggage'],
    ['confirm booking', 'checkin'],
    ['reservation arrival', 'checkin'],
];
return ['training' => $training, 'holdout' => $holdout];
