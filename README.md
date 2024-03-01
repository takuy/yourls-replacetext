# Plugin for YOURLS: replacetext

## What for

This plugin allows you to create dynamic or smart redirects - not 100% sure what to call them, but you can use regex, simple text replacements or a script!

## How to install

* In `/user/plugins`, create a new folder named `replacetext`
* Drop these files in that directory
* Go to the Plugins administration page and activate the plugin 
* Have fun

## How to use

A short URL keyword prefixed with `regex/`, followed by a regex pattern will allow you to redirect based on regex pattern, but also do replacements within the Long URL based on the pattern match. You can use numerical tokens like `[[1]]`, `[[2]]`, etc to insert specific match groups from the regex pattern.

A short URL keyword in the format `wordone/wordtwo` will allow you to replace the token `[[wordone]]` in the long URL whatever ``wordtwo`` is in your Navigation URL.  

A short URL keyword in the format `$scriptname/` following by a regex pattern will allow you to call a script with name "`scriptname.php`" from the folder `./scripts/`. See [sample.php](scripts/sample.php) as an example for how the YOURLS filter hook should be structured. The requested keyword from the navigation URL, the matched Short URL keyword, the matched Long URL, and an array of the Regex matches are passed to the script. 

Most easily described with examples...

Base YOURLS example: `https://sho.rt` 

| Short URL | Long URL  | Navigation URL <br> <sub>what you would navigate to in your browser</sub>  | Final Redirect    |
| ---       | ---           | ---               | ---               |
| `regex/(REQ[0-9]*)`| `https://myticketsystem.com/?ticket=%22[[1]]%22` | `https://sho.rt/REQ0000010` | `https://myticketsystem.com/?ticket=REQ0000010` |
| `regex/(.*)_(.*)` | `https://www.google.com/search?q=[[1]]&tbm=[[2]]` | `https://sho.rt/sample_isch` | `https://www.google.com/search?q=sample&tbm=isch` |
| `req` | `https://myticketsystem.com/?ticket=%22[[req]]%22` | `https://sho.rt/req/REQ0000010` | `https://myticketsystem.com/?ticket=REQ0000010` | 
| `$sample/google_(.*)` | `anything` <br> <sub>passed to the script for use</sub> | `https://sho.rt/google_hello%20world` | `https://www.google.com/search?q=hello%20world` | 



