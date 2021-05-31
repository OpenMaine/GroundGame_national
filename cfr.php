<?php

	// some weird things with imported data
	error_reporting(E_ERROR | E_PARSE);



	if(!isset($_GET['id'])) exit("Please provide a federal committee id.");
	$committee_id = $_GET['id'];

	include('fetch_cfr.php');
	extract(fetch_cfr($committee_id));


	$headers = array('Date', 'Total', 'First Name', 'Last Name', 'Employer', 'Occupation', 'City', 'State', 'Street');
	$fields = array('display_date', 'total_display', 'First Name', 'Last Name', 'Employer', 'Occupation', 'City', 'State', 'Street');



	// foreach($result as $r){
	// 	$t = array();
	// 	foreach($dictionary as $k => $v) $t[$k] = $r -> $v;
	// 	$t['timestamp'] = strtotime($t["date"]);

	// 	$t['date'] = date("M j, Y", $t['timestamp']);

	// 	$t['amount_display'] = "$" . number_format($t['amount'], 2);

	// 	if(explode('.', $t['amount_display'])[1] == '00') $t['amount_display'] = explode('.', $t['amount_display'])[0];

	// 	$transactions[] = $t;

	// 	$total += $t['amount'];
	// }

//print_r($transactions);



?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Contributions to <?php echo $committee_id; ?></title>
	<style>
		body { font-family: sans-serif; margin: 30px;}
		table { border-collapse: collapse; width: 1200px;}
		th 		{ text-align: left; font-size: 13px; }
		td 		{ border: solid 1px #ccc; padding: 5px; font-size: 12px; line-height: 18px}
		tbody tr:hover { background: #333; color: white; } 	
		tr th:hover { cursor: pointer; text-decoration: underline;  }	
		input { display: block; width: 250px; height: 40px; line-height: 40px; font-size: 16px; border: solid 1px #ccc; margin: 20px 0; padding: 0 8px;}
	</style>

	<!-- JS LIBRARIES: jQuery -->
	<script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>

	<script>
		var transactions = <?php echo json_encode($transactions); ?>;
		transactions.forEach(function(t){
			t.display = true;
		});

		var fields = <?php echo json_encode($fields); ?>;
		var headers = <?php echo json_encode($headers); ?>

		var orders = {};
		headers.forEach(function(f){ orders[f] = 1; })
		orders.amount = 1;
		orders.timestamp = 1;

		console.log(orders)

		// (RE)LOAD TABLE
		function loadTable(){
			var html = '';
			transactions.forEach(function(t){
				if(t.display){
					html += '<tr>';
					fields.forEach(function(f){ html += '<td>' + t[f] + '</td>'; })
					html += '</tr>';
				}
			})
			$('#tbody').html(html);
		}


		// SORT TABLE
		function sortBy(field){

			console.log('trying to sort by ' + field);

			console.log(transactions)

			if(field == 'Date') field = "timestamp";

			if(orders[field] == 1){
				transactions.sort(function(a, b){ return a[field] < b[field]; });				
			}
			else {
				transactions.sort(function(a, b){ return a[field] > b[field]; });
			}
			orders[field] = -1 * orders[field];
			loadTable();
		}


		// FILTER TABLE BY SEARCH STRING
		function filter(search_str){
			transactions.forEach(function(t){
				t.display = false;
				fields.forEach(function(f){
					if(t[f] && t[f].toLowerCase().indexOf(search_str.toLowerCase()) != -1) t.display = true;
				})
			})
			loadTable();
		}

		$(function(){
			// loadTable();
		})
	</script>

</head>
<body>

	<div id="committee" style="font-size: 12px; line-height: 18px;">
		<b style="font-size: 18px;"><?php echo $committee['Candidate Name']; ?></b>
		<br /><?php echo substr($committee['Party'], 0, 1); ?> - <?php echo $committee['Status']; ?>
		<br /><?php echo $committee['Committee Name']; ?> - <?php echo $committee['City']; ?>, <?php echo $committee['State']; ?>
		<br />Received: $<?php echo number_format($committee['Received']); ?>
		<br />Spent: $<?php echo number_format($committee['Spent']); ?>
	</div>

	<input placeholder="Search..." onkeyup="filter(this.value)">

	<table>
		<thead>
			<tr>
				<?php
					foreach($headers as $h){
						echo '<th onclick="sortBy(\'' . $h . '\');">' . $h . '</th>';
					}
				?>
			</tr>
		</thead>
		<tbody id="tbody">
			<?php 
				foreach($transactions as $t){
					echo '<tr>';						
						foreach($fields as $f) echo '<td>' . $t[$f] . '</td>';
					echo '</tr>';
				}
			?>
		</tbody>
</body>
</html>

