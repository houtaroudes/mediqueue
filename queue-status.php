<?php
// Live board feed: GET queue-status.php -> {"ok":true,"number":"A004",...}
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
// board.php renders the wait estimate, whose helpers live in functions.php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/board.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode(mq_board_status());
