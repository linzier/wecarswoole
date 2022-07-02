<?php

include_once './base.php';

use voku\helper\AntiXSS;

$antiXss = new AntiXSS();
$antiXss->removeEvilAttributes(array('style'));

$t = '<li style="list-style-image: url(javascript:alert(0))">ddd</li> <>';

$s = $antiXss->xss_clean($t);

echo $s,"=======: ",html_entity_decode($s), "\n";
