<?php
/*
 * Great resource https://modern.ircdocs.horse/formatting.html
 */

require_once "vendor/autoload.php";

use knivey\tools;
use knivey\irctools;

require_once 'colors.php';

function getSizes($font, $size) {
    static $cache = [];

    if(isset($cache["$font $size"])) {
        return $cache["$font $size"];
    }

    //So much for easy monospace, this string should give us an idea to calc text block size, the imagettfbox function only gives bare min to draw
    $lol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $rect = imagettfbbox($size, 0, $font, $lol);
    $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $charW = ($maxX - $minX) / strlen($lol);
    $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $charH = $maxY - $minY;
    //Some chars hang below baseline, try to see how much for a little nicer render
    $lol = "$lol;y,_";
    $rect = imagettfbbox($size, 0, $font, $lol);
    $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $riseup = ($maxY - $minY) - $charH;
    //this will be our total height
    $rect = imagettfbbox($size, 0, $font, '|');
    $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $charH = ($maxY - $minY) + $riseup;


    return $cache["$font $size"] = [$charH, $charW, $riseup];
}

function convert(string $mircfile, string $pngfile, $size = 12, $font = "./Hack-Regular.ttf") {
    $text = irctools\loadartfile($mircfile);
    if(empty($text)) {
        echo "$mircfile is empty?\n";
        return;
    }
    $width = 0;
    $height = count($text);
    if($height > 9000) {
        echo "$mircfile very very big skipping it\n";
        return;
    }
    foreach($text as $line) {
        $width = max($width, mb_strlen(irctools\stripcodes($line)));
    }

    list($charH, $charW, $riseup) = getSizes($font, $size);
    $height *= $charH;
    $width *= $charW;

    //add a px or two why not
    $im = imagecreatetruecolor($width + 1, $height);

    $colors = gimmeColors($im);

    $lineno = count($text) -1;
    foreach(array_reverse($text) as $line) {
        $fg = $colors[0];
        $bg = $colors[1];
        $curX = 0;
        $colorRegex = "/^\x03(\d?\d?)(,\d\d?)?/";
        $line = irctools\stripcodes($line, false, false);
        for($i=0; $i < strlen($line); $i++) {
            $rem = substr($line, $i);

            if(preg_match($colorRegex, $rem, $m)) {
                $i += strlen($m[0]); // Don't sub 1 because later we locate next color code
                if(isset($m[1]) && $m[1] != "")
                    $fg = $colors[(int)$m[1]];
                else
                    if(!isset($m[2])) {
                        $fg = $colors[0];
                        $bg = $colors[1];
                    }
                if(isset($m[2])) {
                    $m[2] = substr($m[2], 1);
                    $bg = $colors[(int)$m[2]];
                }
            }
            if(!isset($line[$i])) //Line could have ended with a color code
                continue;
            //Format reset
            if($line[$i] == "\x0F") {
                $fg = $colors[0];
                $bg = $colors[1];
                $i++;
            }
            $nextCode = strcspn($line, "\x03\x0F", $i) + $i;
            $chunk = substr($line, $i, $nextCode - $i);
            $i = $nextCode-1;

            $x = $curX * $charW;
            $y = ($lineno * $charH) - $riseup;
            imagefilledrectangle($im, $x, $y, $x + ($charW * mb_strlen($chunk)), $y + $charH, $bg);
            // $y + $charH because lol fonts start at bottom coord..
            imagettftext($im, $size, 0, $x, $y + $charH - $riseup, $fg, $font, $chunk);
            $curX += mb_strlen($chunk);
        }
        $lineno--;
    }
    imagepng($im, $pngfile);
    imagedestroy($im);
}


//convert($argv[1], pathinfo($argv[1], PATHINFO_FILENAME) . '.png');
$files = tools\dirtree($argv[1]);
if($files === false) {
    die("Bad directory: $argv[1]\nFirst argument directory of mirc art txt files, Second arguments where to save pngs of all files found.\n");
}

if(!is_dir($argv[2])) {
    die("$argv[2] is not directory\n");
}
//var_dump($files);
if(count($files) == 0) {
    die("No txt files found\n");
}

$bdir = $argv[1];
if($bdir[strlen($bdir)-1] != '/') {
    $bdir = "$bdir/";
}

echo "Conversion starting...\r";
$total = count($files);
$num = 0;
foreach(tools\dirtree($bdir) as $file) {
    $num++;
    echo "[$num/$total] Converting " . substr($file, strlen($bdir)) . "\n";
    $out = $argv[2] ?? 'out';
    $outRelative = substr($file, strlen($bdir));
    $outDir = $out . '/' . pathinfo($outRelative, PATHINFO_DIRNAME);
    if(!file_exists($outDir))
        mkdir($outDir, 0777, true);
    $outFile = pathinfo($outRelative, PATHINFO_FILENAME) . '.png';
    convert($file, $outDir . '/' . $outFile);
}
echo "\nDone!\n";







