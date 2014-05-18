<?
require("/home/pi/lib/php/keystore.php");
include("/home/pi/bin/krumo/class.krumo.php");
$out = array();
$meta = json_decode(file_get_contents("data/github.meta.json"), true);
// iterate all 10 pages
for ($page = 1; $page <= 10; $page++) {
    $curl = curl_init("https://api.github.com/users/OllieTerrance/events?page=" . $page);
    $headers = array("Authorization: token " . keystore("github", "token"));
    // check ETag on first page
    if ($page === 1 && !isset($_GET["force"])) {
        curl_setopt($curl, CURLOPT_HEADER, true);
        if (isset($meta["etag"])) {
            array_push($headers, "If-None-Match: \"" . $meta["etag"] . "\"");
        }
    }
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_USERAGENT, "OllieTerrance-Timeline");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $resp = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($page === 1 && !isset($_GET["force"])) {
        if ($code === 304) {
            print(json_encode(array("message" => "No changes since last update.")));
            $out = json_decode(file_get_contents("data/github.json"), true);
            break;
        }
        // updated, save current ETag to meta
        $parts = explode("\r\n\r\n", $resp, 2);
        preg_match("/ETag: \"([0-9a-f]+)\"/", $parts[0], $match);
        $meta = array("etag" => $match[1]);
        $resp = $parts[1];
    }
    if ($code !== 200) {
        print(json_encode(array("message" => "Failed to update page " . $page . " with error code " . $code . ".")));
        $out = json_decode(file_get_contents("data/github.json"), true);
        break;
    }
    $feed = json_decode($resp);
    curl_close($curl);
    if (isset($_GET["raw"])) {
        krumo($feed);
    }
    foreach ($feed as $event) {
        $item = array(
            "links" => array(
                array(
                    "icon" => "book",
                    "text" => $event->repo->name,
                    "link" => "https://github.com/" . $event->repo->name
                )
            ),
            "time" => strtotime($event->created_at)
        );
        $data = $event->payload;
        switch ($event->type) {
            case "CreateEvent":
                $ref = $data->ref;
                if ($data->ref_type === "repository") {
                    $repo = explode("/", $event->repo->name);
                    $ref = $repo[1];
                }
                $item["desc"] = "Created " . $data->ref_type . ": " . $ref;
                if ($data->ref_type === "branch" || $data->ref_type === "tag") {
                    array_push($item["links"], array(
                        "icon" => $data->ref_type === "branch" ? "code-fork" : "tag",
                        "text" => $ref,
                        "link" => "https://github.com/" . $event->repo->name . "/tree/" . $data->ref
                    ));
                }
                break;
            case "DeleteEvent":
                $item["desc"] = "Deleted " . $data->ref_type . ": " . $data->ref;
                break;
            case "ForkEvent":
                $repo = explode("/", $event->repo->name);
                $item["desc"] = "Forked: " . $repo[1];
                array_push($item["links"], array(
                    "icon" => "files-o",
                    "text" => $data->forkee->full_name,
                    "link" => $data->forkee->html_url
                ));
                break;
            case "IssueCommentEvent":
                $item["desc"] = "Commented on issue: " . $data->issue->title;
                array_push($item["links"], array(
                    "icon" => "exclamation-circle",
                    "text" => $data->issue->number,
                    "link" => $data->issue->html_url
                ));
                break;
            case "IssuesEvent":
                $item["desc"] = ucfirst($data->action) . " issue: " . $data->issue->title;
                array_push($item["links"], array(
                    "icon" => "exclamation-circle",
                    "text" => $data->issue->number,
                    "link" => $data->issue->html_url
                ));
                break;
            case "PullRequestEvent":
                $item["desc"] = ucfirst($data->action) . ($data->action === "synchronize" ? "d" : "") . " pull request: " . $data->pull_request->title;
                array_push($item["links"], array(
                    "icon" => "check-square-o",
                    "text" => $data->pull_request->number,
                    "link" => $data->pull_request->html_url
                ));
                break;
            case "PushEvent":
                if ($data->size === 0) {
                    continue;
                } elseif ($data->size === 1) {
                    $msg = explode("\n\n", $data->commits[0]->message);
                    $item["desc"] = "Pushed commit: " . $msg[0];
                    array_push($item["links"], array(
                        "icon" => "dot-circle-o",
                        "text" => substr($data->commits[0]->sha, 0, 7),
                        "link" => "https://github.com/" . $event->repo->name . "/commit/" . $data->commits[0]->sha
                    ));
                } else {
                    $item["desc"] = "Pushed " . $data->size . " commits";
                    $short = substr($data->before, 0, 7) . "..." . substr($data->head, 0, 7);
                    array_push($item["links"], array(
                        "icon" => "bullseye",
                        "text" => $short,
                        "link" => "https://github.com/" . $event->repo->name . "/compare/" . $short
                    ));
                }
                break;
            case "ReleaseEvent":
                $item["desc"] = "Released: " . $data->release->name;
                array_push($item["links"], array(
                    "icon" => "paper-plane",
                    "text" => $data->release->tag_name,
                    "link" => $data->release->html_url
                ));
                break;
            case "WatchEvent":
                $repo = explode("/", $event->repo->name);
                $item["desc"] = "Starred: " . $repo[1];
                break;
            default:
                continue;
        }
        array_push($out, $item);
    }
}
$file = fopen("data/github.json", "w");
fwrite($file, json_encode($out));
fclose($file);
$file = fopen("data/github.meta.json", "w");
fwrite($file, json_encode($meta));
fclose($file);
if (isset($_GET["pretty"])) {
    krumo($out);
    krumo($meta);
}
