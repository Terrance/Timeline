<?
require_once getenv("PHPLIB") . "keystore.php";
require_once getenv("PHPLIB") . "twitter_api.php";
include_once getenv("PHPLIB") . "krumo/class.krumo.php";
$out = array();
$twitter = new TwitterAPIExchange(
    array(
        "consumer_key" => keystore("twitter", "consumer", "key"),
        "consumer_secret" => keystore("twitter", "consumer", "secret"),
        "oauth_access_token" => keystore("twitter", "oauth", "token"),
        "oauth_access_token_secret" => keystore("twitter", "oauth", "secret")
    )
);
// fetch up to 200 tweets
$feed = json_decode($twitter->setGetfield("?contributor_details=true&count=200&exclude_replies=true&include_rts=true")
                            ->buildOauth("https://api.twitter.com/1.1/statuses/user_timeline.json", "GET")
                            ->performRequest());
if (isset($_GET["raw"])) {
    krumo($feed);
}
foreach ($feed as $data) {
    $text = preg_replace("/(\s+)?[a-z]+:\/\/\S+/i", "", $data->text);
    $text = preg_replace("/(^@|[\.\s]@)([a-z0-9_]+)/i", '$1<a href="https://twitter.com/$2">$2</a>', $text);
    $text = preg_replace("/(^#|\s#)([a-z0-9_]+)/i", '$1<a href="https://twitter.com/search?q=%23$2">$2</a>', $text);
    $item = array(
        "desc" => $text,
        "links" => array(
            array(
                "icon" => "twitter",
                "link" => "https://twitter.com/" . $data->user->screen_name . "/status/" . $data->id_str
            )
        ),
        "time" => strtotime($data->created_at)
    );
    foreach ($data->entities->media as $media) {
        array_push($item["links"], array(
            "icon" => "camera",
            "text" => $media->display_url,
            "link" => $media->expanded_url
        ));
    }
    foreach ($data->entities->urls as $url) {
        array_push($item["links"], array(
            "icon" => "link",
            "text" => $url->display_url,
            "link" => $url->expanded_url
        ));
    }
    array_push($out, $item);
}
$file = fopen(getenv("DATA") . "timeline/twitter.json", "w");
fwrite($file, json_encode($out));
fclose($file);
if (isset($_GET["pretty"])) {
    krumo($out);
}
