<?php

$out = [];
$artisan = dirname(__FILE__).'/../artisan';

$result = exec("php $artisan optimize",$out);

foreach($out as $o) {
	if ( ! empty($o)) echo $o."<br>";
}


