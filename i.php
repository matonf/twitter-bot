<?php
//les ID tweeter
define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('ACCESS_TOKEN', '');
define('ACCESS_TOKEN_SECRET', '');
//nombe de tweet à monitorer à chaque requête
define('NB_TWEET_RAMENER', 1);
define('PROD', true);
define('USER_t', 'XXX123');
define('TWEETOS', '@XYZ,@MgXeYZitJ,@FeaFFFFFoFFX');
define('MAIL_WEBMASTER', 'xx.yy@pm.me');
define('FILE_log', 'log_bot.txt');
$mentions_r = array('tag ', 'comment', 'partage', 'identifie', 'mention', 'répond');
//la recherche 
define('SRCH_t', 'rt AND (concours OR gagner OR participer OR remporter)');
if (! PROD)
{
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}
?>
