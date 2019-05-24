<?php
//fichier du dernier id lu
define('FILE', 'tweet_id.txt');
//les ID tweeter
define('CONSUMER_KEY', '');
define('CONSUMER_SECRET', '');
define('ACCESS_TOKEN', '');
define('ACCESS_TOKEN_SECRET', '');
//nombre de follower à ramener d'un post
define('AMIS_NB_FOLLOWER', 1);
//nombe de tweet à monitorer à chaque requête
define('NB_TWEET_RAMENER', 1);
define('PROD', true);
define('USER_t', 'XXX123');
define('NB_tweets_recup', 200);
define('PURGE_tweets_recup', NB_tweets_recup*0.9);
if (! PROD)
{
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
}
?>
