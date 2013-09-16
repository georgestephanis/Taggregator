Taggregator
===========

_A Social Media Aggregator for WordPress_

Setup
=====

Once installed, go to Settings > Discussion, and scroll down until you get to the __Taggregator Social Media Aggregator__ section.  You will see a number of options:

* __Actively Scraping?__ Whether the cron job that runs is actively trying to access the servers in question.
* __Tag__ What tag you'd like to search for.  Please keep it to one word, and precede it with a hashtag.
* __Twitter Consumer Key__ _(maybe)_ The Consumer Key for your Twitter Application.  Twitter's v1.1 API requires an application to connect and query.
* __Twitter Consumer Secret__ _(maybe)_ The Consumer Secret for your Twitter Application.
* __Instagram Access Token__ _(maybe)_ The Access Token for your Instagram Application.  Instagram requires an application to query its API for media tag items.  Once you create one, you can find your authentication token through their developer panel.  You don't need their application keys, just an authentication token.

The latter three options will only display if they've not been globally defined in constant form by a network administrator.

Input
=====

If __Actively Scraping?__ is selected, then it will attempt to use the credentials provided to scrape the respective services at a given interval (currently 1 hour) via wp_cron.

To trigger a scrape manually, have a site administrator append the `taggregator_cron_active` GET argument to any admin page, like so:

`http://example.com/wp-admin/index.php?taggregator_cron_active`

Please note, however, that this method will be going away soon.  It is merely for testing purposes at the moment.  It will be replaced with a button somewhere in the admin to do the same task via ajaxy goodness.

Output
======

You can output the content on any post or page using the `[taggregator]` shortcode.  It defaults to the most recent 30 items, but can do any number by specifying it as such: `[taggregator qty="50"]`

Future display methods are forthcoming, as well as loading new items in via ajaxy goodness.
