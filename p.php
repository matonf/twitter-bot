<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

//charges les identifiants tweeter
require_once("i.php");

//charge la librairie twitter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
	
echo 'date: ' . date('d/m/Y à H:i') . '<br>';

//cherche qui je follow
$results_moi = $connection->get('friends/ids', [ 'user_id' => USER_t ]);
$cpt = 0;
$cpt_del = 0;
foreach ($results_moi->ids as $follow) 
{
	//collecte des données sur chaque follow
	$results_ami = $connection->get('users/show', [ 'user_id' => $follow ]);
	if ($connection->getLastHttpCode() != 200) die($connection->getLastHttpCode());
	//supprime l'ami si pas éminent
	if ($results_ami->followers_count < 500) 
	{
		if (PROD) $connection->post('friendships/destroy', [ 'id' => $follow ]);
		if ($connection->getLastHttpCode() != 200) die($connection->getLastHttpCode());
		echo 'delete: ' . $results_ami->screen_name . ' (' . $results_ami->followers_count . ' amis)<br>';
		$cpt_del++;
		//if ($cpt_del > 10) break;
	}
	$cpt++;
}
echo 'Sur ' . $cpt . ' amis, ' . $cpt_del . ' supprimés !';
?>
