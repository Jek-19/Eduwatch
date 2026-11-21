<?php
/* POST -> destroys the session. */
require_once __DIR__ . '/../includes/api.php';
api_start(['POST']);
logout_session();
api_out();
