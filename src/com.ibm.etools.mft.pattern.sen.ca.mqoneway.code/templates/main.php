<?php
	
	$requestLoggingRequired = $_MB['PP']['pp9'] == 'true';
	$responseLoggingRequired = $_MB['PP']['pp10'] == 'true';

    if ($requestLoggingRequired || $responseLoggingRequired) {
         mb_pattern_run_template("ServiceAccessMQone-way", "mqsi/Log.esql.php", "mqsi/Log.esql");
    }
?>
