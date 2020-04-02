<?php


namespace SwarmWatcher;


/**
 * Analyze report collection and produce readable report with new warnings and warnings that disappeared
 *
 * @TODO use logger object
 * @TODO use SPL for files and directories
 * @TODO use objects/factories for better testing
 */
class WebInterface
{

    /**
     * @var string
     */
    private $collectedHealthReportsRootPath;

    /**
     * @var string
     */
    private $localCacheRootPath;

    /**
     * ReportAnalyzer constructor.
     * @param $collectedHealthReportsRootPath string
     * @param $localCacheRootPath string
     * @throws \Exception
     */
    function __construct($collectedHealthReportsRootPath, $localCacheRootPath)
    {
        $this->collectedHealthReportsRootPath = $collectedHealthReportsRootPath;
        $this->localCacheRootPath = $localCacheRootPath;

        //@TODO validate the input
        if (empty(getenv("KD_SYSTEM_NAME"))) {
            throw new \Exception("Empty environment variable KD_SYSTEM_NAME");
        }
        if (empty(getenv("KD_EMAIL_NOTIFICATION_RECIPIENT"))) {
            throw new \Exception("Empty environment variable KD_EMAIL_NOTIFICATION_RECIPIENT");
        }

    }

    /**
     * @param $msg
     */
    function log($msg)
    {
        echo "[".date("Y-m-d H:i:s")."][".basename(__CLASS__)."] ".$msg."\n";
    }

    /**
     * @param $timestamp
     * @return string
     */
    public function ago($timestamp)
    {
        $daysAgo = floor((time() - $timestamp) / (3600 * 24));
        $hoursAgo = floor(((time() - $timestamp) - ($daysAgo * 3600 * 24)) / (3600));
        $minutesAgo = floor(((time() - $timestamp) - ($daysAgo * 3600 * 24) - ($hoursAgo * 3600)) / (60));

        return "<b>".$daysAgo."</b>d <b>".$hoursAgo."</b>h <b>".$minutesAgo."</b>m";
    }

    /**
     * @param $text
     * @return string
     */
    public function warning($text)
    {
        return "<span class='warning'>$text</span>";
    }

    /**
     * @param $serviceReportPayload
     */
    public function showServiceReportNgrok($serviceReportPayload)
    {
        echo "<ul>";
        echo "<li> url: <a href='http://".$serviceReportPayload['ngrok_url']."'>".$serviceReportPayload['ngrok_url']."</a>";
        echo "</ul>";
    }

    /**
     * @param array $report
     */
    public function showReport($report)
    {
        $payload = $report['payload'];
        $version = $payload['version'];
        $systemName = $payload['system_name'];
        //$minutesAgo = floor((time() - $payload['timestamp']) / (60));

        echo "<b>$systemName</b><br>";

        //echo "raport received at: <b>".date("Y-m-d H:i:s", $report['timestamp'])."</b><br>";

        echo "time: <b>".$payload['local_time']."</b> ";
        echo "(".$this->ago($payload['timestamp'])." ago)";
        if ((time() - $payload['timestamp']) > 1200) {
            echo $this->warning("report is old");
        }
        echo "<br>";
        echo "cpu temp: <b>".$payload['cpu_temp']." C</b><br>";
        echo "uptime: <b>".(floor($payload['uptime_seconds'] / (3600 * 24)))." days</b><br>";
        echo "disk space avail: <b>".(number_format($payload['disk_space_available_kb'] / (1024 * 1024), 2, '.', ''))." GB</b><br>";

        if (!empty($payload['services']['ngrok']['report']['ngrok_url'])) {
            $ngrokUrl = "http://".$payload['services']['ngrok']['report']['ngrok_url']."";
            echo "ngrok url: <a href='".$ngrokUrl."'>".$ngrokUrl."</a><br>";
            $videoStreamUrl = $ngrokUrl."/video";
            echo "video stream: <a href='".$videoStreamUrl."'>".$videoStreamUrl."</a><br>";
        }

        if ($version == 1) {

            $videoStreamInfo = $payload['video_stream'];
            echo "video stream: ".$videoStreamInfo;
            if (strpos($videoStreamInfo, "Stream #0:0: Video: mjpeg") === false) {
                echo $this->warning("video format is invalid");
            }

        } elseif ($version == 2) {

            echo "<ul>";
            foreach ($payload['services'] as $serviceName => $serviceReportFullData) {
                echo "<li>".$serviceName." (".($serviceReportFullData['is_enabled'] == 1 ? "enabled" : "<span style='color:red'>disabled</span>").")<br>";
                if (!empty($serviceReportFullData['report']['timestamp'])) {
                    echo "reported at: ".date("Y-m-d H:i:s", $serviceReportFullData['report']['timestamp'])." (".$this->ago($serviceReportFullData['report']['timestamp']).")";
                }
                switch ($serviceName) {
                    case "ngrok":
                        $this->showServiceReportNgrok($serviceReportFullData['report']);
                        break;
                }
            };
            echo "<ul>";

        } else {
            echo "ERROR: unsupported raport payload version $version";
        }
    }

    /**
     *
     */
    public function showReportsAsWebPage()
    {

        echo "
            <html>
            <head>
                <title>Swarm Watcher (".htmlspecialchars(getenv("KD_SYSTEM_NAME")).")</title>
            </head>
            <style>
                .report {
                    display: inline-block;
                    border: 1px solid black;
                    border-radius: 3px;
                    margin: 1px;
                    padding: 5px;
                    background: #efefef;
                    color: black;
                    font-size:11px;
                    vertical-align:top; 
                }
                
                .warning {
                    display: inline-block;
                    border: 1px solid red;
                    border-radius: 3px;
                    margin: 1px;
                    padding: 1px;
                    background: #ffaaaa;
                    color: black;
                }
            </style>
            <body>
        ";

        //scan all collected report files, visualize
        $reportFiles = glob($this->collectedHealthReportsRootPath."/*.json");
        foreach ($reportFiles as $fileName) {
            //echo "<div style='font-size:11px; margin:5px; border: solid 1px black; padding:5px; display: inline-block; min-width:200px; min-height: 100px; vertical-align: top'>";
            echo "<div class='report'>";
            $fileContent = file_get_contents($fileName);
            if (empty($fileContent)) {
                //error
                echo "ERROR: Cannot get content from $fileName";
            } else {
                $report = json_decode($fileContent, true);
                if ($report === false) {
                    echo "ERROR: invalid json from $fileName";
                } else {
                    $this->showReport($report);
                }
            }
            echo "</div>";
        }

        echo "
            </body>
            
            </html>
        ";
    }

}


