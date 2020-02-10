<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

//debug 
function elog($m)
{
	//pause pour passer sous les radars
	sleep(rand(3,9));
	//sortie texte
	if (PROD === false) echo "<font color=gray>$m</font><br>\n";
	//sortie fichier
	else @file_put_contents(FILE_log, strip_tags($m) . PHP_EOL, FILE_APPEND);
}

//charges les identifiants tweeter
require_once("i.php");

//charge la librairie twitter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
	
//lit le dernier id
$last_id = @file_get_contents(FILE_id);
elog('date: ' . date('d/m/Y à H:i'));
$nb_concours = 0;
//cherche #concours etc
$results = $connection->get('search/tweets', [ 'tweet_mode' => 'extended', 'q' => SRCH_t, 'lang' => 'fr', 'result_type' => 'mixed', 'count' => 3*NB_TWEET_RAMENER, 'include_entities' => false, 'since_id' => $last_id ] );

/*
//récupère les derniers tweets
$favs = $connection->get('statuses/user_timeline', [ 'screen_name' => USER_t, 'count' => 50, 'trim_user' => false]);
$tab_fav = array();
foreach ($favs as $fav) 
{
	array_push($tab_fav, $fav->id_str);
}
*/

//parcours des tweet à faire
foreach ($results->statuses as $tweet) 
{	
	//détecte un retweet
	if (isset($tweet->retweeted_status)) 
	{
		//réaffecte le tweet original
		$tweet = $tweet->retweeted_status;
	}
	
	//récupère le texte pour plusieurs traitements ultérieurs
	$texte = $tweet->full_text;
	
	/*
	if (in_array($tweet->id_str, $tab_fav))
	{
		elog("pré-détection, retweet déjà fait: " . $tweet->id_str);
		continue;
	}
	*/
	
	//écrit le tweet sans les retours chariots
	elog('tweet: ' . str_replace(PHP_EOL, ' ', $texte));
	
	//1-FAV le tweet
	if (PROD) $retour_post = $connection->post('favorites/create', [ 'id' => $tweet->id_str ]);
	//détection d'erreur force à enchaîner la boucle
	if (count($retour_post->errors)) 
	{
		elog("Erreur, retour API: FAV déjà fait: " . $tweet->id_str);
		continue;
	}

	$nb_concours++;
	elog('favori: <a target=_blank href=https://twitter.com/' . $tweet->user->screen_name . '/status/' . $tweet->id_str . '>' . $tweet->id_str . '</a>');
		
	//2-RT le tweet
	if (PROD) $connection->post('statuses/retweet', [ 'id' => $tweet->id_str]);
	elog('retweet: ' . $tweet->id_str);
		
	//3-prépare un commentaire avec des mentions @XX @YY @ZZ si la mention est nécessaire uniquement
	$mentionner = false;
	foreach ($mentions_r as $val_r) 
	{
		if (stripos($texte, $val_r)) 
		{
			$mentionner = true;
			break;
		}
	}

	$noms = null;	
	if ($mentionner)
	{
		if (AMIS_NB_FOLLOWER)
		{
			$users = $connection->get('followers/list', [ 'user_id' => $tweet->user->id_str, 'count' => AMIS_NB_FOLLOWER ]);
			foreach ($users->users as $user) $noms .= '@' . $user->screen_name . ' ';
		}
		elseif (defined('TWEETOS')) $noms = TWEETOS . ' ';
	}
	if (! is_null($noms)) $nom_commentaire = " j'invite à participer " . $noms;
	else $nom_commentaire = null;
		
	//4-FOLLOW le compte
	if (PROD) $connection->post('friendships/create', [ 'screen_name' => $tweet->user->screen_name, 'follow' => 'true']);
	elog('follow: ' . $tweet->user->screen_name);
	
	//5-FOLLOW les comptes associés dans le tweet
	//raz initiales
	$compter_nom = false;
	$compter_hashtag = false;
	$nom = null;
	$hashtag = null;
	//parcours des tweets retenus
	for ($j=0; $j<strlen($texte); $j++)
	{
		$lettre = substr($texte, $j,1);
		//détecte un nom d'utilisateur
		if ($lettre == '@')
		{
				$nom = null;
				$compter_nom = true;
		}
		if ($lettre == '#')
		{
				$hashtag .= ' ';
				$compter_hashtag = true;
		}				
		
		//détecte la fin du nom
		if ($lettre ==  '!' || $lettre ==  ':' ||  $lettre ==  '.' ||  $lettre ==  ',' || $lettre ==  ' ' || ($j == strlen($texte)-1))
		{
				if ($compter_nom) 
				{
					elog('follow sup: ' . $nom);
					if (PROD) $connection->post('friendships/create', [ 'screen_name' => $nom, 'follow' => 'true']);
				}
				//raz
				$compter_nom = false;
				$compter_hashtag = false;
		}
		
		if ($compter_nom && $lettre != '@') $nom .= $lettre;
		if ($compter_hashtag) $hashtag .= $lettre;
	}
	
	//3 bis-poste un commentaire avec des mentions @XX @YY @ZZ
	if ($mentionner)
	{
		if (! is_null($nom_commentaire))
		{
			//format du message : @nom_du_posteur_original @noms_des_amis #hastags
			$messg = '@' . $tweet->user->screen_name . ' ' . trim($nom_commentaire) . ' ' . trim($hashtag);
			if (PROD) $connection->post('statuses/update', ['status' => trim($messg), 'in_reply_to_status_id' => $tweet->id_str, 'auto_populate_reply_metadata' => false ]);
			elog('comment: ' . $messg);
		}
	}
	
	//on a posté autant que désiré, on sort
	if (NB_TWEET_RAMENER == $nb_concours) break;
}

//on a participé à un concours ou plusieurs
if ($nb_concours) 
{
	//stocke le dernier id lu
	if (PROD) file_put_contents(FILE_id, $tweet->id_str);
}
else //on a rien fait
{
	//cherche tout sauf #concours, on va spammer du contenu populaire
	$results_spam = $connection->get('search/tweets', [ 'q' => 'info OR média OR actu', 'lang' => 'fr', 'result_type' => 'popular', 'count' => NB_TWEET_RAMENER, 'include_entities' => false]);
	elog('cherche un retweet populaire pour spam');
	foreach ($results_spam->statuses as $tweet_spam) 
	{
		//RT le tweet
		if (PROD) $connection->post('statuses/retweet', [ 'id' => $tweet_spam->id_str]);
		elog('retweet spam: <a target=_blank href=https://twitter.com/' . $tweet_spam->user->screen_name . '/status/' . $tweet_spam->id_str . '>' . $tweet_spam->id_str . '</a>');
	}	
}
?>
