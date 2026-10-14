<?php
header('Content-Type: text/plain');
echo "GD loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
echo "GD info:\n";
var_dump(gd_info());