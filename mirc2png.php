<?php
/*
 * Great resource https://modern.ircdocs.horse/formatting.html
 */
function loadfile($file) {
    $cont = file_get_contents($file);
    //php apparently sucked at its detection so just checking this manually
    if($cont[0] == "\xFF" && $cont[1] == "\xFE") {
        //UTF-16LE is best bet then fallback to the auto
        if(mb_check_encoding($cont, "UTF-16LE")) {
            $cont = mb_convert_encoding($cont, "UTF-8", "UTF-16LE");
        } else {
            $cont = mb_convert_encoding($cont, "UTF-8");
        }
    }
    $cont = str_replace("\r", "\n", $cont);
    return array_filter(explode("\n", $cont));
}

function stripcodes(string $text, $color = true): string {
    $text = str_replace("\x02", "", $text);
    $text = str_replace("\x1D", "", $text);
    $text = str_replace("\x1F", "", $text);
    $text = str_replace("\x1E", "", $text);
    $text = str_replace("\x11", "", $text);
    $text = str_replace("\x16", "", $text);
    $text = str_replace("\x0F", "", $text);
    if(!$color)
        return $text;
    $colorRegex = "/\x03(\d?\d?)(,\d\d?)?/";
    return preg_replace($colorRegex, '', $text);
}

require_once 'colors.php';

function convert(string $mircfile, string $pngfile, $size = 12, $font = "./Hack-Regular.ttf") {
    $text = loadfile($mircfile);
    if(empty($text)) {
        echo "$mircfile is empty?\n";
        return;
    }
    $width = 0;
    $height = count($text);
    foreach($text as $line) {
        $width = max($width, mb_strlen(stripcodes($line)));
    }
    //So much for easy monospace, i think this may be the biggest char, this function will reduce size if a char is smaller than its box
    $lol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $rect = imagettfbbox($size, 0, $font, $lol);
    $minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
    $charW = ($maxX - $minX) / strlen($lol);
    $rect = imagettfbbox($size, 0, $font, "|");
    $minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));
    $charH = $maxY - $minY;
    var_dump([$charH, $charW]);
    $height *= $charH;
    $width *= ($charW);

    //add a px or two why not
    $im = imagecreatetruecolor($width + 1, $height);

    $colors = gimmeColors($im);

    $lineno = count($text) -1;
    foreach(array_reverse($text) as $line) {
        $fg = $colors[0];
        $bg = $colors[1];
        $curX = 0;
        $colorRegex = "/^\x03(\d?\d?)(,\d\d?)?/";
        $line = stripcodes($line, false);
        for($i=0; $i < strlen($line); $i++) {
            $rem = substr($line, $i);
            if(preg_match($colorRegex, $rem, $m)) {
                $i += strlen($m[0]);
                if(isset($m[1]) && $m[1] != "")
                    $fg = $colors[(int)$m[1]];
                if(isset($m[2])) {
                    $m[2] = substr($m[2], 1);
                    $bg = $colors[(int)$m[2]];
                }
            }
            $nextC = strpos($line, "\x03", $i);
            if($nextC === false) {
                $chunk = substr($line, $i);
                $i = strlen($line);
            } else {
                $chunk = substr($line, $i, $nextC - $i);
                $i = $nextC-1;
            }
            $x = $curX * $charW;
            $y = $lineno * $charH;
            imagefilledrectangle($im, $x, $y, $x + ($charW * mb_strlen($chunk)), $y + $charH, $bg);
            // $y + $charH because lol fonts start at bottom coord..
            $box = imagettftext($im, $size, 0, $x, $y + $charH, $fg, $font, $chunk);
            $curX += mb_strlen($chunk);
        }
        $lineno--;
    }
    imagepng($im, $pngfile);
    imagedestroy($im);

}

convert($argv[1], pathinfo($argv[1], PATHINFO_FILENAME) . '.png');











