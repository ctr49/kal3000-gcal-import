<?php

    echo "<ul>";
		require_once __DIR__ . '/../vendor/autoload.php';
		$cal = new \om\IcalParser();
		$results = $cal->parseFile(
			'https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics'
		);
		foreach ($cal->getSortedEvents() as $r) {
			echo sprintf('	<li>%s - %s</li>' . PHP_EOL, $r['DTSTART']->format('j.n.Y'), $r['SUMMARY']);
		}
    echo "</ul>";
  
?>


