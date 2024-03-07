<?php
/*
Plugin Name: replacetext
Plugin URI: https://github.com/takuy/yourls-replacetext
Description: replace URL or tokens based on regex pattern matching or script
Version: 1.3.1
Author: takuy
Author URI: https://github.com/takuy
*/

// No direct call
if (!defined('YOURLS_ABSPATH')) die();

yourls_add_filter('get_shorturl_charset', 'takuy_replacetext_to_charset');
yourls_add_filter('redirect_location', 'takuy_replacetext_replace_params', 1);
yourls_add_action('redirect_keyword_not_found', 'takuy_replacetext_replace_path', 1);
yourls_add_action('pre_add_new_link', 'takuy_replacetext_pre_pre_add_new_link', 1);
yourls_add_filter('shunt_edit_link', 'takuy_replacetext_pre_pre_edit_link');
yourls_add_filter('is_shorturl', 'takuy_replacetext_is_shorturl', 1);
yourls_add_action('admin_page_before_table', 'takuy_replacetext_admin_page_before_table');
yourls_add_filter('shunt_get_keyword_info', 'takuy_replacetext_get_keywordinfo');
yourls_add_action('plugins_loaded', 'takuy_replacetext_ajax_plugins_loaded');
yourls_add_filter('keyword_is_taken', 'takuy_replacetext_stats_keyword_taken');

function takuy_replacetext_to_charset($in) {
    $added = "";
    $toAdd = ["/", "[", "]"];

    foreach ($toAdd as $c) {
        if (strpos($in, $c)) continue;
        $added = $added . $c;
    }

    return $in . $added;
}

function takuy_replacetext_replace_params($url) {
    parse_str($_SERVER['QUERY_STRING'], $query);
    $to_replace = preg_match_all('/\[\[.+?\]\]/', $url, $matches);
    if (!$to_replace) return $url;

    foreach ($matches[0] as &$match) {
        $match = trim($match, "[]");
    }

    $replaced = 0;

    foreach ($query as $string => $qs) {
        if (!in_array($string, $matches[0])) {
            continue;
        }
        $url = str_replace("[[$string]]", $qs, $url);
        unset($query[$string]);

        $replaced++;
    }
    $_SERVER['QUERY_STRING'] = http_build_query($query, "", "&");
    if ($to_replace > 0 && ($replaced != $to_replace)) {
        yourls_redirect(YOURLS_SITE, 302); // no 404 to tell browser this might change, and also to not pollute logs
        exit();
    }
    return $url;
}

function takuy_replacetext_is_shorturl($isshort, $shorturl) {
    $data = takuy_replacetext_complex_text($shorturl);
    return is_array($data);
}

function takuy_replacetext_simple_text($keyword) {
    $key = $keyword[0];
    $rep = $keyword[1];
    $url = yourls_get_keyword_info($key, "url");

    if (strpos($url, "[[$key]]")) {
        $newURL = str_replace("[[$key]]", $rep, $url);
        return array($newURL, $key);
    }
}

function takuy_replacetext_complex_text($keyword) {
    $table_url = YOURLS_DB_TABLE_URL;

    /* Get all of the Regex-like patterns from the DB */
    $results = yourls_get_db()->fetchAll("SELECT keyword,url FROM `$table_url` WHERE keyword LIKE 'regex/%' OR keyword LIKE '$%/%'");

    foreach ($results as $res) {
        /* Matches to the Regex pattern will be stored here */
        $matches = [];

        /* Parse the regex patterns from the table */
        $keywordSplit = explode("/", $res["keyword"]);
        $regexKey = $keywordSplit[1];
        $regexPattern = "/" . $regexKey . "/";

        /* Execute the regex pattern using the $keyword */
        $match = preg_match($regexPattern, $keyword, $matches);
        $matchCount = count($matches);

        if ($matchCount > 0) {
            $type = (strpos($keywordSplit[0], '$') === 0) ? "script" : "regex";
            $result = array(
                "type" => $type,
                "match_keyword" => $res["keyword"],
                "match_url" => $res["url"],
                "matches" => $matches
            );

            if ($type === "script") {
                $result["script_name"] = substr($keywordSplit[0], 1);
            }

            return $result;
        }
    }
}

function takuy_replacetext_complex_script($script_name, $original_keyword, $match_keyword, $match_url, $matches) {
    /* sanitize script name */
    $scriptName = preg_replace('/[^a-zA-Z0-9_]/', '', $script_name);
    $scriptPath = dirname(__FILE__) . "/scripts/$scriptName.php";

    if (!file_exists($scriptPath)) {
        die("Something horribly wrong happened: $scriptPath");
    }

    require_once($scriptPath);
    return yourls_apply_filter("takuy_scriptreplace_$scriptName", $original_keyword, $match_keyword, $match_url, $matches);
}

function takuy_replacetext_complex_regex($match_url, $matches) {
    $newURL = $match_url;
    /* If there are multiple matched groups - we can replace all of them */
    for ($i = 0; $i < count($matches); $i++) {
        /* Check for matches based on order
               [[1]] will be replaced with the first matched group, [[2]] with the second, etc */
        if (strpos($newURL, "[[$i]]")) {
            $newURL = str_replace("[[$i]]", $matches[$i], $newURL);
        }
        if (strpos($newURL, "[[!$i]]")) {
            $newURL = str_replace("[[!$i]]", strtoupper($matches[$i]), $newURL);
        }
    }
    return $newURL;
}

function takuy_replacetext_replace_path($keyword) {
    $split = explode("/", $keyword[0]);
    /* Simple text replacement */
    if (count($split) > 1) {
        $result = takuy_replacetext_simple_text($split);
        if ($result) {
            yourls_redirect_shorturl($result[0], $result[1]);
            exit();
        }
    } else {
        /* Regex or script replacement */
        $result = takuy_replacetext_complex_text($keyword[0]);
        if (count($result["matches"]) > 0) {
            /* if the keyword begins with a $, this is a call to a script */
            if ($result["type"] === "script") {
                $newURL = takuy_replacetext_complex_script(
                    $result["script_name"],
                    $keyword[0],
                    $result["match_keyword"],
                    $result["match_url"],
                    $result["matches"]
                );
                /* otherwise replace the URL's numbered tokens based on regex match*/
            } else {
                $newURL = takuy_replacetext_complex_regex($result["match_url"], $result["matches"]);
            }

            /* For tracking purposes, we use the matched regex keyword */
            yourls_redirect_shorturl($newURL, $result["match_keyword"]);
            exit();
        }
    }
}

function takuy_replacetext_pre_pre_add_new_link($args) {
    $keyword = $args[1];
    takuy_replacetext_override_sanitize($keyword);
}

function takuy_replacetext_pre_pre_edit_link($return, $keyword, $url, $k, $newkeyword, $title) {
    takuy_replacetext_override_sanitize($newkeyword);
    return $return;
}

/* avoid creating links to takuy_replacetext keywords - pointless for the table. they're not usually valid links. */
function takuy_replacetext_admin_page_before_table($args) {
    takuy_replacetext_override_sanitize("", true);
    yourls_add_filter('table_add_row_cell_array', function ($cells, $keyword, $url, $title, $ip, $clicks, $timestamp) {
        if (takuy_replacetext_is_replaceable($keyword)) {
            $cells["keyword"]["template"] = "%keyword_html%";
        }
        return $cells;
    });

    yourls_add_filter('table_add_row_action_array', function ($actions, $keyword) {
        if (takuy_replacetext_is_replaceable($keyword)) {
            $actions["stats"]["href"] = yourls_statlink(urlencode($keyword));
        }
        return $actions;
    });
};

function takuy_replacetext_get_keywordinfo($return, $keyword, $field, $notfound) {
    takuy_replacetext_override_sanitize($keyword);
    return $return;
}

function takuy_replacetext_stats_keyword_taken($taken, $keyword) {
    if ($GLOBALS["stats"]) {
        $decoded = urldecode($keyword);
        if (takuy_replacetext_is_replaceable($decoded)) {
            takuy_replacetext_override_sanitize($decoded);

            if (yourls_get_keyword_infos($decoded)) {
                $taken = true;
                $GLOBALS["keyword"] = $decoded;
            }
        }
    }
    return $taken;
}

/* TODO: might just replace this whole thing with one function, the stuff in the 2nd if statement */
function takuy_replacetext_override_sanitize($keyword, $conditional = false) {
    /* for cases where a specific keyword is passed, where the hook only affects one keyword */
    if (takuy_replacetext_is_replaceable($keyword)) {
        yourls_add_filter('sanitize_string', function ($sanitized, $original) {
            return $original;
        });
    }

    /* for cases where applying in a hook that will apply to more than just one specific keyword */
    if ($conditional) {
        yourls_add_filter('sanitize_string', function ($sanitized, $original) {
            if (takuy_replacetext_is_replaceable($original)) {
                return $original;
            }
            return $sanitized;
        });
    }
}

/* function to detecting regex and script keywords*/
function takuy_replacetext_is_replaceable($keyword) {
    return (strpos($keyword, "regex/") === 0 || strpos($keyword, "\$") === 0);
}

/* handle admin-ajax.php calls */
function takuy_replacetext_ajax_plugins_loaded($args) {
    if (yourls_is_admin() && yourls_is_Ajax()) {
        takuy_replacetext_override_sanitize('', true);
    }
}
