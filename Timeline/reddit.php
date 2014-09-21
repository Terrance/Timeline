<?
require_once getenv("PHPLIB") . "keystore.php";
include_once getenv("PHPLIB") . "krumo/class.krumo.php";
$out = array();
$meta = json_decode(file_get_contents(getenv("DATA") . "timeline/reddit.meta.json"), true);
// iterate 10 pages
for ($page = 0; $page < 10; $page++) {
    if ($page > 0 && !$after) {
        break;
    }
    $curl = curl_init("http://www.reddit.com/user/OllieTerrance/.json?after=" . $after);
    curl_setopt($curl, CURLOPT_USERAGENT, "Timeline by /u/OllieTerrance");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $feed = json_decode(curl_exec($curl));
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($code !== 200) {
        print(json_encode(array("message" => "Failed to update page " . $page . " with error code " . $code . ".")));
        $out = json_decode(file_get_contents(getenv("DATA") . "timeline/reddit.json"), true);
        break;
    }
    $after = $feed->data->after;
    if ($page === 0 && !isset($_GET["force"])) {
        if ($feed->data->after === $meta["after"]) {
            print(json_encode(array("message" => "No changes since last update.")));
            $out = json_decode(file_get_contents(getenv("DATA") . "timeline/reddit.json"), true);
            break;
        }
        // updated, save current after id to meta
        $meta = array("after" => $feed->data->after);
    }
    curl_close($curl);
    if (isset($_GET["raw"])) {
        krumo($feed);
    }
    foreach ($feed->data->children as $event) {
        $data = $event->data;
        $item = array(
            "links" => array(),
            "time" => $data->created_utc
        );
        switch ($event->kind) {
            // comment
            case "t1":
                $item["desc"] = substr(htmlspecialchars_decode($data->body_html), 16, -7);
                $comments = "http://www.reddit.com/r/" . $data->subreddit . "/comments/" . substr($data->link_id, 3) . "/"; 
                array_push($item["links"], array(
                    "icon" => "comments-o",
                    "text" => $data->link_title,
                    "link" => $comments
                ));
                if (strpos($data->link_url, $comments) > 0) {
                    array_push($item["links"], array(
                        "icon" => "link",
                        "text" => $data->link_url,
                        "link" => $data->link_url
                    ));
                }
                break;
            // post
            case "t3":
                // self-post (text)
                if ($data->is_self) {
                    $item["desc"] = substr(htmlspecialchars_decode($data->body_html), 16, -7);
                    array_push($item["links"], array(
                        "icon" => "comments-o",
                        "text" => $data->title,
                        "link" => $data->url
                    ));
                // external link
                } else {
                    $link = preg_replace("/[a-z]+:\/\/(www\.)?/i", "", $data->url);
                    if (strlen($link) > 30) {
                        $link = substr($link, 0, 25) . "...";
                    }
                    array_push($item["links"], array(
                        "icon" => "comments-o",
                        "text" => $data->title,
                        "link" => "http://www.reddit.com" . $data->permalink
                    ), array(
                        "icon" => "link",
                        "text" => $link,
                        "link" => $data->url
                    ));
                }
                break;
            default:
                continue;
        }
        array_push($out, $item);
    }
}
$file = fopen(getenv("DATA") . "timeline/reddit.json", "w");
fwrite($file, json_encode($out));
fclose($file);
$file = fopen(getenv("DATA") . "timeline/reddit.meta.json", "w");
fwrite($file, json_encode($meta));
fclose($file);
if (isset($_GET["pretty"])) {
    krumo($out);
    krumo($meta);
}
