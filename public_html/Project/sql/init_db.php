<h1>Database Helper Tool</h1>
<details>
    <summary>Info (About the tool)</summary>
    <p>The scope of this tool is to help us separate our structural queries into separate files for better organization.</p>
    <p>This tools job is to attempt to read all of those files and determine which ones are needed to run against your database to synchronize the structure.</p>
    <p>This tool only works for queries that take zero parameters.</p>
    <p>It can be used to preload some data via inserts, but those queries <em>MUST</em> but crafted in such a way that you don't generate duplicates during each run.</p>
    <p>Files should be <a href="https://en.wikipedia.org/wiki/Idempotence">Idempotent</a></p>
</details>
<br><br>
<?php


#turn error reporting on
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//pull in db.php so we can access the variables from it
require_once(__DIR__ . "/../../../lib/db.php");
$count = 0;
try {
    
    foreach (glob(__DIR__ . "/*.sql") as $filename) {
        $sql[$filename] = file_get_contents($filename);
    }

    if (isset($sql) && $sql && count($sql) > 0) {
        echo "<p>Found " . count($sql) . " files...</p>";
    
        ksort($sql);
        //connect to DB
        $db = getDB();
       
        $stmt = $db->prepare("show tables");
        $stmt->execute();
        $count++;
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $t = [];
        //convert it to a flat array
        foreach ($tables as $row) {
            foreach ($row as $key => $value) {
                array_push($t, $value);
            }
        }
        foreach ($sql as $key => $value) {
?>
            <details>
                <summary><?php echo "Running: $key"; ?></summary>
                <pre><code><?php echo $value; ?></code></pre>
            </details>
            <?php
            $lines = explode("(", $value, 2);
            if (count($lines) > 0) {
               
                $line = $lines[0];
                $line = preg_replace('!\s+!', ' ', $line);
                $line = str_ireplace("create table", "", $line);
                $line = str_ireplace("if not exists", "", $line);
                $line = str_ireplace("`", "", $line);
                $line = trim($line);
                if (in_array($line, $t)) {
                    echo "<p style=\"margin-left: 3em\">Blocked from running, table found in 'show tables' results. [This is ok, it reduces redundant DB calls]</p><br>";
                    continue;
                }
            }
            $stmt = $db->prepare($value);
            try {
                $result = $stmt->execute();
            } catch (PDOException $e) {
                
            }
            $count++;
            $error = $stmt->errorInfo();
            ?>
            <details style="margin-left: 3em">
                <summary>Status: <?php echo ($error[0] === "00000" ? "Success" : "Error"); ?></summary>
                <pre><?php echo var_export($error, true); ?></pre>
            </details>
            <br>
<?php
        }
        echo "<p>Init complete, used approximately $count db calls.</p>";
    } else {
        echo "<p>Didn't find any files, please check the directory/directory contents/permissions (note files must end in .sql)</p>";
    }
    $db = null;
} catch (Exception $e) {
    echo $e->getMessage();
    exit("Something went wrong");
}