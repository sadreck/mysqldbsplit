<?php

ini_set('memory_limit', -1);
set_time_limit(0);

date_default_timezone_set('Europe/London');

$db = new db_split();
$db->run();

class db_split
{
    private $_TABLE_BEGIN = '-- Table structure for table';

    private $_data = null;
    private $_options = array(
        'in:',
        /* Input SQL dump. */
        'out:',
        /* Output directory to store results. */
        'list::',
        /* List all tables from SQL dump. Ignores all other options. */
        'force::',
        /* Force create output directory it does not exist. */
        'postfix-time:',
        /* Postfix for table export - MUST be in double quotes and in valid date() format -> http://php.net/manual/en/function.date.php */
        'postfix-name:',
        /* Postfix name for table export */
        'ignore:',
        /* Which tables to ignore. Comma separated */
        'only:',
        /* Specific tables to export. This will override the --ignore option (if set) */
    );

    private $_extension = '.sql';

    private $_time_start = 0;
    private $_statements = '';

    public function __construct()
    {
        $this->_data = new stdClass();
        $this->_time_start = microtime(true);
    }

    public function show_help()
    {
        echo "MySQL Database Dump Splitter v0.0.1\n";
        echo "Author: Pavel Tsakalidis [ http://pavel.gr | p@vel.gr ]\n\n";
        echo "Usage:\n\n";

        echo "--in [sql dump] --out [output folder] --force --postfix-time \"d-m-Y\" --postfix-name [some text] --ignore [ignore-table1,ignore-table2,...] --only [export-table1,export-table2,...] --list\n\n";

        echo "--in\t\tMySQL dump file.\n";
        echo "--out\t\tOutput folder to store individual sql files.\n";
        echo "--force\t\tIf --out directory does not exist, create it.\n";
        echo "--postfix-time\tPHP date() format string to be appended to table filename.\n";
        echo "--postfix-name\tAny text you want to be appended to table filename.\n";
        echo "--ignore\tAny tables you want to ignore, comma separated.\n";
        echo "--only\t\tSpecific tables you want to export. Overrides --ignore option.\n";
        echo "--list\t\tOnly list tables. Will ignore every option except --in.\n";

        echo "\nExamples:\n\n";
        echo "--in /tmp/data.sql --out /tmp/tables --force --only table1,table2\n";
        echo "--in /tmp/data.sql --list\n";
        echo "--in /tmp/data.sql --out /tmp/tables --postfix-time \"d-m-Y_H-i\" --postfix-text myexporttext\n";
    }

    public function run()
    {
        $this->parse_arguments();

        $handle = fopen($this->_data->dump, 'r');
        if (!$handle) {
            $this->abort('Fatal error: Could not open file. Check file permissions.');
        }

        $tables = array();

        $in_table = false;
        $table = ''; // This holds the current table's name.
        $table_path = ''; // This holds the path of the current table.
        $length = strlen($this->_TABLE_BEGIN); // I store the size for speed optimisation

        $h = null; // Handle to the output file.

        $no_table_yet = true; // This helps gather the MySQL preparation statements that are located at the beginning of the file.

        while (($line = fgets($handle)) !== false) {
            if (substr($line, 0, $length) == $this->_TABLE_BEGIN) {
                // Stop gathering data.
                $no_table_yet = false;

                // We just detected a beginning of a table.
                $table_name = $this->get_table_name($line);

                // If there's a --list option then ignore everything else and just add the table name to the table's list.
                if ($this->_data->list) {
                    array_push($tables, $table_name);
                } else {
                    // Is there any current table file that is being written to?
                    if ($in_table) {
                        if ($h) {
                            @fclose($h);
                        }
                        $in_table = false;
                    }

                    // Are there specific tables to export or ignore?
                    if (count($this->_data->specific) > 0) {
                        if (!in_array($table_name, $this->_data->specific)) {
                            continue;
                        }
                    } else {
                        if (count($this->_data->ignore) > 0) {
                            if (in_array($table_name, $this->_data->ignore)) {
                                continue;
                            }
                        }
                    }

                    // Compose the table's name.
                    $table = $table_name;
                    if (!empty($this->_data->postfix_name)) {
                        $table .= '-' . $this->_data->postfix_name;
                    }
                    if (!empty($this->_data->postfix_time)) {
                        $table .= '-' . $this->_data->postfix_time;
                    }
                    $table .= $this->_extension;

                    $table_path = $this->_data->dir . $table;

                    // Open the new file.
                    $h = fopen($table_path, 'w');
                    if (!$h) {
                        $this->abort('Fatal error: Could not create output file for table -> ' . $table);
                    }

                    // Write the preparation statements
                    $this->write_data($h, $this->_statements, $table_path);

                    $in_table = true;
                }
            }

            if ($in_table) {
                $this->write_data($h, $line, $table_path);
            } else {
                if ($no_table_yet) {
                    $this->_statements .= $line;
                }
            }
        }

        // Have we left any file open?
        if ($h) {
            @fclose($h);
        }

        fclose($handle);

        // Display tables if it was requested.
        if ($this->_data->list) {
            foreach ($tables as $table) {
                echo $table, "\n";
            }
        }

        echo 'Finished in ' . number_format(microtime(true) - $this->_time_start, 2) . ' seconds', "\n";
    }

    private function write_data($h, $data, $table_path)
    {
        if (fwrite($h, $data) === false) {
            fclose($h);
            $this->abort('Fatal error while writing to file: ' . $table_path);
        }
    }

    private function get_table_name($line)
    {
        $name = str_replace(
            array(
                $this->_TABLE_BEGIN,
                '`',
                ' ',
                "\r",
                "\n"
            ),
            "",
            $line
        );
        return $name;
    }

    private function parse_arguments()
    {
        $o = getopt('', $this->_options);

        // Check input SQL dump
        if (!isset($o['in']) || empty($o['in']) || !file_exists($o['in'])) {
            $this->abort('Fatal error: No input file specified or file does not exist.', true);
        }
        $this->_data->dump = $o['in'];

        // Check if only a list of the tables is requested
        $this->_data->list = isset($o['list']);

        // Check if there is the force directive
        $this->_data->force = isset($o['force']);

        // Check output directory
        if (isset($o['out']) && !empty($o['out'])) {
            if (!is_dir($o['out'])) {
                if (!$this->_data->force) {
                    $this->abort(
                        'Fatal error: Output directory does not exist and the --force option was not used.',
                        true
                    );
                }

                @mkdir($o['out'], 0777, true);
                if (!is_dir($o['out'])) {
                    $this->abort('Fatal error: Could not create output directory. Check running permissions.');
                }
            }
            $this->_data->dir = $o['out'];

            if (substr($this->_data->dir, -1) != DIRECTORY_SEPARATOR) {
                $this->_data->dir .= DIRECTORY_SEPARATOR;
            }
        }

        // Check postfix-name
        $this->_data->postfix_name = isset($o['postfix-name']) ? trim($o['postfix-name']) : '';

        // Check postfix-time
        $this->_data->postfix_time = isset($o['postfix-time']) ? date(trim($o['postfix-time']), time()) : '';

        // Export specific tables?
        $this->_data->specific = isset($o['only']) ? explode(',', $o['only']) : array();

        if (count($this->_data->specific) == 0) {
            // Are there any tables to ignore?
            $this->_data->ignore = isset($o['ignore']) ? explode(',', $o['ignore']) : array();
        } else {
            $this->_data->ignore = array();
        }
    }

    private function abort($message, $show_help = false)
    {
        if ($show_help) {
            $this->show_help();
            echo "\n\n";
        }
        echo $message, "\n";
        die();
    }
}