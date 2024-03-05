<?php

class sample {
    static function google_that($requestedKeyword, $matchedKeyword, $matchedUrl, $matches) {
        return "https://www.google.com/search?q=" . $matches[1];
    }
}
yourls_add_filter('takuy_scriptreplace_sample', array('sample','google_that'));

