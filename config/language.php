<?php
/**
 * Asha Bank — Multi-language support (English / বাংলা)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$GLOBALS['LANG_STRINGS'] = [
    'en' => [
        'dashboard' => 'Dashboard', 'welcome_back' => 'Welcome back',
        'available_balance' => 'Available Balance', 'send_money' => 'Send Money',
        'deposit' => 'Deposit', 'withdraw' => 'Withdraw', 'transfer' => 'Transfer',
        'profile' => 'Profile', 'logout' => 'Logout', 'login' => 'Login',
        'notifications' => 'Notifications', 'cards' => 'Cards', 'feedback' => 'Feedback',
        'recent_transactions' => 'Recent Transactions', 'view_all' => 'View all',
        'account_number' => 'Account Number', 'tier' => 'Tier',
    ],
    'bn' => [
        'dashboard' => 'ড্যাশবোর্ড', 'welcome_back' => 'ফিরে আসার জন্য স্বাগতম',
        'available_balance' => 'উপলব্ধ ব্যালেন্স', 'send_money' => 'টাকা পাঠান',
        'deposit' => 'জমা', 'withdraw' => 'উত্তোলন', 'transfer' => 'স্থানান্তর',
        'profile' => 'প্রোফাইল', 'logout' => 'লগআউট', 'login' => 'লগইন',
        'notifications' => 'বিজ্ঞপ্তি', 'cards' => 'কার্ড', 'feedback' => 'মতামত',
        'recent_transactions' => 'সাম্প্রতিক লেনদেন', 'view_all' => 'সব দেখুন',
        'account_number' => 'হিসাব নম্বর', 'tier' => 'স্তর',
    ],
];

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$GLOBALS['CURRENT_LANG'] = $_SESSION['lang'] ?? 'en';

function t($key) {
    $lang = $GLOBALS['CURRENT_LANG'] ?? 'en';
    return $GLOBALS['LANG_STRINGS'][$lang][$key] ?? $GLOBALS['LANG_STRINGS']['en'][$key] ?? $key;
}
