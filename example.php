<?php declare(strict_types = 1);
require('src/Plist.php');
require('src/RIPlist.php');

$plist = (new Plist($argv[1]))();
echo RIPlist::rip($plist, $argv[2] ?? '.', $argv[3] ?? '/tmp/ripped') ? 'success' : 'error';
echo PHP_EOL;
