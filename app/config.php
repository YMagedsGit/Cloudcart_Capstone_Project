<?php
define('DB_HOST', getenv('CC_DB_HOST'));
define('DB_USER', getenv('CC_DB_USER'));
define('DB_PASS', getenv('CC_DB_PASS'));
define('DB_NAME', getenv('CC_DB_NAME'));

define('COGNITO_REGION',    getenv('CC_COGNITO_REGION'));
define('COGNITO_USER_POOL', getenv('CC_COGNITO_USER_POOL'));
define('COGNITO_CLIENT_ID', getenv('CC_COGNITO_CLIENT_ID'));

function db(): mysqli {
    static $c = null;
    if ($c === null) {
        $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($c->connect_error) { http_response_code(500); die('DB connection failed'); }
    }
    return $c;
}
