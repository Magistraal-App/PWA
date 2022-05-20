<?php 
    namespace Magistraal\Debug;
    
    define('LOG_ITEM_LABEL_WIDTH', 48);
    define('LOG_ITEM_COLUMN_WIDTH', 28);

    function print_value($label, $level = 1, $value) {      
        if(\Magistraal\Config\get('debugging') !== true) {
            return;
        }

        // Print indentation
        echo(str_repeat('    ', $level));

        // Print label and value
        echo(
            str_pad($label, LOG_ITEM_LABEL_WIDTH).
            str_pad($value, LOG_ITEM_COLUMN_WIDTH, ' ', STR_PAD_LEFT)."\n"
        );
    }

    function print_heading($label, $level = 1, $columns = 1) {
        if(\Magistraal\Config\get('debugging') !== true) {
            return;
        }

        $total_length = LOG_ITEM_LABEL_WIDTH + LOG_ITEM_COLUMN_WIDTH * $columns;

        // Print indentation
        echo(str_repeat('    ', $level));

        // Print heading
        $padding_length = ($total_length-strlen($label))/2;
        $padding_length1 = floor($padding_length);
        $padding_length2 = (is_integer($padding_length)) ? $padding_length : $padding_length+1;
        echo(str_repeat('-', $padding_length1).$label.str_repeat('-', $padding_length2)."\n");
        return;
    }

    function print_timing($label, $level = 1, $start = null, $end = null, $total_start = 1, $total_end = 2) {
        if(\Magistraal\Config\get('debugging') !== true) {
            return;
        }
        
        // Print indentation
        echo(str_repeat('    ', $level));

        if(!isset($end)) {

        }

        if(!isset($start)) {
            // Print start as raw number
            echo($start);
            return;
        }

        // Print timing
        echo(
            str_pad($label.':', LOG_ITEM_LABEL_WIDTH).
            str_pad(round($end-$start), LOG_ITEM_COLUMN_WIDTH - 3, ' ', STR_PAD_LEFT).' ms'.
            str_pad(number_format(($end-$start) / ($total_end-$total_start) * 100, 1, '.', ''), LOG_ITEM_COLUMN_WIDTH - 2, ' ', STR_PAD_LEFT).' %'.
        "\n");
    }

    function get_timestamp() {
        return round(microtime(true) * 1000, 3);
    }
?>