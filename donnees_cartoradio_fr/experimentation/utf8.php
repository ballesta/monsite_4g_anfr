<?php
/**
 * Created by PhpStorm.
 * User: bernard
 * Date: 16/10/18
 * Time: 18:49
 */
//echo '<!doctype html><html lang="fr"><head><meta charset="UTF-8"></head>';

// Antenne autoris?e mais non activ?e
//Antenne autoris?e mais non activ?e

//header('Content-Type: text/html;charset=ISO-8859-1');
header('Content-Type: text/html;charset=UTF-8');
//header('Content-Type: text/html;charset=windows-1252');

$s = 'Numéro du support ééé ààà çççç ÉÉÉÉÉ ÈÈÈÈÈ ÀÀÀÀÀ Â';
echo $s,'<br>';

$u = utf8_encode($s);
echo $u,'<br>';

$t = str_split($s);
foreach ($t as $c)
    echo $c, ' ', ord($c), '<br>';

