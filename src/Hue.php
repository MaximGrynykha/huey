<?php

namespace Ismaxim\Huey;

use Ismaxim\Pathfinder\Path;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;

final class Hue
{
    private string $image_path;
    private string $storage_path;

    public function __construct(
        string $image_path,
        string $storage_path
    ) {
        set_time_limit(0);

        $this->image_path = Path::to($image_path);
        $this->storage_path = Path::to($storage_path);
    }

    /**
     * Generate and cache images by spinning the hue of the base image on the color wheel.
     * 
     * If $from_degrees set to 0, spinning begins from 0 degrees on the color wheel 
     * (0 degrees is a color of the base image) and moves forward by $direction
     * (clockwise / counterclockwise) from the color of the base image by the scheme 
     * of the color wheel based on _[Farbkreis](https://w.wiki/3ukh)_ 
     * by _[Johannes Itten](https://w.wiki/3ukj)_ to $to_degrees value.
     * 
     * ⚠️ This process can take a long time.
     * 
     * @param int $from_degrees
     * @param int $to_degrees
     * @param int $step
     * 
     * @return void
     */
    public function spin(int $from_degree, int $to_degree, int $step_degree = 1): void
    {
        $step_degree = abs($step_degree);

        if ($from_degree < 0 || $from_degree > 360) {
            throw new \Exception("The degrees value need to be in the range: 0-360");
        }
        if ($to_degree < 0 || $to_degree > 360) {
            throw new \Exception("The degrees value need to be in the range: 0-360");
        }

        $counter = 0;
        if ($from_degree <= $to_degree) {
            $step_count = floor(($to_degree - $from_degree) / $step_degree); 
        } else {
            $step_count = floor(($to_degree - $from_degree + 360) / $step_degree);
        }
        
        $current_degree = $from_degree;
        // dd($step_count);
        while ($counter <= $step_count) {
            $spin_image = $this->imagehue($current_degree);
            $spin_image->save($this->storage_path."test_".($current_degree).".png", 100);

            $current_degree += $step_degree; 
            $counter++;
        }


        // for ($i = $from_degree; $i <= $to_degree and $i >= $to_degree - 360; $i += $step_degree) {
            // $spin_image = $this->imagehue($i);
            // $spin_image->save($storage."hue_".($i).".png", 100);
        // }

    }

    /**
     * @param int $angle
     * 
     * @return Image
     */
    private function imagehue(int $angle): Image
    {
        $image_manager = new ImageManager();

        $image = $image_manager
            ->make($this->image_path)
            ->getCore();

        $width = imagesx($image);
        $height = imagesy($image);

        // if ($angle % 360 === 0) return null;

        $colors_cache = [];

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($image, $x, $y);

                if (! isset($colors_cache[$rgb])) {
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;            
                    $alpha = ($rgb & 0x7F000000) >> 24;
    
                    [$h, $s, $l] = $this->rgb2hsl($r, $g, $b);
                    
                    $h = $h * 360 + $angle;

                    if ($h < 0) {
                        $h = (360 - abs($h)) + (360 * floor(abs($h) / 360));
                    }
                    if ($h >= 360) {
                        $h = $h - floor($h / 360) * 360;
                    }
                    
                    $h /= 360; 

                    
                    // $h += $angle / 360;
                    // if ($h > 1) $h--;
    
                    [$r, $g, $b] = $this->hsl2rgb($h, $s, $l);
    
                    $colors_cache[$rgb] = imagecolorallocatealpha($image, $r, $g, $b, $alpha);
                } 
                
                imagesetpixel($image, $x, $y, $colors_cache[$rgb]);
            }
        }

        return $image_manager->make($image);
    }

    /**
     * @param int|float $r
     * @param int|float $g
     * @param int|float $b
     * 
     * @return array
     */
    private function rgb2hsl($r, $g, $b): array
    {
        $var_R = ($r / 255);
        $var_G = ($g / 255);
        $var_B = ($b / 255);
    
        $var_Min = min($var_R, $var_G, $var_B);
        $var_Max = max($var_R, $var_G, $var_B);
        $del_Max = $var_Max - $var_Min;
    
        $v = $var_Max;
    
        if ($del_Max == 0) {
            $h = 0;
            $s = 0;
        } else {
            $s = $del_Max / $var_Max;
    
            $del_R = ((($var_Max - $var_R ) / 6) + ($del_Max / 2)) / $del_Max;
            $del_G = ((($var_Max - $var_G ) / 6) + ($del_Max / 2)) / $del_Max;
            $del_B = ((($var_Max - $var_B ) / 6) + ($del_Max / 2)) / $del_Max;
    
            if     ($var_R == $var_Max) $h = $del_B - $del_G;
            elseif ($var_G == $var_Max) $h = (1 / 3) + $del_R - $del_B;
            elseif ($var_B == $var_Max) $h = (2 / 3) + $del_G - $del_R;
    
            if ($h < 0) $h++;
            if ($h > 1) $h--;
        }
    
        return [$h, $s, $v];
    }

    /**
     * @param int|float $h
     * @param int|float $s
     * @param int|float $v
     * 
     * @return array
     */
    private function hsl2rgb($h, $s, $v): array
    {
        if ($s == 0) {
            $r = $g = $B = $v * 255;
        } else {
            $var_H = $h * 6;
            $var_i = floor($var_H);
            $var_1 = $v * (1 - $s);
            $var_2 = $v * (1 - $s * ($var_H - $var_i));
            $var_3 = $v * (1 - $s * (1 - ($var_H - $var_i)));
    
            if     ($var_i == 0) { $var_R = $v     ; $var_G = $var_3  ; $var_B = $var_1 ; }
            elseif ($var_i == 1) { $var_R = $var_2 ; $var_G = $v      ; $var_B = $var_1 ; }
            elseif ($var_i == 2) { $var_R = $var_1 ; $var_G = $v      ; $var_B = $var_3 ; }
            elseif ($var_i == 3) { $var_R = $var_1 ; $var_G = $var_2  ; $var_B = $v     ; }
            elseif ($var_i == 4) { $var_R = $var_3 ; $var_G = $var_1  ; $var_B = $v     ; }
            else                 { $var_R = $v     ; $var_G = $var_1  ; $var_B = $var_2 ; }
    
            $r = $var_R * 255;
            $g = $var_G * 255;
            $B = $var_B * 255;
        }    

        return [$r, $g, $B];
    }
}