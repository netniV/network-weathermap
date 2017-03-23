<?php

// ******************************************
// sensible defaults
$mapdir = 'configs';
$cacti_base = '../../';
$cacti_url = '/';
$ignore_cacti = false;

$config['base_url'] = $cacti_url;
$config['cacti_version'] = "NONE";

# if your installation keeps plugins separate from the cacti install, you might need to manually set this
# (e.g. Debian/ubuntu package-based installs probably need it)

# $cacti_base = "/var/www/html/cacti-0.8.8h";

@include_once 'editor-config.php';

if (is_dir($cacti_base) && file_exists($cacti_base . "/include/global.php")) {
    // include the cacti-config, so we know about the database
    include_once $cacti_base . "/include/global.php";

    $config['base_url'] = (isset($config['url_path']) ? $config['url_path'] : $cacti_url);
    $cacti_found = true;

    require_once dirname(__FILE__) . "/lib/database.php";
} else {
    $cacti_found = false;
    print "NO CACTI";
    exit();
}

$jquery = '<script type="text/javascript" src="vendor/jquery.min.js"></script>';
if (substr($config['cacti_version'], 0, 2) == "1.") {
    $jquery = "";
}

$pdo = weathermap_get_pdo();

require_once dirname(__FILE__) . "/lib/cacti-pick.php";

$ui = new EditorDataPicker();
$ui->main($_REQUEST);
exit();



// *************************************************************************************************************************************



if (isset($_SESSION['cacti']['weathermap']['last_used_host_id'][0])) {
    print "<b>Last Host Selected:</b><br>";
    $last['id'] = array_reverse($_SESSION['cacti']['weathermap']['last_used_host_id']);
    $last['name'] = array_reverse($_SESSION['cacti']['weathermap']['last_used_host_name']);

    foreach ($last['id'] as $key => $id) {
        list($name) = explode(" - ", $last['name'][$key], 2);
        print "<a href=cacti-pick.php?host_id=" . $id . "&command=link_step1&overlib=1&aggregate=0>[" . $name . "]</a><br>";
    }
}

if (isset($_REQUEST['command']) && $_REQUEST["command"] == 'link_step1') {

    $host_id = -1;

    $overlib = true;
    $aggregate = false;

    if (isset($_REQUEST['aggregate'])) {
        $aggregate = ($_REQUEST['aggregate'] == 0 ? false : true);
    }

    if (isset($_REQUEST['overlib'])) {
        $overlib = ($_REQUEST['overlib'] == 0 ? false : true);
    }

    if (isset($_REQUEST['host_id']) && intval($_REQUEST['host_id']) >= 0) {
        $host_id = intval($_REQUEST['host_id']);
        $statement = $pdo->prepare("SELECT data_local.host_id, data_template_data.local_data_id, data_template_data.name_cache as description, data_template_data.active, data_template_data.data_source_path FROM data_local,data_template_data,data_input,data_template WHERE data_local.id=data_template_data.local_data_id AND data_input.id=data_template_data.data_input_id AND data_local.data_template_id=data_template.id  AND data_local.host_id=?  ORDER BY name_cache;");
        $statement->execute(array(intval($_REQUEST['host_id'])));
    } else {
        $statement = $pdo->prepare("SELECT data_local.host_id, data_template_data.local_data_id, data_template_data.name_cache as description, data_template_data.active, data_template_data.data_source_path FROM data_local,data_template_data,data_input,data_template WHERE data_local.id=data_template_data.local_data_id AND data_input.id=data_template_data.data_input_id AND data_local.data_template_id=data_template.id  ORDER BY name_cache;");
        $statement->execute();
    }

    $sources = $statement->fetchAll(PDO::FETCH_ASSOC);
    uasort($sources, "usortNaturalDescriptions");

    $hosts_stmt = $pdo->prepare("SELECT id,CONCAT_WS('',description,' (',hostname,')') AS name FROM host ORDER BY description,hostname");
    $hosts_stmt->execute();
    $hosts = $hosts_stmt->fetchAll(PDO::FETCH_ASSOC);
    uasort($hosts, "usortNaturalNames");

    $tpl = new SimpleTemplate();
    $tpl->set("title", "Pick a data source");
    $tpl->set("selected_host", $host_id);
    $tpl->set("hosts", $hosts);
    $tpl->set("recents", $ui->getRecentHosts());
    $tpl->set("sources", $sources);
    $tpl->set("overlib", ($overlib ? 1 : 0));
    $tpl->set("aggregate", ($aggregate ? 1 : 0));
    $tpl->set("base_url", isset($config['base_url']) ? $config['base_url'] : '');
    $tpl->set("rra_path", jsEscape($config['rra_path']));

    echo $tpl->fetch("editor-resources/templates/picker-data.php");
    exit();
} // end of link step 1


if (isset($_REQUEST['command']) && $_REQUEST["command"] == 'link_step2') {
    $dataId = intval($_REQUEST['dataid']);
    $hostId = $_REQUEST['host_id'];

    list($graphId, $name) = $ui->getCactiGraphForDataSource($dataId);

    $ui->updateRecentHosts($hostId, $name);

    ?>
    <html>
    <head>
        <script type="text/javascript">
            function update_source_step2(graphid) {
                var graph_url, info_url;

                var base_url = '<?php echo isset($config['base_url']) ? $config['base_url'] : ''; ?>';

                if (typeof window.opener == "object") {

                    graph_url = base_url + 'graph_image.php?rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300&local_graph_id=' + graphid;
                    info_url = base_url + 'graph.php?rra_id=all&local_graph_id=' + graphid;

                    opener.document.forms["frmMain"].link_infourl.value = info_url;
                    opener.document.forms["frmMain"].link_hover.value = graph_url;
                }
                self.close();
            }

            window.onload = update_source_step2(<?php echo $graphId ?>);

        </script>
    </head>
    <body>
    This window should disappear in a moment.
    </body>
    </html>
    <?php

    // end of link step 2
}



if (isset($_REQUEST['command']) && $_REQUEST["command"] == 'node_step1') {
    $host_id = -1;

    $overlib = true;
    $aggregate = false;

    if (isset($_REQUEST['aggregate'])) {
        $aggregate = ($_REQUEST['aggregate'] == 0 ? false : true);
    }
    if (isset($_REQUEST['overlib'])) {
        $overlib = ($_REQUEST['overlib'] == 0 ? false : true);
    }

    if (isset($_REQUEST['host_id']) && intval($_REQUEST['host_id']) >= 0) {
        $statement = $pdo->prepare("SELECT graph_templates_graph.id, graph_local.host_id, graph_templates_graph.local_graph_id, graph_templates_graph.height, graph_templates_graph.width, graph_templates_graph.title_cache, graph_templates.name, graph_local.host_id	FROM (graph_local,graph_templates_graph) LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) WHERE graph_local.id=graph_templates_graph.local_graph_id AND graph_local.host_id=? ORDER BY title_cache");
        $statement->execute(array(intval($_REQUEST['host_id'])));
    } else {
        $statement = $pdo->prepare("SELECT graph_templates_graph.id, graph_local.host_id, graph_templates_graph.local_graph_id, graph_templates_graph.height, graph_templates_graph.width, graph_templates_graph.title_cache, graph_templates.name, graph_local.host_id	FROM (graph_local,graph_templates_graph) LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) WHERE graph_local.id=graph_templates_graph.local_graph_id ORDER BY title_cache");
        $statement->execute(array());
    }

    $queryrows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $hosts_stmt = $pdo->prepare("SELECT id,CONCAT_WS('',description,' (',hostname,')') AS name FROM host ORDER BY description,hostname");
    $hosts_stmt->execute();
    $hosts = $hosts_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <html>
      <head>
        <?php echo $jquery ?>
        <script type="text/javascript">

            function filterlist(previous) {
                var filterstring = $('input#filterstring').val();

                if (filterstring == '') {
                    $('ul#dslist > li').show();
                    return;
                }

                if (filterstring != previous) {
                    $('ul#dslist > li').hide();
                    $('ul#dslist > li').contains(filterstring).show();
                }
            }

            $(document).ready(function () {
                $('span.filter').keyup(function () {
                    var previous = $('input#filterstring').val();
                    setTimeout(function () {
                        filterlist(previous)
                    }, 500);
                }).show();
            });

            function applyDSFilterChange(objForm) {
                strURL = '?host_id=' + objForm.host_id.value;
                strURL = strURL + '&command=node_step1';
                if (objForm.overlib.checked) {
                    strURL = strURL + "&overlib=1";
                }
                else {
                    strURL = strURL + "&overlib=0";
                }

                document.location = strURL;
            }

        </script>
        <script type="text/javascript">

            function update_source_step1(graphid) {
                var graph_url, hover_url, info_url;

                var base_url = '<?php echo isset($config['base_url']) ? $config['base_url'] : ''; ?>';

                if (typeof window.opener == "object") {

                    graph_url = base_url + 'graph_image.php?rra_id=0&graph_nolegend=true&graph_height=100&graph_width=300&local_graph_id=' + graphid;
                    info_url = base_url + 'graph.php?rra_id=all&local_graph_id=' + graphid;

                    // only set the overlib URL unless the box is checked
                    if (document.forms['mini'].overlib.checked) {
                        opener.document.forms["frmMain"].node_infourl.value = info_url;
                    }
                    opener.document.forms["frmMain"].node_hover.value = graph_url;
                }
                self.close();
            }
        </script>
        <style type="text/css">
            body {
                font-family: sans-serif;
                font-size: 10pt;
            }

            ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            ul {
                border: 1px solid black;
            }

            ul li.row0 {
                background: #ddd;
            }

            ul li.row1 {
                background: #ccc;
            }

            ul li {
                border-bottom: 1px solid #aaa;
                border-top: 1px solid #eee;
                padding: 2px;
            }

            ul li a {
                text-decoration: none;
                color: black;
            }
        </style>
        <title>Pick a graph</title>
    </head>
    <body>

    <h3>Pick a graph:</h3>

    <form name="mini">
        <?php
        if (sizeof($hosts) > 0) {
            print 'Host: <select name="host_id"  onChange="applyDSFilterChange(document.mini)">';

            print '<option ' . ($host_id == -1 ? 'SELECTED' : '') . ' value="-1">Any</option>';
            print '<option ' . ($host_id == 0 ? 'SELECTED' : '') . ' value="0">None</option>';

            uasort($hosts, "usort_natural_hosts");

            foreach ($hosts as $host) {
                print '<option ';
                if ($host_id == $host['id']) {
                    print " SELECTED ";
                }
                print 'value="' . $host['id'] . '">' . $host['name'] . '</option>';
            }
            print '</select><br />';
        }

        print '<span class="filter" style="display: none;">Filter: <input id="filterstring" name="filterstring" size="20"> (case-sensitive)<br /></span>';
        print '<input id="overlib" name="overlib" type="checkbox" value="yes" ' . ($overlib ? 'CHECKED' : '') . '> <label for="overlib">Set both OVERLIBGRAPH and INFOURL.</label><br />';
        ?>
    </form>
    <div class="listcontainer">
        <ul id="dslist">
            <?php

            $i = 0;

            uasort($queryrows, "usort_natural_titles");

            if (is_array($queryrows) && sizeof($queryrows) > 0) {
                foreach ($queryrows as $line) {
                    echo "<li class=\"row" . ($i % 2) . "\">";
                    $key = $line['local_graph_id'];
                    echo "<a href=\"#\" onclick=\"update_source_step1('$key')\">" . $line['title_cache'] . "</a>";
                    echo "</li>\n";
                    $i++;
                }
            } else {
                print "No results...";
            }

            ?>
        </ul>
    </body>
    </html>
    <?php
} // end of node step 1

// vim:ts=4:sw=4:
?>
