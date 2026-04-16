<?php
// Language configuration file
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default language to English if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

// Language switching
if (isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
    // Redirect back to same page without lang parameter
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    header("Location: $redirect_url");
    exit();
}

// Translations array
$lang = [
    'en' => [
        // Navigation
        'home' => 'Home',
        'dashboard' => 'Dashboard',
        'transfer' => 'Transfer',
        'deposit' => 'Deposit',
        'withdraw' => 'Withdraw',
        'nominee' => 'Nominee',
        'profile' => 'Profile',
        'login' => 'Login',
        'logout' => 'Logout',
        'register' => 'Register',
        'open_account' => 'Open Account',
        'notifications' => 'Notifications',
        'mark_all_read' => 'Mark all read',
        'no_notifications' => 'No notifications',
        
        // Dashboard
        'current_balance' => 'Current Balance',
        'account_number' => 'Account Number',
        'total_sent' => 'Total Sent',
        'total_received' => 'Total Received',
        'kyc_status' => 'KYC Status',
        'pending' => 'Pending',
        'verified' => 'Verified',
        'quick_actions' => 'Quick Actions',
        'recent_transactions' => 'Recent Transactions',
        'no_transactions' => 'No transactions yet',
        'click_for_card' => 'Click for card details',
        'not_added' => 'Not Added',
        
        // Transaction types
        'date' => 'Date',
        'type' => 'Type',
        'amount' => 'Amount',
        'status' => 'Status',
        'completed' => 'Completed',
        'pending_status' => 'Pending',
        
        // Forms
        'receiver_account' => 'Receiver Account Number',
        'enter_amount' => 'Enter amount',
        'description' => 'Description',
        'send_money' => 'Send Money',
        'confirm_deposit' => 'Confirm Deposit',
        'confirm_withdrawal' => 'Confirm Withdrawal',
        'save_changes' => 'Save Changes',
        'update_profile' => 'Update Profile',
        'change_password' => 'Change Password',
        
        // KYC
        'kyc_verification' => 'KYC Verification',
        'phone_number' => 'Phone Number',
        'verification_code' => 'Verification Code',
        'send_code' => 'Send Verification Code',
        'verify_now' => 'Verify Now',
        'resend_code' => 'Resend Code',
        
        // Messages
        'welcome_back' => 'Welcome back',
        'login_success' => 'Login successful',
        'transfer_success' => 'Transfer successful',
        'deposit_success' => 'Deposit successful',
        'withdraw_success' => 'Withdrawal successful',
        'profile_updated' => 'Profile updated successfully',
        'nominee_updated' => 'Nominee updated successfully',
        'password_changed' => 'Password changed successfully',
        'password_mismatch' => 'New password does not match',
        'wrong_password' => 'Current password is incorrect',
        'invalid_amount' => 'Please enter a valid amount',
        'insufficient_balance' => 'Insufficient balance',
        'account_not_found' => 'Account not found',
        'cannot_transfer_self' => 'Cannot transfer to your own account',
        'transfer_failed' => 'Transfer failed',
        'deposit_failed' => 'Deposit failed',
        'withdraw_failed' => 'Withdrawal failed',
        
        // Back button
        'back_to_dashboard' => 'Back to Dashboard',
        
        // Card
        'card_details' => 'Card Details',
        'card_number' => 'Card Number',
        'expiry_date' => 'Expiry Date',
        'cvv' => 'CVV',
        'card_holder' => 'Card Holder Name',
        'expires' => 'Expires',
    ],
    'bn' => [
        // Navigation
        'home' => 'হোম',
        'dashboard' => 'ড্যাশবোর্ড',
        'transfer' => 'টাকা স্থানান্তর',
        'deposit' => 'টাকা জমা',
        'withdraw' => 'টাকা উত্তোলন',
        'nominee' => 'নামমাত্র',
        'profile' => 'প্রোফাইল',
        'login' => 'লগইন',
        'logout' => 'লগআউট',
        'register' => 'রেজিস্টার',
        'open_account' => 'একাউন্ট খুলুন',
        'notifications' => 'নোটিফিকেশন',
        'mark_all_read' => 'সব পড়া হয়েছে',
        'no_notifications' => 'কোনো নোটিফিকেশন নেই',
        
        // Dashboard
        'current_balance' => 'বর্তমান ব্যালেন্স',
        'account_number' => 'একাউন্ট নম্বর',
        'total_sent' => 'মোট পাঠানো',
        'total_received' => 'মোট প্রাপ্ত',
        'kyc_status' => 'কেওয়াইসি স্ট্যাটাস',
        'pending' => 'বাকি',
        'verified' => 'ভেরিফাইড',
        'quick_actions' => 'দ্রুত কর্ম',
        'recent_transactions' => 'সাম্প্রতিক লেনদেন',
        'no_transactions' => 'কোনো লেনদেন নেই',
        'click_for_card' => 'কার্ডের বিবরণ দেখতে ক্লিক করুন',
        'not_added' => 'যোগ করা হয়নি',
        
        // Transaction types
        'date' => 'তারিখ',
        'type' => 'ধরন',
        'amount' => 'পরিমাণ',
        'status' => 'স্ট্যাটাস',
        'completed' => 'সম্পন্ন',
        'pending_status' => 'বাকি',
        
        // Forms
        'receiver_account' => 'প্রাপকের একাউন্ট নম্বর',
        'enter_amount' => 'পরিমাণ লিখুন',
        'description' => 'বিবরণ',
        'send_money' => 'টাকা পাঠান',
        'confirm_deposit' => 'জমা নিশ্চিত করুন',
        'confirm_withdrawal' => 'উত্তোলন নিশ্চিত করুন',
        'save_changes' => 'সংরক্ষণ করুন',
        'update_profile' => 'প্রোফাইল আপডেট করুন',
        'change_password' => 'পাসওয়ার্ড পরিবর্তন করুন',
        
        // KYC
        'kyc_verification' => 'কেওয়াইসি ভেরিফিকেশন',
        'phone_number' => 'ফোন নম্বর',
        'verification_code' => 'ভেরিফিকেশন কোড',
        'send_code' => 'ভেরিফিকেশন কোড পাঠান',
        'verify_now' => 'এখন ভেরিফাই করুন',
        'resend_code' => 'পুনরায় কোড পাঠান',
        
        // Messages
        'welcome_back' => 'স্বাগতম',
        'login_success' => 'লগইন সফল',
        'transfer_success' => 'টাকা স্থানান্তর সফল',
        'deposit_success' => 'টাকা জমা সফল',
        'withdraw_success' => 'টাকা উত্তোলন সফল',
        'profile_updated' => 'প্রোফাইল আপডেট করা হয়েছে',
        'nominee_updated' => 'নামমাত্র আপডেট করা হয়েছে',
        'password_changed' => 'পাসওয়ার্ড পরিবর্তন করা হয়েছে',
        'password_mismatch' => 'নতুন পাসওয়ার্ড মেলেনি',
        'wrong_password' => 'বর্তমান পাসওয়ার্ড ভুল',
        'invalid_amount' => 'অনুগ্রহ করে সঠিক পরিমাণ লিখুন',
        'insufficient_balance' => 'পর্যাপ্ত ব্যালেন্স নেই',
        'account_not_found' => 'একাউন্ট পাওয়া যায়নি',
        'cannot_transfer_self' => 'নিজের একাউন্টে টাকা স্থানান্তর করা যাবে না',
        'transfer_failed' => 'টাকা স্থানান্তর ব্যর্থ হয়েছে',
        'deposit_failed' => 'টাকা জমা ব্যর্থ হয়েছে',
        'withdraw_failed' => 'টাকা উত্তোলন ব্যর্থ হয়েছে',
        
        // Back button
        'back_to_dashboard' => 'ড্যাশবোর্ডে ফিরে যান',
        
        // Card
        'card_details' => 'কার্ডের বিবরণ',
        'card_number' => 'কার্ড নম্বর',
        'expiry_date' => 'মেয়াদ শেষের তারিখ',
        'cvv' => 'সিভিভি',
        'card_holder' => 'কার্ডধারীর নাম',
        'expires' => 'মেয়াদ',
    ]
];

// Translation function
function __($key) {
    global $lang;
    $current_lang = $_SESSION['language'] ?? 'en';
    return $lang[$current_lang][$key] ?? $key;
}

// Get current language
function current_lang() {
    return $_SESSION['language'] ?? 'en';
}
?>