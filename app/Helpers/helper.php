<?php

use Carbon\Carbon;

function generateSku(string $title, string $categoryId): string
{
    // Get the first three letters of the title
    $titlePart = strtoupper(substr(preg_replace('/\s+/', '', $title), 0, 3));

    // Get the category code (you might need to fetch this from the database)
    $categoryPart = str_pad($categoryId, 3, '0', STR_PAD_LEFT);

    // Generate a random 4-digit number
    $randomNumber = mt_rand(1000, 9999);

    // Combine parts to form SKU
    return $titlePart . '-' . $categoryPart . '-' . $randomNumber;
}

function generateSetupCode($length = 12)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}