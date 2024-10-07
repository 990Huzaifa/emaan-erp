<?php

use Carbon\Carbon;
use App\Models\ChartOfAccount;

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

function generateSetupCode($length = 64)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}


function createCOA($name, $parent_code, $ref_id): ChartOfAccount
{
    $newCode = null;
    for ($i = 1; $i <= 9; $i++) {
        $generatedCode = $parent_code . '-' . $i;
        if (!ChartOfAccount::where('code', $generatedCode)->exists()) {
            $newCode = $generatedCode;
            break;
        }
    }

    if ($newCode === null) {
        throw new Exception('Unable to generate a unique code.', 400);
    }

    $parentCodeParts = explode('-', $parent_code);
    $numLevels = count($parentCodeParts);

    $level1 = isset($parentCodeParts[0]) ? $parentCodeParts[0] : '0';
    $level2 = isset($parentCodeParts[1]) ? $parentCodeParts[1] : '0';
    $level3 = isset($parentCodeParts[2]) ? $parentCodeParts[2] : '0';
    $level4 = isset($parentCodeParts[3]) ? $parentCodeParts[3] : '0';
    $level5 = isset($parentCodeParts[4]) ? $parentCodeParts[4] : '0';

    if ($numLevels == 1) {
        $level2 = $i;
    } elseif ($numLevels == 2) {
        $level3 = $i;
    } elseif ($numLevels == 3) {
        $level4 = $i;
    } elseif ($numLevels == 4) {
        $level5 = $i;
    }

    return ChartOfAccount::create([
        'name' => $name,
        'ref_id' => $ref_id,
        'parent_code' => $parent_code,
        'level1' => $level1,
        'level2' => $level2,
        'level3' => $level3,
        'level4' => $level4,
        'level5' => $level5,
    ]);
}