<?php

function sg_catchall($code, $message) {
        require __DIR__ . DIRECTORY_SEPARATOR . 'sg_error_page.php';
        exit;
}

?>
