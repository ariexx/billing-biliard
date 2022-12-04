<?php

if(!function_exists('rupiah')){
    function rupiah(int $angka): string
    {
         return "Rp " . number_format($angka,2,',','.');
    }
}
