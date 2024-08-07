<?php
ob_start();
?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>ServerMon</title>
        <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png?v=1719392798">
        <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32x32.png?v=1719392798">
        <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16x16.png?v=1719392798">
        <link rel="manifest" href="/icons/site.webmanifest?v=1719392798">
        <link rel="mask-icon" href="/icons/safari-pinned-tab.svg?v=1719392798" color="#5bbad5">
        <link rel="shortcut icon" href="/icons/favicon.ico?v=1719392798">
        <meta name="apple-mobile-web-app-title" content="ServerMon">
        <meta name="application-name" content="ServerMon">
        <meta name="msapplication-TileColor" content="#000000">
        <meta name="msapplication-config" content="/icons/browserconfig.xml?v=1719392798">
        <meta name="theme-color" content="#ffffff">
        <link href="style.css?v=1719392798" type="text/css" rel="stylesheet"/>
    </head>

    <!--suppress HtmlUnknownAttribute -->
    <body oncontextmenu="return false;" onselectstart="return false" unselectable="on">

    <?php
    echo '<script>';
    echo 'setTimeout(function(){location.reload();}, 1000);'; // Refresh the page after 5 seconds
    echo '</script>';
    ?>

    <?php

    $hostn = shell_exec('hostname');
    echo "<h1>$hostn</h1>";

    $verar = shell_exec('cat /etc/os-release');
    $versn = explode(PHP_EOL, $verar)[0];
    $verps = str_replace('PRETTY_NAME="', "", $versn);
    $verps = str_replace('"', "", $verps);

    $uptme = shell_exec('uptime -p');
    $uptme = str_replace(array("\r\n", "\r", "\n", "\t", ","), "", $uptme);
    $uptme = str_replace(array("up"), "<span class=\"type\">UP</span>", $uptme);
    $uptme = str_replace(array(" weeks ", " week "), "<span class=\"unit\">W</span><span class=\"unit\"></span>", $uptme);
    $uptme = str_replace(array(" days ", " day "), "<span class=\"unit\">D</span><span class=\"unit\"></span>", $uptme);
    $uptme = str_replace(array(" hours ", " hour "), "<span class=\"unit\">H</span><span class=\"unit\"></span>", $uptme);
    $uptme = str_replace(array(" minutes", " minute"), "<span class=\"unit\">M</span><span class=\"unit\"></span>", $uptme);

    if ($verps == null or $verps == "") {
        echo "<div class=\"left\"><h2>$uptme</span></h2></div><br/><br/><br/><br/><br/>";
    } else {
        echo "<div class=\"left\"><h2>$verps</h2></div>";
        echo "<div class=\"right\"><h2>$uptme</span></h2></div><br/><br/><br/><br/><br/>";
    }

    ?>

    <div class="module">
        <?php

        $model = shell_exec('lscpu | grep \'Model name\' | cut -f 2 -d ":" | awk \'{$1=$1}1\'');
        $model = str_replace(array("\r\n", "\r", "\n", "\t", "(R)", "(TM)"), "", $model);

        $archn = trim(shell_exec('arch'));

        $cpusn = shell_exec('cat /proc/cpuinfo | grep "physical id" | sort | uniq -c | wc -l');
        $cpusn = str_replace(array("\r\n", "\r", "\n", "\t"), "", $cpusn);
        $cores = shell_exec('cat /proc/cpuinfo | grep processor | wc -l');
        $cores = str_replace(array("\r\n", "\r", "\n", "\t"), "", $cores);
        $output2 = shell_exec("cat /sys/class/thermal/thermal_zone*/temp");
        $output2 = $output2 ? str_replace(array("\r\n", "\r", "\n", "\t"), "", $output2) : "";
        $number = floatval($output2);
        $output2 = number_format($number / 1000, 1);

        $cpust = shell_exec("cat /proc/stat | grep cpu");

        $array = explode(PHP_EOL, $cpust);

        $count = count($array);

        $c0arr = explode(" ", $array[0]);
        $c0idl = $c0arr[5];
        $c0tot = $c0arr[2] + $c0arr[3] + $c0arr[4] + $c0arr[5] + $c0arr[6] + $c0arr[7] + $c0arr[8] + $c0arr[9] + $c0arr[10] + $c0arr[11];
        if (!empty($_COOKIE['core0'])) {
            $c0ars = unserialize($_COOKIE['core0']);
            $c0ids = $c0ars[5];
            $c0tos = $c0ars[2] + $c0ars[3] + $c0ars[4] + $c0ars[5] + $c0ars[6] + $c0ars[7] + $c0ars[8] + $c0ars[9] + $c0ars[10] + $c0ars[11];
        } else {
            $c0ids = 0;
            $c0tos = 0;
        }

        setcookie('core0', serialize($c0arr), time() + 3600);

        $c0idm = $c0idl - $c0ids;
        $c0tom = $c0tot - $c0tos;

        $cpuut = number_format(100 * ($c0tom - $c0idm) / $c0tom);

        $cpuld = explode(" ", shell_exec('cat /proc/loadavg'));

        echo "<div class=\"left\">$model";
        if ($archn == null or $archn == "") {
            echo "</div>";
        } else {
            echo "<span> (</span>$archn<span>)</span></div>";
        }
        echo "<div class=\"right\">";
        if ($cpusn != 0) {
            echo "$cpusn" . "<span class=\"unit\">CPU</span>&nbsp;";
        }
        echo "$cores" . "<span class=\"unit\">Cores</span>&nbsp;$cpuut<span class=\"unit\">%</span>";
        echo "<span> (</span>$cpuld[0]" . "&nbsp;$cpuld[1]" . "&nbsp;$cpuld[2]" . "<span>)</span>";
        if ($output2 == 0) {
            echo "</div><br/><br/>";
        } else {
            echo "&nbsp;" . "$output2" . "<span class=\"unit\">°C</span></div><br/><br/>";
        }

        for ($c = 1; $c < count($array) - 1; $c++) {
            $modul = "<div class=\"space\"></div>";
            $modul .= "<div class=\"module\">\n";

            $arran = explode(" ", $array[$c]);

            $usern = $arran[1];
            $systm = $arran[3];
            $iowat = $arran[5];
            $steal = $arran[8];
            $total = $arran[1] + $arran[2] + $arran[3] + $arran[4] + $arran[5] + $arran[6] + $arran[7] + $arran[8] + $arran[9] + $arran[10];

            if (isset($_COOKIE['core' . $c])) {
                $arrse = unserialize($_COOKIE['core' . $c]);
                $users = $usern - $arrse[1];
                $systs = $systm - $arrse[3];
                $iowas = $iowat - $arrse[5];
                $steas = $steal - $arrse[8];
                $totas = $total - $arrse[1] - $arrse[2] - $arrse[3] - $arrse[4] - $arrse[5] - $arrse[6] - $arrse[7] - $arrse[8] - $arrse[9] - $arrse[10];
            } else {
                $users = 0;
                $systs = 0;
                $iowas = 0;
                $steas = 0;
                $totas = 1;
            }

            setcookie('core' . $c, serialize($arran), time() + 3600);

            $userp = floor(floatval($users) / floatval($totas) * 100);
            $systp = floor(floatval($systs) / floatval($totas) * 100);
            $iowap = floor(floatval($iowas) / floatval($totas) * 100);
            $steap = floor(floatval($steas) / floatval($totas) * 100);

            $test = floatval($users) / floatval($totas);

            $bar = "<div class=\"bar\">\n";
            $bar .= str_repeat("<i class=\"element usr\"></i>\n", $userp);
            $bar .= str_repeat("<i class=\"element sys\"></i>\n", $systp);
            $bar .= str_repeat("<i class=\"element blu\"></i>\n", $iowap);
            $bar .= str_repeat("<i class=\"element yel\"></i>\n", $steap);
            $bar .= str_repeat("<i class=\"element\"></i>\n", 100 - $userp - $systp - $iowap - $steap);
            $bar .= "</div>\n";
            print $bar;
        }

        ?>
    </div>

    <div class="space"></div>

    <div class="module">
        <?php

        $free1 = shell_exec('cat /proc/meminfo | grep MemFree');
        $posn1 = strpos($free1, ':');
        $free1 = substr($free1, $posn1 + 1);
        $free1 = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $free1);
        $free1 = number_format(floatval($free1) / 1024 / 1024, 2);

        $total = shell_exec('cat /proc/meminfo | grep MemTotal');
        $posn2 = strpos($total, ':');
        $total = substr($total, $posn2 + 1);
        $total = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $total);
        $total = number_format(floatval($total) / 1024 / 1024, 2);

        $buffs = shell_exec('cat /proc/meminfo | grep Buffers');
        $posn3 = strpos($buffs, ':');
        $buffs = substr($buffs, $posn3 + 1);
        $buffs = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $buffs);
        $buffs = number_format(floatval($buffs) / 1024 / 1024, 2);

        $cache = shell_exec('cat /proc/meminfo | grep Cached');
        $posn4 = strpos($cache, ':');
        $cache = substr($cache, $posn4 + 1);
        $cache = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $cache);
        $cache = number_format(floatval($cache) / 1024 / 1024, 2);

        $avail = shell_exec('cat /proc/meminfo | grep MemAvailable');
        $posn5 = strpos($avail, ':');
        $avail = substr($avail, $posn5 + 1);
        $avail = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $avail);
        $avail = number_format(floatval($avail) / 1024 / 1024, 2);

        $used1 = $total - $free1 - $cache - $buffs;

        echo "<div class=\"left\"><span class=\"type\">MEM</span></div>";
        echo "<div class=\"right\">$used1" . "<span class=\"slash\">/</span>" . "$total" . "<span class=\"unit\">GB</span></div><br/><br/>";

        $usedp = floor(floatval($used1) / floatval($total) * 100);
        $buffp = floor(floatval($buffs) / floatval($total) * 100);
        $cachp = floor(floatval($cache) / floatval($total) * 100);

        $bar = "<div class=\"bar\">\n";
        $bar .= str_repeat("<i class=\"element usr\"></i>\n", $usedp);
        $bar .= str_repeat("<i class=\"element blu\"></i>\n", $buffp);
        $bar .= str_repeat("<i class=\"element gry\"></i>\n", $cachp);
        for ($i = $usedp + $buffp + $cachp; $i < 100; $i++) {
            $bar .= "<i class=\"element\"></i>\n";
        }
        $bar .= "</div>\n";
        print $bar;

        echo "<div class=\"space\"></div>";

        $swap1 = shell_exec('cat /proc/meminfo | grep SwapFree');
        $posn1 = strpos($swap1, ':');
        $swap1 = substr($swap1, $posn1 + 1);
        $swap1 = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $swap1);
        $swap1 = number_format(floatval($swap1) / 1024 / 1024, 2);

        $swap2 = shell_exec('cat /proc/meminfo | grep SwapTotal');
        $posn2 = strpos($swap2, ':');
        $swap2 = substr($swap2, $posn2 + 1);
        $swap2 = str_replace(array("\r\n", "\r", "\n", "\t", " ", "kB"), "", $swap2);
        $swap2 = number_format(floatval($swap2) / 1024 / 1024, 2);

        if (floatval($swap2) == 0) {
            $freep = 100;
        } else {
            $freep = floor(floatval($swap1) / floatval($swap2) * 100);
        }

        $swap1 = $swap2 - $swap1;

        echo "<div class=\"left\"><span class=\"type\">SWAP</span></div>";
        echo "<div class=\"right\">$swap1" . "<span class=\"slash\">/</span>" . "$swap2" . "<span class=\"unit\">GB</span></div><br/><br/>";

        $bar = "<div class=\"bar\">\n";
        $bar .= str_repeat("<i class=\"element usr\"></i>\n", 100 - $freep);
        $bar .= str_repeat("<i class=\"element\"></i>\n", $freep);
        $bar .= "</div>\n";
        print $bar;

        ?>
    </div>

    <?php

    if (!function_exists('str_contains')) {
        function str_contains($haystack, $needle): bool
        {
            return $needle !== '' && mb_strpos($haystack, $needle) !== false;
        }
    }

    $model = shell_exec("lspci | grep VGA");

    if ($model != null) {
        echo '<div class="space"></div><div class="module">';

        $model = str_replace(array("\r\n", "\r", "\n", "\t"), "", $model);
        $posn1 = strpos($model, ':');
        $model = substr($model, $posn1 + 1);
        $posn2 = strpos($model, ':');
        $model = substr($model, $posn2 + 2);

        echo "<div class=\"left\">$model</div>";

        if (str_contains($model, 'NVIDIA')) {

            $temp1 = shell_exec('nvidia-smi --query-gpu=temperature.gpu --format=csv,noheader');
            $temp1 = str_replace(array("\r\n", "\r", "\n", "\t"), "", $temp1);
            $used1 = shell_exec('nvidia-smi --query-gpu=memory.used --format=csv,noheader');
            $used1 = str_replace(array("\r\n", "\r", "\n", " ", "MiB"), "", $used1);
            $total = shell_exec('nvidia-smi --query-gpu=memory.total --format=csv,noheader');
            $total = str_replace(array("\r\n", "\r", "\n", " ", "MiB"), "", $total);
            echo "<div class=\"right\">$temp1" . "<span class=\"unit\">°C</span></div><br/><br/>";

            echo "<div class=\"left\"><span class=\"type\">MEM</span></div>";
            echo "<div class=\"right\">$used1" . "<span class=\"slash\">/</span>" . "$total" . "<span class=\"unit\">MB</span></div><br/><br/>";

            $gpupt = shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader');
            $gpupt = str_replace(array("\r\n", "\r", "\n", "\t", " ", "%"), "", $gpupt);
            $mempt = shell_exec('nvidia-smi --query-gpu=utilization.memory --format=csv,noheader');
            $mempn = str_replace(array("\r\n", "\r", "\n", "\t", " ", "%"), "", $mempt);

            $bar = "<div class=\"bar\">\n";
            $bar .= str_repeat("<i class=\"element usr\"></i>\n", $mempn);
            $bar .= str_repeat("<i class=\"element\"></i>\n", 100 - $mempn);
            $bar .= "</div>\n";
            print $bar;

            echo "<br/>";
            echo "<div class=\"left\"><span class=\"type\">USE</span></div>";
            echo "<div class=\"right\">" . "$gpupt" . "<span class=\"unit\">%</span></div><br/><br/>";

            $bar = "<div class=\"bar\">\n";
            $bar .= str_repeat("<i class=\"element usr\"></i>\n", $gpupt);
            $bar .= str_repeat("<i class=\"element\"></i>\n", 100 - $gpupt);
            $bar .= "</div>\n";
            print $bar;

            echo '</div>';
        } else {
            echo '<br/>';
        }
        echo '</div>';
    }

    ?>

    <?php

    $disks = shell_exec("df -h -P | grep -wv tmpfs | grep -wv devtmpfs");

    $array = explode(PHP_EOL, $disks);

    $count = count($array);

    function filter($arr): bool
    {
        if ($arr === '' || $arr === null) {
            return false;
        }
        return true;
    }

    for ($i = 1; $i < $count - 1; $i++) {
        $modul = "<div class=\"space\"></div>";
        $modul .= "<div class=\"module\">\n";

        $arran = explode(" ", $array[$i]);
        $arran = array_filter($arran, 'filter');
        $arran = array_values($arran);
        $perct = str_replace(array("%"), "", $arran[4]);

        $siztn = substr($arran[1], 0, strlen($arran[1]) - 1);
        $siztu = substr($arran[1], -1);

        if ($arran[2] === '0') {
            $sizun = 0;
            $sizuu = $siztu;
        } else {
            $sizun = substr($arran[2], 0, strlen($arran[2]) - 1);
            $sizuu = substr($arran[2], -1);
        }

        $modul .= "<div class=\"left\">$arran[0]</div>\n";
        $modul .= "<div class=\"right\"><span class=\"type\">$arran[5]</span></div><br/><br/>\n";

        $modul .= "<div class=\"right\">$sizun<span class=\"unit\">$sizuu</span><span class=\"slash\">/</span>$siztn<span class=\"unit\">$siztu</span></div><br/><br/>";

        $modul .= "<div class=\"bar\">\n";
        $modul .= str_repeat("<i class=\"element usr\"></i>\n", $perct);
        $modul .= str_repeat("<i class=\"element\"></i>\n", 100 - $perct);
        $modul .= "</div>\n";

        $modul .= "</div>\n";

        echo $modul;
    }

    ?>

    </body>
    </html>

<?php
ob_end_flush();
?>