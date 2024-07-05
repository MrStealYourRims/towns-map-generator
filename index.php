<?php
/**
 * Map generator (originally for Towns.cz game)
 *
 * @copyright 2015-2024 Towns.cz
 * @link http://api.towns.cz/
 * @link http://www.towns.cz/
 * @author     Pavol Hejný
 * @version    3.0
 */

declare(strict_types=1);

// Initialization
ini_set("max_execution_time", "1000");
ini_set("memory_limit", "200M");
error_reporting(E_ALL);

// Load values from form
$velikost = intval($_POST["velikost"] ?? 400);
$voda = floatval($_POST["voda"] ?? 40);
$zrnitostOstrova = floatval($_POST["zrnitostostrova"] ?? 0.8);
$delkaRek = floatval($_POST["delkarek"] ?? 400);
$tocivostRek = floatval($_POST["tocivostrek"] ?? 22);
$centralitaRek = floatval($_POST["centralitarek"] ?? 60);
$zrnitostOstatni = floatval($_POST["zrnitostostatni"] ?? 1);

$tereny = [
    ["t2" => 5],   // dlažba
    ["t3" => 20],  // sníh/led
    ["t4" => 5],   // písek
    ["t5" => 20],  // kamení
    ["t6" => 5],   // hlína
    ["t7" => 3],   // sůl
    ["t9" => 30],  // tráva(toxic)
    ["t10" => 30], // les
    ["t11" => 5],  // řeka
    ["t12" => 80], // tráva(jaro)
    ["t13" => 90], // tráva(podzim)
];

foreach ($tereny as $key => $default) {
    $terrainKey = array_key_first($default);
    $tereny[$key] = floatval($_POST[$terrainKey] ?? $default[$terrainKey]);
}

// Adjust values
$voda = min($voda, 80);
$velikost = min($velikost, 1000);
$zrnitostOstrova = max($zrnitostOstrova, 0.5);
$zrnitostOstatni = max($zrnitostOstatni, 0.5);

// Map generation
if (isset($_POST["velikost"])) {
    $mapa = array_fill(1, $velikost, array_fill(1, $velikost, 1));

    $pevnina = 100 - $voda;
    $q = ($velikost * $velikost) * ($pevnina / 100);
    $x = rand(1, $velikost);
    $y = rand(1, $velikost);

    while ($q > 0) {
        if ($mapa[round($x)][round($y)] != 8) {
            $mapa[round($x)][round($y)] = 8;
            $q--;
        }

        $x += (rand(0, $zrnitostOstrova * 200) / 100) - $zrnitostOstrova;
        $y += (rand(0, $zrnitostOstrova * 200) / 100) - $zrnitostOstrova;

        if ($x > $velikost - 1 || $x < 1 || $y > $velikost - 1 || $y < 1) {
            $x = rand(1, $velikost);
            $y = rand(1, $velikost);
        }
    }

    // Rivers generation
    $q = ($delkaRek * $velikost) / 100;
    $u = $centralitaRek / 100 / 2;
    while ($q > 0) {
        $x = rand(intval($velikost * (0.5 - $u)), intval($velikost * (0.5 + $u)));
        $y = rand(intval($velikost * (0.5 - $u)), intval($velikost * (0.5 + $u)));
        
        $uhel = rand(1, 360);
        $px = cos($uhel / 180 * pi()) / 1.41;
        $py = sin($uhel / 180 * pi()) / 1.41;

        while ($mapa[round($x)][round($y)] != 1 && !($x > $velikost - 1 || $x < 1 || $y > $velikost - 1 || $y < 1)) {
            $q--;
            $mapa[round($x)][round($y)] = 11;
            
            $px = cos($uhel / 180 * pi()) / 1.41;
            $py = sin($uhel / 180 * pi()) / 1.41;
            $x += $px;
            $y += $py;
            
            $uhel += rand(0, $tocivostRek * 2) - $tocivostRek;
        }
    }

    // Other terrains
    $tereny = array_map(fn($val, $key) => [$val, $key + 2], $tereny, array_keys($tereny));
    shuffle($tereny);

    foreach ($tereny as [$procento, $teren]) {
        $q = intval($velikost * $velikost * ($pevnina / 100) * ($procento / 100));
        $x = rand(1, $velikost);
        $y = rand(1, $velikost);

        while ($q > 0) {
            if ($mapa[round($x)][round($y)] == 1 || $mapa[round($x)][round($y)] == 11) {
                $x = rand(1, $velikost);
                $y = rand(1, $velikost);
            } else {
                $mapa[round($x)][round($y)] = $teren;
                $x += (rand(0, $zrnitostOstatni * 200) / 100) - $zrnitostOstatni;
                $y += (rand(0, $zrnitostOstatni * 200) / 100) - $zrnitostOstatni;

                if ($x > $velikost - 1) $x = rand(1, $velikost);
                if ($x < 1) $x = rand(1, $velikost);
                if ($y > $velikost - 1) $y = rand(1, $velikost);
                if ($y < 1) $y = rand(1, $velikost);
            }
            $q--;
        }
    }

    // Image creation
    $im = imagecreate($velikost, $velikost);
    $black = imagecolorallocate($im, 0, 0, 0);
    imagefill($im, 0, 0, $black);

    $colors = [];
    $colorMap = [
        1 => "5299F9", 2 => "545454", 3 => "EFF7FB", 4 => "F9F98D", 5 => "878787",
        6 => "5A2F00", 7 => "DCDCAC", 8 => "2A7302", 9 => "51F311", 10 => "535805",
        11 => "337EFA", 12 => "8ABC02", 13 => "8A9002"
    ];

    foreach ($mapa as $y => $row) {
        foreach ($row as $x => $p) {
            if (!isset($colors[$p])) {
                $color = $colorMap[$p] ?? "000000";
                $red = min(255, max(1, hexdec(substr($color, 0, 2))));
                $green = min(255, max(1, hexdec(substr($color, 2, 2))));
                $blue = min(255, max(1, hexdec(substr($color, 4, 2))));
                $colors[$p] = imagecolorallocate($im, $red, $green, $blue);
            }
            imagesetpixel($im, $x - 1, $y - 1, $colors[$p]);
        }
    }

    ob_start();
    imagepng($im);
    $src = ob_get_clean();
    $src = 'data:image/png;base64,' . base64_encode($src);
    
    imagedestroy($im);
} else {
    $src = 'default.png';
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Map Generator</title>
    <style>
        body { background-color: #FFFFFF; color: #000000; font-size: 14px; font-family: "Trebuchet MS", sans-serif; }
        h1 { font-size: 25px; }
        a { color: #5555dd; }
        .tabulka_hodnot { border-spacing: 8px; }
        .tabulka_hodnot tr td { text-align: left; font-weight: 600; height: 25px; }
        input[type="text"] { width: 55px; }
        input[type="submit"] { display: inline-block; font-weight: bold; font-size: 22px; color: #000000; background: #cccccc; border: 2px solid #444444; }
        .mapa { width: <?= $velikost ?>px; border: 2px solid #444444; box-shadow: 0 0 4px #111111; }
        .teren { width: 25px; height: 25px; box-shadow: 0 0 6px #000000; }
    </style>
</head>
<body>
<div style="text-align:center;">
    <h1>Map Generator</h1>
    <table align="center">
        <tr>
            <td>
                <form method="post" action="">
                    <table class="tabulka_hodnot">
                        <tr><td width="25"></td><td>Size:</td><td><input name="velikost" type="text" value="<?= $velikost ?>"></td></tr>
                        <tr><td bgcolor="#5299F9" class="teren"></td><td>Water [%]:</td><td><input name="voda" type="text" value="<?= $voda ?>"></td></tr>
                        <tr><td></td><td>Coast granularity:</td><td><input name="zrnitostostrova" type="text" value="<?= $zrnitostOstrova ?>"></td></tr>
                        <tr><td></td><td>Other granularity:</td><td><input name="zrnitostostatni" type="text" value="<?= $zrnitostOstatni ?>"></td></tr>
                        <tr><td bgcolor="#337EFA" class="teren"></td><td>River length [%]:</td><td><input name="delkarek" type="text" value="<?= $delkaRek ?>"></td></tr>
                        <tr><td bgcolor="#337EFA" class="teren"></td><td>River curvature [°]:</td><td><input name="tocivostrek" type="text" value="<?= $tocivostRek ?>"></td></tr>
                        <tr><td bgcolor="#337EFA" class="teren"></td><td>River centrality [%]:</td><td><input name="centralitarek" type="text" value="<?= $centralitaRek ?>"></td></tr>
                        <?php foreach ($tereny as $index => $value): ?>
                            <tr>
                                <td bgcolor="<?= $colorMap[$index + 2] ?>" class="teren"></td>
                                <td><?= ucfirst(array_keys($default)[$index]) ?> [%]:</td>
                                <td><input name="t<?= $index + 2 ?>" type="text" value="<?= $value ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <br>
                    <input type="submit" name="submit" value="Generate">
                </form>
            </td>
            <td>
                <img src="<?= $src ?>" alt="Generated Map" class="mapa">
            </td>
        </tr>
    </table>
    <br>
    &copy; Pavol Hejný | <a href="http://towns.cz" target="_blank">Towns.cz</a> | <?= date('Y') ?>
</div>
</body>
</html>
