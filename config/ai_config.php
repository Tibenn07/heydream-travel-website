<?php
// config/ai_config.php
// ============================================================
// Gemini AI Configuration — shared across the project
// Get your FREE API key at: https://aistudio.google.com/apikey
// ============================================================

define('GEMINI_API_KEY', 'AIzaSyBsAGNKK_6IzVXIMUMRcmCNGg0Tgvwenz4');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . GEMINI_API_KEY);
define('GEMINI_ENABLED', strlen(GEMINI_API_KEY) > 20);
