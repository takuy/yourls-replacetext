<?php
/*
Plugin Name: replacetext
Plugin URI: http://yourls.org/
Description: replace URL or tokens based on regex pattern matching or script
Version: 1.0
Author: takuy
Author URI: https://github.com/takuy
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

yourls_add_filter( 'get_shorturl_charset', 'takuy_replacetext_to_charset' );
yourls_add_filter( 'redirect_location', 'takuy_replacetext_replace_params', 1 );
yourls_add_action( 'redirect_keyword_not_found', 'takuy_replacetext_replace_path', 1);
yourls_add_filter( 'shunt_add_new_link', 'takuy_replacetext_pre_pre_add_new_link', 1);

function takuy_replacetext_to_charset( $in ) {
	$added = "";
	
	$toAdd = ["/", "[", "]"];

	foreach ($toAdd as $c) {
		if(strpos($in, $c)) continue;

		$added = $added.$c;
		
	}
	
	return $in.$added;
}

function takuy_replacetext_replace_params ( $url ) {
	parse_str($_SERVER['QUERY_STRING'], $query);
	$to_replace = preg_match_all('/\[\[.+?\]\]/', $url, $matches);
	if(!$to_replace) return $url; 
	
	foreach($matches[0] as &$match) {
		$match = trim($match, "[]");
	}
	
	$replaced = 0;

	foreach($query as $string => $qs) {
		if(!in_array($string, $matches[0])) {
			continue;
		}
		$url = str_replace("[[$string]]", $qs, $url);
		unset($query[$string]);

		$replaced++;
	}
	$_SERVER['QUERY_STRING'] = http_build_query($query, "", "&");
	if($to_replace > 0 && ($replaced != $to_replace)) {
		yourls_redirect( YOURLS_SITE, 302 ); // no 404 to tell browser this might change, and also to not pollute logs
		exit();
	}
	return $url;
	
}

function takuy_replacetext_replace_path($keyword) {
	
	$split = explode("/", $keyword[0]);
	
	/* Simple text replacement */
	if(count($split) > 1) {
		$key = $split[0];
		$rep = $split[1];
		$url = yourls_get_keyword_info($key, "url");
		
				
		if(strpos($url, "[[$key]]")) {
			$newURL = str_replace("[[$key]]", $rep, $url);
			echo "redirecting $key to $newURL";
			
			yourls_redirect_shorturl($newURL, $key);
			exit();
		}
	}

	else {
 		$table_url = YOURLS_DB_TABLE_URL;
		
		/* Get all of the Regex-like patterns from the DB */
		$results = yourls_get_db()->fetchAll( "SELECT keyword,url FROM `$table_url` WHERE keyword LIKE 'regex/%' OR keyword LIKE '$%/%'");

		foreach ($results as $res) {
			/* Matches to the Regex pattern will be stored here */
			$matches = [];
			
			/* Parse the regex patterns from the table */
			$keywordSplit = explode("/",$res["keyword"]);
			$regexKey = $keywordSplit[1];
			$regexPattern = "/". $regexKey . "/";
			
			/* Execute the regex pattern using the $keyword */
			$match = preg_match($regexPattern,  $keyword[0], $matches);
			$matchCount = count($matches);
			
			if($matchCount > 0) {
				
				/* if the keyword begins with a $, this is a call to a script */
				if(strpos($keywordSplit[0], '$') === 0) {
					
					$scriptName = substr($keywordSplit[0], 1); 
					
					/*sanitize it*/
					$scriptName = preg_replace('/[^a-zA-Z0-9_]/', '', $scriptName);
					
					$scriptPath = dirname(__FILE__) ."/scripts/$scriptName.php";
					
					if(!file_exists($scriptPath)) {
						die("Something horribly wrong happened: $scriptPath");
					}
					require_once($scriptPath);
					
					$newURL = yourls_apply_filter("takuy_scriptreplace_$scriptName", $keyword[0], $res["keyword"], $res["url"], $matches);
					
				
				/* otherwise replace the URL's numbered tokens based on regex match*/
				} else {
					$newURL = $res["url"];
					
					/* If there are multiple matched groups - we can replace all of them */
					for($i = 0; $i < $matchCount; $i++) {
						/* Check for matches based on order 
						[[1]] will be replaced with the first matched group, [[2]] with the second, etc */
						if(strpos($newURL, "[[$i]]")) {
							$newURL = str_replace("[[$i]]", $matches[$i], $newURL);
						}
					}
				}
				echo "redirecting $$keyword[0] to $newURL";
				
				/* For tracking purposes, we use the matched regex keyword */
				yourls_redirect_shorturl($newURL, $res["keyword"]);
				exit(); 
			}
		}
	}
	
}

function takuy_replacetext_pre_pre_add_new_link($return, $url, $keyword, $title) {
	if(strpos($keyword, "regex/") === 0 || strpos($keyword, "\$") === 0) {
		

		yourls_add_filter( 'sanitize_string', function($sanitized, $original) { 
			return $original;
		} );

		return false;
	} 
	return false;
}

