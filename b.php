<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

//debug 
function elog($m)
{
	//pause pour passer sous les radars
	sleep(rand(2,5));
	//sortie texte
	if (PROD === false) echo "<font color=gray>$m</font><br>\n";
	//sortie fichier
	else @file_put_contents(FILE_log, strip_tags($m) . PHP_EOL, FILE_APPEND);
}

//retourne un smiley sympa
function getsmil()
{
	// source : https://freek.dev/376-using-emoji-in-phphttps://freek.dev/376-using-emoji-in-php
	$smilies = ["\u{1F603}", "\u{1F340}" , "\u{1F600}", "\u{1F4AA}", "\u{1F44D}", "\u{1F64C}", "\u{1F601}"];
	return $smilies[rand(0,count($smilies)-1)];
}

//vérifie la bonne exécution des requêtes
function testeRequete($c, $l)
{
	$encodage = "Content-Type: text/plain; charset=\"utf-8\" Content-Transfer-Encoding: 8bit\n\r";
	if ($c != 200) 
	{
		mail(MAIL_WEBMASTER, "Rapport du bot twitter : erreur $c", "Une erreur a été rencontrée: erreur $c à la ligne $l\r\nhttps://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],$encodage);	
		die("😢");
	}
}

//charges les identifiants tweeter
require_once("i.php");

//purge la log le premier de chaque mois
if (date("jG") == "10") @unlink(FILE_log);

//charge la librairie twitter
require 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//se connecte à tweeter
$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

//quelques variables initialisées
$hashtags_inutiles = array("#RT", "#FOLLOW", "#FAV", "#CONCOURS", "#PAYPAL", "#GIVEAWAY");
$nb_concours = 0;
//cherche #concours etc
$results = $connection->get('search/tweets', [ 'tweet_mode' => 'extended', 'q' => SRCH_t, 'lang' => 'fr', 'result_type' => 'mixed', 'count' => 50, 'include_entities' => false ] ); //, 'since_id' => $last_id ] );
testeRequete($connection->getLastHttpCode(), __LINE__ );

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
	
	
	//1-FAV le tweet
	$retour_post = $connection->post('favorites/create', [ 'id' => $tweet->id_str ]);
		
	//détection d'erreur force à enchaîner la boucle
	if (count($retour_post->errors)) 
	{
		//pause forcée
		sleep (1);
		continue;
	}
	testeRequete($connection->getLastHttpCode(), __LINE__ );

	$nb_concours++;
	//écrit l'id du tweet 
	elog('FAV: <a target=_blank href=https://twitter.com/' . $tweet->user->screen_name . '/status/' . $tweet->id_str . '>' . $tweet->id_str . '</a>');
		
	//2-RT le tweet
	$connection->post('statuses/retweet', [ 'id' => $tweet->id_str]);
	testeRequete($connection->getLastHttpCode(), __LINE__ );
	elog('RT: ' . $tweet->id_str);
		
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
			testeRequete($connection->getLastHttpCode(), __LINE__ );
			foreach ($users->users as $user) $noms .= '@' . $user->screen_name . ' ';
		}
		elseif (defined('TWEETOS')) 
		{
			$tab_noms_tweetos = explode(',', TWEETOS);
			$noms =  $tab_noms_tweetos[rand(0, count($tab_noms_tweetos)-1)] . ' ';
		}
	}
	if (! is_null($noms)) $nom_commentaire = " j'invite à participer " . $noms;
	else $nom_commentaire = null;
		
	//4-FOLLOW le compte
	$connection->post('friendships/create', [ 'screen_name' => $tweet->user->screen_name, 'follow' => 'true']);
	elog('FOLLOW: ' . $tweet->user->screen_name);
	
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
		if ($lettre ==  "\n" || $lettre ==  '!' || $lettre ==  ':' ||  $lettre ==  '.' ||  $lettre ==  ',' || $lettre ==  ' ' || ($j == strlen($texte)-1))
		{
				if ($compter_nom) 
				{
					$connection->post('friendships/create', [ 'screen_name' => $nom, 'follow' => 'true']);
					elog('FOLLOW: ' . $nom);
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
			//purge quelques hastags inutiles
			$hashtag = trim(str_ireplace($hashtags_inutiles, "", $hashtag));
			//format du message : @nom_du_posteur_original @noms_des_amis #hastags
			if ($hashtag == "") $hashtag = getsmil();
			$messg = '@' . $tweet->user->screen_name . ' ' . trim($nom_commentaire) . ' ' . trim($hashtag);
			$connection->post('statuses/update', ['status' => trim($messg), 'in_reply_to_status_id' => $tweet->id_str, 'auto_populate_reply_metadata' => false ]);
			testeRequete($connection->getLastHttpCode(), __LINE__ );
			elog('TWEET: ' . $messg);
		}
	}
	
	//on a posté autant que désiré, on sort
	if (NB_TWEET_RAMENER == $nb_concours) break;
}

//on a pas participé à un concours
if ($nb_concours == 0) 
{
	//cherche tout sauf #concours, on va spammer du contenu populaire
	$results_spam = $connection->get('search/tweets', [ 'q' => 'info OR média OR actu', 'lang' => 'fr', 'result_type' => 'popular', 'count' => NB_TWEET_RAMENER, 'include_entities' => false]);
	testeRequete($connection->getLastHttpCode(), __LINE__ );
	foreach ($results_spam->statuses as $tweet_spam) 
	{
		//RT le tweet
		$connection->post('statuses/retweet', [ 'id' => $tweet_spam->id_str]);
		testeRequete($connection->getLastHttpCode(), __LINE__ );
		elog('retweet spam: <a target=_blank href=https://twitter.com/' . $tweet_spam->user->screen_name . '/status/' . $tweet_spam->id_str . '>' . $tweet_spam->id_str . '</a>');
	}	
}

//pour le fun
echo getsmil();
?>
