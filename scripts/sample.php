<?php

class sample {
    static function google_that($keyword, $url, $matches) {
        return "https://www.google.com/search?q=" . $matches[1];
    }
}
yourls_add_filter('takuy_scriptreplace_sample', array('sample','google_that'));

