<?php
/**
 * OyubiaCYF configuration template.
 *
 * SETUP: copy this file to "config.php" (same folder) and fill in your
 * cPanel MySQL database credentials. config.php is git-ignored and never
 * served to the web (see app/.htaccess).
 */
return [
    // ----- Database (from cPanel > MySQL Databases) -----
    'db' => [
        'host'    => 'localhost',
        'name'    => 'yourcpanel_oyubiacyf',
        'user'    => 'yourcpanel_oyubiacyf',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // MySQL session timezone offset. Keeps "today" on Nigerian time (WAT, UTC+1)
    // even if the cPanel server runs on UTC. Use a numeric offset (always works;
    // named zones may not be installed on shared hosting).
    'db_timezone' => '+01:00',

    // ----- App -----
    'app_name'  => 'OyubiaCYF Attendance Management System',
    'org_name'  => 'Oyubia Christian Youth Forum',
    'reg_prefix'=> 'OYCF',          // used in reg numbers: OYCF-2026-0014
    'base_url'  => '',              // e.g. '' if at domain root, '/oyubiacyf' if in a subfolder
    'timezone'  => 'Africa/Lagos',

    // Set true ONLY on the public demo site (demo.oyubiacyf.com). It shows the
    // shared demo login credentials on the sign-in page so testers can get in.
    // Leave false (or omit) on the real production site.
    'demo_mode'    => false,
    'demo_email'   => 'demo@oyubiacyf.com',  // shown on the demo login page
    'demo_password'=> 'demo1234',           // shown on the demo login page

    // 32+ random chars; used to sign sessions. Change this once on install.
    'app_key'   => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
];
