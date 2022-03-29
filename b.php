<?php
// DOC
// https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

//debug 
function elog($m, $t=1)
{
	//pause pour passer sous les radars
	sleep($t);
	$m = date('d/m/Y à H:i ') . $m;
	//sortie texte
	if (PROD === false) echo "<font color=gray>$m</font><br>\n";
	//sortie fichier
	if (LOG) @file_put_contents(FILE_log, strip_tags($m) . PHP_EOL, FILE_APPEND);
}

//retourne un smiley sympa
function getsmil()
{
	// source : https://freek.dev/376-using-emoji-in-php
	$smilies = ["\u{1F603}", "\u{1F340}" , "\u{1F600}", "\u{1F4AA}", "\u{1F44D}", "\u{1F64C}", "\u{1F601}"];
	return $smilies[rand(0,count($smilies)-1)];
}

//vérifie la bonne exécution des requêtes
function testeRequete($c, $l)
{
	if ($c != 200) 
	{
		elog("Une erreur a été rencontrée: erreur $c à la ligne $l 😢");
		die();
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

//cherche #concours 
//Paris : 'geocode' => '2.352222,48.856614,900 km' ,
$results = $connection->get('search/tweets', [ 'tweet_mode' => 'extended', 'q' => SRCH_t . ' filter:safe', 'lang' => 'fr', 'result_type' => 'mixed', 'count' => 70, 'include_entities' => false ] ); 
testeRequete($connection->getLastHttpCode(), __LINE__ );

//quelques variables initialisées
$hashtags_inutiles = array("#RT", "#FOLLOW", "#FAV", "#CONCOURS", "#PAYPAL", "#GIVEAWAY");
$nb_concours = 0;
//mentions recherchées dans un tweet
if (defined('MENTIONS')) $mentions_r = explode(',', MENTIONS);
else $mentions_r = array();
//liste des tweetos à mentionner
if (defined('TWEETOS')) $tab_noms_tweetos = explode(',', TWEETOS);

//listes de tweetos à ignorer
$tab_listes_ignores = array();

if (defined('LISTE_IGNORES')) 
{
	$tab_listes = explode(',', LISTE_IGNORES);
	foreach ($tab_listes as $liste)
	{
		//récupère les follow de la liste courante
		$ignores = $connection->get('lists/members', [ 'list_id' => $liste, 'count' => 5000, 'include_entities' => false ]);
		//traite les suivis de la liste
		foreach ($ignores->users as $ignore)
		{
			//les ajoute au tableau général
			array_push($tab_listes_ignores, $ignore->screen_name);
			//echo "$ignore->screen_name,";
		}
	}
}


//parcours des tweet à faire
foreach ($results->statuses as $tweet) 
{	
	//détecte un retweet
	if (isset($tweet->retweeted_status)) 
	{
		//réaffecte le tweet original
		$tweet = $tweet->retweeted_status;
	}
	
	//regarde si le tweetos est ignoré depuis les listes, si oui on passe au tweet suivant
	if (in_array($tweet->user->screen_name, $tab_listes_ignores)) 
	{
		elog($tweet->user->screen_name . " est ignoré", 0);
		continue;
	}

    //ignore le tweet si le compte est pas assez populaire
  	if ($tweet->user->followers_count < 9000) 
	{
		elog($tweet->user->screen_name . " n'a pas assez d'amis", 0);
        continue;
    }
	
	//récupère le texte pour plusieurs traitements ultérieurs
	$texte = $tweet->full_text;
	
	//1-FAV le tweet
	$retour_post = $connection->post('favorites/create', [ 'id' => $tweet->id_str ]);
		
	//détection d'erreur force à enchaîner la boucle
	if (count($retour_post->errors)) 
	{
		elog('Déjà participé au concours: ' . $tweet->id_str, 0);
		continue;
	}
	testeRequete($connection->getLastHttpCode(), __LINE__ );

	$nb_concours++;
	//écrit l'id du tweet 
	elog('FAV: ' . $tweet->id_str);
		
	//2-RT le tweet
	$connection->post('statuses/retweet', [ 'id' => $tweet->id_str]);
	testeRequete($connection->getLastHttpCode(), __LINE__ );
	elog('RT: ' . $tweet->id_str);
		
	//3-prépare un commentaire avec des mentions @XX @YY @ZZ si la mention est nécessaire uniquement
	$mentionner = false;
	
	if (defined('MENTIONS')) 
	{
		foreach ($mentions_r as $val_r) 
		{
			if (stripos($texte, $val_r)) 
			{
				$mentionner = true;
				break;
			}
		}
	}
	
	$nom = null;	
	if ($mentionner)
	{
		//amis définis en dur dans la configuration
		if (defined('TWEETOS')) 
		{
			$nom =  $tab_noms_tweetos[rand(0, count($tab_noms_tweetos)-1)];
		}
		else
		{
			//amis qui me suivent, liste dynamique
			$results_followers = $connection->get('followers/ids', [ 'user_id' => USER_t ]);
			testeRequete($connection->getLastHttpCode(), __LINE__ );
			$followers = $results_followers->ids;
			//collecte des données sur le followertiré au hasard	
			$follower = $connection->get('users/show', [ 'user_id' => $followers[rand(0,count($followers)-1)], 'include_entities' => false ]);
			testeRequete($connection->getLastHttpCode(), __LINE__ );
			$nom = '@' . $follower->screen_name;
		}
	}
	if (! is_null($nom)) $nom_commentaire = " j'invite à participer " . $nom;
	else $nom_commentaire = null;
		
	//4-FOLLOW le compte automatiquement
	$connection->post('friendships/create', [ 'screen_name' => $tweet->user->screen_name]);
	elog('FOLLOW: ' . $tweet->user->screen_name);
	
	//5-FOLLOW les comptes associés dans le tweet 
	$offset = 0;
	//recherche les @
	while ($offset !== FALSE)
	{
		$offset = stripos($texte,'@',$offset);
		if ($offset === FALSE) break;
		$offset++;
		//filtre @ (a-z0-9_)
		$noma = trim(strtolower(substr($texte, $offset, strspn(strtolower($texte),"abcdefghijklmnopqrstuvwxyz1234567890_",$offset))));
		$connection->post('friendships/create', [ 'screen_name' => $noma]);
		elog('FOLLOW: ' . $noma);
	}

	//3 bis-poste un commentaire avec des mentions @XX @YY @ZZ
	if ($mentionner)
	{
		if (! is_null($nom_commentaire))
		{
			$offset = 0;
			//recherche les #
			while ($offset !== FALSE)
			{
				$offset = stripos($texte,'#',$offset);
				if ($offset === FALSE) break;
				$offset++;
				//filtre les hashtag (a-z0-9_)
				$hash = trim(strtolower(substr($texte, $offset, strspn(strtolower($texte),"abcdefghijklmnopqrstuvwxyz1234567890_",$offset))));
				$hashtag .= ' #' . $hash;
			}
			//purge quelques hastags inutiles
			$hashtag = trim(str_ireplace($hashtags_inutiles, '', $hashtag));
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

//on a pas participé à un seul concours
if ($nb_concours == 0) 
{
	//on va spammer du contenu populaire
	$results_spam = $connection->get('search/tweets', [ 'q' => 'Nintendo', 'lang' => 'fr', 'result_type' => 'popular', 'count' => NB_TWEET_RAMENER, 'include_entities' => false]);
	testeRequete($connection->getLastHttpCode(), __LINE__ );
	foreach ($results_spam->statuses as $tweet_spam) 
	{
		//RT le tweet
		$connection->post('statuses/retweet', [ 'id' => $tweet_spam->id_str]);
		elog('RT SPAM: ' . $tweet_spam->id_str);
		$nb_concours++;
	}	
}

//pour le fun, affiche des smileys
echo str_repeat(getsmil(), $nb_concours);
?>
