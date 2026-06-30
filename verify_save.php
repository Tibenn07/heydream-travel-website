<?php
$_SERVER['HTTP_HOST'] = 'localhost';
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'super_admin';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
  'action' => 'save_destination',
  'id' => 0,
  'name' => 'PHP Test Local Save',
  'type' => 'local',
  'country' => 'Philippines',
  'city' => 'Cebu',
  'location_name' => 'Cebu',
  'description' => 'test',
  'short_description' => 'test',
  'activities_count' => 2,
  'package_price' => 199,
  'package_duration' => '3D/2N',
  'duration' => '3D/2N',
  'price' => 199,
  'currency' => '₱',
  'group_size' => '2-15 pax',
  'best_season' => 'Year Round',
  'category' => 'beach',
  'booked_count' => '0',
  'is_active' => 1,
  'display_order' => 1,
  'badge_text' => 'Test',
  'remarks' => '',
  'blocked_dates' => '',
  'promo_start' => '',
  'promo_end' => '',
  'highlight_duration' => 1,
  'blocked_months' => [],
  'itinerary' => '[]',
  'hotels' => '[]',
  'inclusions' => '',
  'exclusions' => ''
];
$_FILES = [];
require 'admin/content-manager.php';
