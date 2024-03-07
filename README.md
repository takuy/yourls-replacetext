# Plugin for YOURLS: replacetext

## What for

This plugin allows you to create dynamic or smart redirects - not 100% sure what to call them, but you can use regex, simple text replacements or a script!

## How to install

* In `/user/plugins`, create a new folder named `replacetext`
* Drop these files in that directory
* Go to the Plugins administration page and activate the plugin 
* Have fun

## How to use

A short URL keyword prefixed with `regex/`, followed by a regex pattern will allow you to redirect based on regex pattern, but also do replacements within the Long URL based on the pattern match. You can use numerical tokens like `[[1]]`, `[[2]]`, etc to insert specific match groups from the regex pattern. Prefix the number in the token with ! to force uppercase the replaced text, ie `[[!1]]`.

A short URL keyword in the format `wordone/wordtwo` will allow you to replace the token `[[wordone]]` in the long URL whatever ``wordtwo`` is in your Navigation URL.  

A replacement token may also be provided in a long URL, in which case a short URL navigation with that token as a query parameter will cause a replacement, ie, if a `[[token]]` is provided in a Long URL like `https://example.com/?lookup=[[token]]` with Short URL `https://sho.rt/ex` - then navigating to URL `https://sho.rt/ex?token=12345` will redirect you to `https://example.com/?lookup=12345`.

A short URL keyword in the format `$scriptname/` following by a regex pattern will allow you to call a script with name "`scriptname.php`" from the folder `./scripts/`. See [sample.php](scripts/sample.php) as an example for how the YOURLS filter hook should be structured. The requested keyword from the navigation URL, the matched Short URL keyword, the matched Long URL, and an array of the Regex matches are passed to the script. 

Most easily described with examples...

Base YOURLS example: `https://sho.rt` 

| Short URL | Long URL  | Navigation URL <br> <sub>what you would navigate to in your browser</sub>  | Final Redirect    |
| ---       | ---           | ---               | ---               |
| `regex/(REQ[0-9]*)`| `https://example.com/?ticket=%22[[1]]%22` | `https://sho.rt/REQ0000010` | `https://example.com/?ticket="REQ0000010"` |
| `whatever`| `https://example.com/?ticket=%22[[req]]%22` | `https://sho.rt/whatever?req=REQ0000010` | `https://example.com/?ticket="REQ0000010"` |
| `regex/(.*)_(.*)` | `https://www.google.com/search?q=[[1]]&tbm=[[2]]` | `https://sho.rt/sample_isch` | `https://www.google.com/search?q=sample&tbm=isch` |
| `req` | `https://example.com/?ticket=%22[[req]]%22` | `https://sho.rt/req/REQ0000010` | `https://example.com/?ticket="REQ0000010"` | 
| `$sample/google_(.*)` | `anything` <br> <sub>passed to the script for use</sub> | `https://sho.rt/google_hello%20world` | `https://www.google.com/search?q=hello%20world` | 



