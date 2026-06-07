<?php
require 'web/python_config.php';
$cmd = $pythonExe . ' "c:/Users/shafnats/Development/Tubes_Dasildat/scripts/predict.py" "[6,148,72,35,0,33.6,0.627,50]" "svm" 2>&1';
echo "COMMAND: " . $cmd . "\n";
echo "OUTPUT: " . shell_exec($cmd);
