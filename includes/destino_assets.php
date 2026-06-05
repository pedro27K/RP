<?php
// Mapa de imágenes y gradientes CSS por destino_id
// Equivalente al DESTINO_ASSETS de data.js
$IMGS_DESTINOS = [
    1  => 'assets/maldivas.jpg',
    2  => 'assets/cancun.png',
    3  => 'assets/paris.jpg',
    4  => 'assets/tokio.jpg',
    5  => 'assets/santorini.jpg',
    6  => 'assets/dubai.jpg',
    7  => 'assets/nuevayork.jpg',
    8  => 'assets/bali.jpg',
    9  => 'assets/roma.jpg',
    10 => 'assets/bangkok.jpg',
    11 => 'assets/marrakech.jpg',
    12 => 'assets/phuket.jpg',
    13 => 'assets/amsterdam.jpg',
    14 => 'assets/rio.jpg',
    15 => 'assets/capadocia.jpg',
    16 => 'assets/lisboa.jpg',
    17 => 'assets/praga.jpg',
    18 => 'assets/bergen.jpg',
    19 => 'assets/cusco.jpg',
    20 => 'assets/islascanarias.jpg',
];

function imagenDestino(int $destino_id, ?string $imagen_url, array $mapa): string {
    if ($imagen_url) return $imagen_url;
    return $mapa[$destino_id] ?? '';
}
