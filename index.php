<?php

include('configuration.php');
include(PATH.'/Parsedown.php');

if(!file_exists(PATH.'/monitors.json')) die('<h1>Missing monitors.json</h1><p>You’ll need a <code>monitors.json</code> file in the same location where this script exists. See <a href="https://github.com/neatnik/website-monitor">this page</a> for more information.</p>');

if(!file_exists(PATH.'/monitors')) die('<h1>Missing monitors directory</h1><p>You’ll need a <code>monitors</code> directory in the same location where this script exists. See <a href="https://github.com/neatnik/website-monitor">this page</a> for more information.</p>');

if(!file_exists(PATH.'/incidents')) die('<h1>Missing incidents directory</h1><p>You’ll need an <code>incidents</code> directory in the same location where this script exists. See <a href="https://github.com/neatnik/website-monitor">this page</a> for more information.</p>');

if(!is_writable(PATH.'/monitors')) die('<h1>Monitors directory is not writable</h1><p>Your <code>monitors</code> directory is not writable. Please adjust its permissions and try again. See <a href="https://github.com/neatnik/website-monitor">this page</a> for more information.</p>');

?><!DOCTYPE html>
<html lang="en">
<head>
<title>Website Monitor</title>
<meta charset="utf-8">
<meta name="theme-color" content="#212529">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.8.2/dist/chart.min.js" crossorigin="anonymous"></script>
</head>
<body>

<main>

<h1>Website Monitor</h1>

<?php

$incidents = array();
foreach(glob(PATH.'/incidents/*.md') as $incident_file) {
	$incident_filename = str_replace(PATH.'/incidents/', '', $incident_file);
	if(substr($incident_filename, 0, 6) == 'alert_') $incidents[$incident_filename] = 'alert';
	else if(substr($incident_filename, 0, 7) == 'notice_') $incidents[$incident_filename] = 'notice';
	else $incidents['post_'.$incident_filename] = 'post';
}

ksort($incidents);
foreach($incidents as $incident_filename => $class) {
	if(substr($incident_filename, 0, 5) == 'post_') $incident_filename = substr($incident_filename, 5);
	$Parsedown = new Parsedown();
	echo '<div class="incident '.$class.'">';
	echo $Parsedown->text(file_get_contents(PATH.'/incidents/'.$incident_filename));
	echo '</div>';
}

$monitors = json_decode(file_get_contents(PATH.'/monitors.json'));

$i = 0;

foreach($monitors as $monitor => $url) {
	
	$log = json_decode(file_get_contents(PATH.'/monitors/'.$monitor), TRUE);
	$last = $log[array_key_last($log)];
	
	if(is_numeric($last['response']) && $last['response'] >= 200 && $last['response'] < 300) {
		$last = ' <span class="status">HTTP/1.1 '.$last['response'].'</span>';
		$class = 'good';
		$graph_color = '#51cf66';
	}
	else {
		$last = ' <span class="status">HTTP/1.1 '.$last['response'].'</span>';
		$class = 'bad';
		$graph_color = '#fa5252';
	}
	
	echo '<div class="item"><h2><span class="'.$class.'">⬤</span> '.$monitor.$last.'</h2>';
	
	if(file_exists(PATH.'/updates/'.$monitor.'.md')) {
		$Parsedown = new Parsedown();
		echo $Parsedown->text(file_get_contents(PATH.'/updates/'.$monitor.'.md'));
	}
	
	$labels = array();
	$data = array();
	
	$const = 'ctx'.$i;
	$const_chart = $const.'_'.$const;
	
	echo '<div class="bloops">';
	
	// do we need bloop padding?
	if(count($log) < 60) {
		$diff = 59 - count($log);
		
		for ($x = 0; $x <= $diff; $x++) {
			echo '<span class="bloop" title="Not monitored"></span>';
		}
	}
	
	foreach($log as $arr) {
		
		$labels[] = "'".date("H:i", $arr['timestamp'])."'";
		
		$data[] = @$arr['time'];
		
		if(@$arr['time'] > 0) {
			if(@$arr['response'] >= 200 && @$arr['response'] < 300) {
				echo '<span class="bloop good" data-status="Up" data-time="'.date("H:i", $arr['timestamp']).'" data-response="'.$arr['response'].'" data-ms="'.$arr['time'].'" data-status="Up at '.date("H:i", $arr['timestamp']).'" title="Up at '.date("H:i", $arr['timestamp']).' ('.$arr['time'].' ms)"></span>';
			} else {
				echo '<span class="bloop bad" data-status="Down" data-time="'.date("H:i", $arr['timestamp']).'" data-response="'.$arr['response'].'" data-ms="'.$arr['time'].'" data-status="Down at '.date("H:i", $arr['timestamp']).'" title="Down at '.date("H:i", $arr['timestamp']).' ('.$arr['time'].' ms)"></span>';
			}
		}
		else {
			echo '<span class="bloop bad"></span>';
		}
	}
	
	$min = min($data);
	$max = max($data);
	
	$diff = $max - $min;
	
	$max += ($diff / 3);
	$min -= ($diff / 3);
	
	if($min < 0) $min = 0;
	
	echo '</div>';
	
	$chart_id = str_replace('.', '-', $monitor);
	
	$labels = implode(', ', $labels);
	$data = implode(', ', $data);
	
	$out = <<<EOD
<canvas id="$chart_id" width="300" height="100"></canvas>
<script>
const $const = document.getElementById('$chart_id').getContext('2d');
const $const_chart = new Chart($const, {
	type: 'line',
	data: {
		labels: [$labels],
		datasets: [{
			label: 'response time',
			data: [$data],
			backgroundColor: '$graph_color',
			borderColor: '$graph_color',
			borderWidth: 2,
			tension: .4
		}]
	},
	options: {
		scales: {
			y: {
				beginAtZero: false,
				min: $min,
				max: $max,
				grid: { display: false, drawBorder: false }
			},
			x: {
				grid: { display: false, drawBorder: false }
			}
		},
		plugins: {
			legend: {
				display: false
			},
			tooltip: {
				callbacks: {
					label: function(context) {
						return context.parsed.y + ' ms';
					}
				}
			}
		}
	}
});
</script>
EOD;
	echo $out;
	echo '</div>';
	$i++;
}

?>

<footer>

<p>Website Monitor is an open source project inspired by <a href="https://broke.lol">broke.lol</a>. <a href="https://github.com/neatnik/website-monitor">Download it on GitHub</a>.</p>

</footer>

</main>
</body>
</html>