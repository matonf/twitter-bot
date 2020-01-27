<?php
//charges les identifiants et constantes
require_once("i.php");
//charge la librairie tweeter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;
//kk paramètres
define('NB_tweets_recup', 200);
?>

<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Derniers tweets</title>
<head>
<meta name="viewport" content="width=device-width"/>
<body bgcolor="#E9E9E9">
<?php
//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
//récupère les derniers tweets
$favs = $connection->get('statuses/user_timeline', [ 'screen_name' => USER_t, 'count' => NB_tweets_recup, 'trim_user' => false]);
echo "Cette page affiche les " . NB_tweets_recup . " derniers tweets :<br><ul>";
//compteur de tweet
$i = 0;
$rt = 0;
foreach ($favs as $fav) 
{
	echo '<li>Le ' . date("d/m/y à H:i", strtotime($fav->created_at)) . ': <a target=_blank href="https://twitter.com/' .  $fav->user->screen_name . '/status/' . $fav->id_str . '">' . substr($fav->text, 0 ,113) . "</a></li>";
	//compte les RT
	$i++;
	if (substr($fav->text, 0, 2) == "RT") $rt++;
	
}
echo '</ul>';
echo "$i tweet dont $rt RT #concours";
?> 
</body>
</html>
