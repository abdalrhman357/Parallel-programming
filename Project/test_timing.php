<?php
$start = microtime(true);
$fp = fopen('/tmp/log.txt', 'a');
fwrite($fp, "Start " . getmypid() . " at " . $start . "\n");
fclose($fp);
