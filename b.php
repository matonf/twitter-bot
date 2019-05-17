<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

function before()
{
	$s = [ "Je mentionne", "ça va ? ", "salut", "coucou", "hey", "hello", "bonjour", "Respect à", "Et voilà" ];
	return $s[array_rand($s)];
}

function after()
{
  $smilies = array('GG', 'a+'  ,':-|' ,':-o'  ,':-O' ,':o' ,':O' ,';)'  ,';-)' ,':p'  ,':-p' ,':P'  ,':-P' ,':D' ,':-D' ,'8)' ,'8-)' ,':)'  ,':-)', 'Merci pour ce magnifique cadeau !', 'Merci', ' merci beaucoup !!' );
  return $smilies[array_rand($smilies)];
}
  
//debug 
function elog($m)
{
	if (! PROD) echo "<font clor=gray>$m</font><br>\n";
}

//charges les identifiants tweeter
require_once("i.php");

//charge la librairie tweeter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
	
//lit le dernier id
if (! $last_id = file_get_contents(FILE)) $last_id = 0;
elog("last : $last_id");
$nb_concours = 0;
//cherche #concours
$results = $connection->get('search/tweets', [ 'q' => '#concours', 'lang' => 'fr', 'result_type' => 'popular', 'count' => NB_TWEET_RAMENER, 'include_entities' => false, 'since_id' => $last_id]);

foreach ($results->statuses as $tweet) 
{
	$nb_concours++;
	$texte = $tweet->text;
	elog("Tweet: " . $texte);
	
	//1-favorise le tweet
	$connection->post('favorites/create', [ 'id' => $tweet->id_str ]);
	elog('favori: ' . $tweet->id_str);
	
	//2-rt le tweet
	$connection->post('statuses/retweet', [ 'id' => $tweet->id_str]);
	elog('retweet: ' . $tweet->id_str);
	
	//3-poste un commentaire avec des mentions @XX @YY @ZZ
	$users = $connection->get('followers/list', [ 'user_id' => $tweet->user->id_str, 'count' => AMIS_NB_FOLLOWER ]);
	$commentaire = null;
	foreach ($users->users as $user) $commentaire .= '@' . $user->screen_name . ' ';
	$commentaire = '@' . $tweet->user->screen_name . ' ' . before() . ' '  . trim($commentaire) . ' ' . after();
	$statues = $connection->post('statuses/update', ['status' => $commentaire, 'in_reply_to_status_id' => $tweet->id_str ]);
	elog('tweet: '  .  $commentaire . ' + in_reply_to_status_id: '. $tweet->id_str);
	
	//4-follow le compte
	$post = $connection->post('friendships/create', [ 'screen_name' => $tweet->user->screen_name, 'follow' => 'false']);
	elog('follow: ' . $tweet->user->screen_name);
	
	//5-follow les comptes associés dans le tweet
	$compter = false;
	$nom = null;
	for ($j=0; $j<strlen($texte); $j++)
	{
		$lettre = substr($texte, $j,1);
		//détecte un nom d'utilisateur
		if ($lettre == '@')
		{
				$nom = null;
				$compter = true;
		}				
		
		//détecte la fin du nom
		if ($lettre ==  '.' ||  $lettre ==  ',' || $lettre ==  ' ' || ($j == strlen($texte)-1))
		{ 
				if ($compter) elog('follow sup: ' . $nom);
				if ($compter) $connection->post('friendships/create', [ 'screen_name' => $nom, 'follow' => 'false']);
				$compter = false;
		}
		
		if ($compter && $lettre != '@') $nom .= $lettre;
		
	}

	elog(" ");
}
if ($nb_concours) 
{
	//stocke le dernier id lu
	file_put_contents(FILE, $tweet->id_str);
	elog("last : " . $tweet->id_str);
}
?>
