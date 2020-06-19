<?php
//les ID tweeter
define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('ACCESS_TOKEN', '');
define('ACCESS_TOKEN_SECRET', '');
//nombe de tweet à monitorer à chaque requête
define('NB_TWEET_RAMENER', 1);
//encodage des mails
define('ENCODAGE', 'Content-Type: text/plain; charset="utf-8" Content-Transfer-Encoding: 8bit\n\r');
define('PROD', true);
define('USER_t', 'XXX123');
//define('TWEETOS', '@XYZ,@MgXeYZitJ,@FeaFFFFFoFFX');
define('MAIL_WEBMASTER', 'xx.yy@pm.me');
define('FILE_log', 'log_bot.txt');
//les mentions recherchées
define('MENTIONS', 'tag ,comment,partage,identifie,mention,répond,tweet');
//listes à ignorer
define('LISTE_IGNORES', '169292256,228332513,228334346,231709899,233218973,227239190,110146060,104622842,83433025,1109024371321040896,825009467527802882,194339800,85905206,85594350');
//la recherche 
define('SRCH_t', 'rt concours');

if (! PROD)
{
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}
?>
