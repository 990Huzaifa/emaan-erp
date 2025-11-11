<?php

use App\Events\NotificationSent;
use App\Models\BusinessHasAccount;
use App\Models\OpeningBalance;
use App\Models\SaleVoucher;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserHasBusiness;
use App\Notifications\GeneralNotification;
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


function createCOA($name, $parent_code): ChartOfAccount
{
    $newCode = null;
    $i=1;
    while ($newCode === null) {
        $generatedCode = $parent_code . '-' . $i;
        if (!ChartOfAccount::where('code', $generatedCode)->exists()) {
            $newCode = $generatedCode;
            break;
        }
        $i++;
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
    $name = strtoupper($name);
    return ChartOfAccount::create([
        'name' => $name,
        'parent_code' => $parent_code,
        'code' => $newCode,
        'level1' => $level1,
        'level2' => $level2,
        'level3' => $level3,
        'level4' => $level4,
        'level5' => $level5,
    ]);
}

// function updateCOA($id, $name, $parent_code): ChartOfAccount
// {
//     $acc = ChartOfAccount::find($id);
//     if (empty($acc)) {
//         throw new Exception('Chart of Account not found', 404);
//     }

//     $oldCode = $acc->code;

//     // Check if the parent_code has changed
//     if ($acc->parent_code != $parent_code) {
//         // Generate a new code based on the new parent_code
//         $newCode = null;
//         for ($i = 1; $i <= 9; $i++) {
//             $generatedCode = $parent_code . '-' . $i;
//             if (!ChartOfAccount::where('code', $generatedCode)->exists()) {
//                 $newCode = $generatedCode;
//                 break;
//             }
//         }

//         if ($newCode === null) {
//             throw new Exception('Unable to generate a unique code.', 400);
//         }

//         // Update levels based on the new parent_code
//         $parentCodeParts = explode('-', $parent_code);
//         $numLevels = count($parentCodeParts);

//         $level1 = isset($parentCodeParts[0]) ? $parentCodeParts[0] : '0';
//         $level2 = isset($parentCodeParts[1]) ? $parentCodeParts[1] : '0';
//         $level3 = isset($parentCodeParts[2]) ? $parentCodeParts[2] : '0';
//         $level4 = isset($parentCodeParts[3]) ? $parentCodeParts[3] : '0';
//         $level5 = isset($parentCodeParts[4]) ? $parentCodeParts[4] : '0';

//         if ($numLevels == 1) {
//             $level2 = $i;
//         } elseif ($numLevels == 2) {
//             $level3 = $i;
//         } elseif ($numLevels == 3) {
//             $level4 = $i;
//         } elseif ($numLevels == 4) {
//             $level5 = $i;
//         }

//         // Update the account with the new code and levels
//         $acc->code = $newCode;
//         $acc->parent_code = $parent_code;
//         $acc->level1 = $level1;
//         $acc->level2 = $level2;
//         $acc->level3 = $level3;
//         $acc->level4 = $level4;
//         $acc->level5 = $level5;
//     }

//     // Update the name regardless of parent_code changes
//     $acc->name = $name;

//     $acc->save();

//     // If the code has changed, update all child accounts recursively
//     if ($acc->code != $oldCode) {
//         updateChildAccounts($acc, $oldCode, $acc->code);
//     }

//     return $acc;
// }


function updateCOA($id, $name, $parent_code): ChartOfAccount
{
    $acc = ChartOfAccount::find($id);
    if (empty($acc)) {
        throw new Exception('Chart of Account not found', 404);
    }

    $oldCode = $acc->code;

    // Check if the parent_code has changed
    if ($acc->parent_code != $parent_code) {
        // Generate a new code based on the new parent_code
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

        // Update levels based on the new parent_code
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

        // Update the account with the new code and levels
        $acc->code = $newCode;
        $acc->name = $name;
        $acc->parent_code = $parent_code;
        $acc->level1 = $level1;
        $acc->level2 = $level2;
        $acc->level3 = $level3;
        $acc->level4 = $level4;
        $acc->level5 = $level5;
    }

    // Update the name regardless of parent_code changes
    $acc->name = $name;

    $acc->save();

    return $acc;
}

// Helper function to update child accounts recursively
function updateChildAccounts($parentAcc, $oldParentCode, $newParentCode)
{
    $children = ChartOfAccount::where('parent_code', $oldParentCode)->get();
    foreach ($children as $child) {
        $oldChildCode = $child->code;

        // Replace the old parent code with the new parent code in the child's code
        $newChildCode = preg_replace(
            '/^' . preg_quote($oldParentCode, '/') . '/',
            $newParentCode,
            $child->code
        );

        // Update levels based on the new code
        $codeParts = explode('-', $newChildCode);
        $level1 = isset($codeParts[0]) ? $codeParts[0] : '0';
        $level2 = isset($codeParts[1]) ? $codeParts[1] : '0';
        $level3 = isset($codeParts[2]) ? $codeParts[2] : '0';
        $level4 = isset($codeParts[3]) ? $codeParts[3] : '0';
        $level5 = isset($codeParts[4]) ? $codeParts[4] : '0';

        $child->code = $newChildCode;
        $child->parent_code = $newParentCode;
        $child->level1 = $level1;
        $child->level2 = $level2;
        $child->level3 = $level3;
        $child->level4 = $level4;
        $child->level5 = $level5;

        $child->save();

        // Recursively update the child accounts of this child
        updateChildAccounts($child, $oldChildCode, $newChildCode);
    }
}


function calculateBalance($acc_id, $change, $isDebit = true): float
{
    $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    if ($lastTransaction) {
        return $isDebit ? $lastTransaction->current_balance + $change : $lastTransaction->current_balance - $change;
    }
    $openingBalance = OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0;
    return $isDebit ? $openingBalance + $change : $openingBalance - $change;
}


function currentBalance($acc_id, $date = null)
{
    if($date){
        $lastTransaction = Transaction::where('acc_id', $acc_id)
            ->whereDate('created_at', '<=', $date)
            ->orderBy('id', 'desc')
            ->first();
    }else{
        $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    }
    if (!empty($lastTransaction)) {
        return $lastTransaction->current_balance;
    }else{
        $openingBalance = OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0;
        return $openingBalance;
    }
}

function calculateBalancePartners($acc_id, $change, $isDebit = true): float
{
    $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    if ($lastTransaction) {
        return $isDebit ? $lastTransaction->current_balance - $change : $lastTransaction->current_balance + $change;
    }
    return $isDebit ? 0 - $change : 0 + $change;
}


function notUsedfunc($acc_id, $change, $isDebit = true): float
{
    // Retrieve the account type
    $accountType = ChartOfAccount::where('id', $acc_id)->value('level1');
    
    if (!$accountType) {
        throw new Exception("Account type not found for account ID: {$acc_id}");
    }

    // Retrieve the last transaction
    $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    $last_balance = $lastTransaction ? $lastTransaction->current_balance : (OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0);

    // Determine the new balance based on account type and transaction nature
    switch ($accountType) {
        case '1':  // Assets
        case '2':  // Liabilities
        case '3':  // Equity
        case '4':  // Expenses
            // For assets and expenses, Debit increases and Credit decreases
            return $isDebit ? ($last_balance + $change) : ($last_balance - $change);
        case '5':  // Revenue
             // For liabilities, equity, and revenues, Debit decreases and Credit increases
             return $isDebit ? ($last_balance - $change) : ($last_balance + $change);

           
        
        default:
            throw new Exception("Unsupported account type: {$accountType}");
    }
}


function notifyUser($user_id, $business_id,$permission, $message,$url = null){

    $businesses = UserHasBusiness::where('business_id',$business_id)->get();

    foreach ($businesses as $businessAccount) {
        $user = User::find($businessAccount->user_id);
        $user->hasBusinessPermission($business_id, $permission);

        if ($user->hasBusinessPermission($business_id, $permission)) {
            $user->notify(new GeneralNotification($message, $user->id, $url));
            broadcast(new NotificationSent($message, $user->id, $url));
        }
    }
}

function notifyUserWelcome($user_id,$message){

    $user = User::find($user_id);

    $user->notify(new GeneralNotification($message, $user->id));
    broadcast(new NotificationSent($message, $user->id));
}


function timeLimit($id)
{
    $latestVoucher = SaleVoucher::where('customer_id', $id)
        ->orderBy('id', 'desc')
        ->first();

        if (!$latestVoucher) {
            return 1; // No previous record means no restriction
        }
    
        // If approve_date is NULL, it means not paid, so return 0
        if (is_null($latestVoucher->approve_date)) {
            return 0;
        }

        $daysDifference = Carbon::parse($latestVoucher->approve_date)
        ->diffInDays(Carbon::parse($latestVoucher->voucher_date));

    return $daysDifference > 30 ? 0 : 1;
}


// function convertNumberToWords($number)
// {
//     $number = (int)$number;
//     $words = [];
//     $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
//     $teens = ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
//     $tens = ['', 'ten', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
//     $thousands = ['', 'thousand', 'lakh', 'crore', 'billion'];

//     $numberString = (string)$number;

//     // Handle lakh and crore by dividing the number accordingly.
//     $chunks = [];
//     if (strlen($numberString) > 6) {
//         // For numbers larger than a crore
//         $chunks[] = substr($numberString, 0, strlen($numberString) - 7); // Crore place (first part)
//         $chunks[] = substr($numberString, -7, 2); // Lakh place
//         $numberString = substr($numberString, -5); // Remaining part after lakh
//     }

//     // Breaking number into chunks of 3 digits (for thousands, hundreds)
//     $chunks = array_merge($chunks, str_split($numberString, 3));

//     foreach ($chunks as $key => $value) {
//         $currentPart = (int)$value;
//         $currentWords = [];

//         if ($currentPart > 99) {
//             $currentWords[] = $ones[(int)($currentPart / 100)] . ' hundred';
//             $currentPart %= 100;
//         }

//         if ($currentPart > 19) {
//             $currentWords[] = $tens[(int)($currentPart / 10)];
//             $currentPart %= 10;
//         }

//         if ($currentPart > 0) {
//             $currentWords[] = $ones[$currentPart];
//         }

//         if (!empty($currentWords)) {
//             // Add the corresponding place value
//             $words[] = implode(' ', $currentWords) . ' ' . $thousands[$key];
//         }
//     }

//     return ucfirst(implode(' ', array_reverse($words)));
// }

function convertNumberToWords($number)
    {
        if ($number < 0) return false;

        // Arrays to hold words for single-digit, double-digit, and below-hundred numbers
        $single_digit = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        $double_digit = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $below_hundred = ['Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number === 0) return 'Zero';

        // Recursive function to translate the number into words
        function translate($n, $single_digit, $double_digit, $below_hundred)
        {
            $word = "";
            if ($n < 10) {
                $word = $single_digit[$n] . ' ';
            } else if ($n < 20) {
                $word = $double_digit[$n - 10] . ' ';
            } else if ($n < 100) {
                $rem = translate($n % 10, $single_digit, $double_digit, $below_hundred);
                $word = $below_hundred[floor($n / 10) - 2] . ' ' . $rem;
            } else if ($n < 1000) {
                $word = $single_digit[floor($n / 100)] . ' Hundred ' . translate($n % 100, $single_digit, $double_digit, $below_hundred);
            } else if ($n < 1000000) {
                $word = translate(floor($n / 1000), $single_digit, $double_digit, $below_hundred) . 'Thousand ' . translate($n % 1000, $single_digit, $double_digit, $below_hundred);
            } else if ($n < 1000000000) {
                $word = translate(floor($n / 1000000), $single_digit, $double_digit, $below_hundred) . 'Million ' . translate($n % 1000000, $single_digit, $double_digit, $below_hundred);
            } else if ($n < 1000000000000) {
                $word = translate(floor($n / 1000000000), $single_digit, $double_digit, $below_hundred) . 'Billion ' . translate($n % 1000000000, $single_digit, $double_digit, $below_hundred);
            } else {
                $word = translate(floor($n / 1000000000000), $single_digit, $double_digit, $below_hundred) . 'Trillion ' . translate($n % 1000000000000, $single_digit, $double_digit, $below_hundred);
            }
            return $word;
        }

        // Get the result by translating the given number
        $result = translate($number, $single_digit, $double_digit, $below_hundred);
        return trim($result);
    }


function calculateDebitBalance($acc_id, $debit_amount): float
{
    $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    $currentBalance = $lastTransaction ? $lastTransaction->current_balance : 
                        (OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0);

    // Determine the nature of the account using the helper
    $majorType = getAccountMajorType($acc_id); 

    // Rule: ASSET/EXPENSE have a Debit balance, so Debit increases the balance.
    // Rule: LIABILITY/EQUITY/REVENUE have a Credit balance, so Debit decreases the balance.
    
    if (in_array($majorType, ['ASSET', 'EXPENSE'])) {
        return $currentBalance + $debit_amount; // Debit ADD
    } else { 
        return $currentBalance - $debit_amount; // Debit SUBTRACT
    }
}

function calculateCreditBalance($acc_id, $credit_amount): float
{
    $lastTransaction = Transaction::where('acc_id', $acc_id)->orderBy('id', 'desc')->first();
    $currentBalance = $lastTransaction ? $lastTransaction->current_balance : 
                        (OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0);

    // Determine the nature of the account using the helper
    $majorType = getAccountMajorType($acc_id); 

    // Rule: LIABILITY/EQUITY/REVENUE have a Credit balance, so Credit increases the balance.
    // Rule: ASSET/EXPENSE have a Debit balance, so Credit decreases the balance.

    if (in_array($majorType, ['LIABILITY', 'EQUITY', 'REVENUE'])) {
        return $currentBalance + $credit_amount; // Credit ADD
    } else {
        return $currentBalance - $credit_amount; // Credit SUBTRACT
    }
}

function getAccountMajorType($acc_id): string
{
    // Fetch the account entry
    $account = ChartOfAccount::where('id', $acc_id)->first(); 

    if (!$account) {
        // Handle error: account not found
        return 'Unknown'; 
    }

    // Use the level1 code to determine the major type
    $majorCode = $account->level1;

    switch ($majorCode) {
        case '1': return 'ASSET';
        case '2': return 'LIABILITY';
        case '3': return 'EQUITY';
        case '4': return 'EXPENSE';
        case '5': return 'REVENUE';
        default: return 'UNKNOWN';
    }
}


function getCOGS($businessId){
    $businessAccs = BusinessHasAccount::where('business_id', $businessId)->pluck('chart_of_account_id')->toArray();

    $acc = ChartOfAccount::where('name' , 'COGS')->whereIn('id', $businessAccs)->first();
    return  $acc->id;


}


function getRevenueAccount($businessId){
    $businessAccs = BusinessHasAccount::where('business_id', $businessId)->pluck('chart_of_account_id')->toArray();

    $acc = ChartOfAccount::where('level1' , '5')
    ->where('name','SALES REVENUE')
    ->whereIn('id', $businessAccs)->first();
    return  $acc->id;
}