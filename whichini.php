<?php
echo "Loaded php.ini: " . php_ini_loaded_file() . "<br>";
echo "GD loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "<br>";
echo "extension_dir: " . ini_get('extension_dir') . "<br>";