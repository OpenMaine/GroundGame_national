<?php

	set_time_limit(0);
//	header('Content-Type: text/plain');
	error_reporting(E_ALL);
	ini_set("display_errors", 1);

	// echo "\nSchedule A records describe itemized receipts reported by a committee. This is where\nyou can look for individual contributors. If you are interested in\nindividual donors, `/schedules/schedule_a` will be the endpoint you use.\n\nOnce a person gives more than a total of $200, the donations of that person must be\nreported by committees that file F3, F3X and F3P forms.\n\nContributions $200 and under are not required to be itemized, but you can find the total\namount of these small donations by looking up the \"unitemized\" field in the `/reports`\nor `/totals` endpoints.\n\nWhen comparing the totals from reports to line items. the totals will not match unless you\nonly look at items where `\"is_individual\":true` since the same transaction is in the data\nmultiple ways to explain the way it may move though different committees as an earmark.\nSee the `is_individual` sql function within the migrations for more details.\n\nFor the Schedule A aggregates, such as by_occupation and by_state, include only unique individual\ncontributions. See below for full methodology.\n\n__Methodology for determining unique, individual contributions__\n\nFor receipts over $200 use FEC code line_number to identify individuals.\n\nThe line numbers that specify individuals that are automatically included:\n\nLine number with description\n    - 10 Contribution to Independent Expenditure-Only Committees (Super PACs),\n         Political Committees with non-contribution accounts (Hybrid PACs)\n         and nonfederal party \"soft money\" accounts (1991-2002)\n         from a person (individual, partnership, limited liability company,\n         corporation, labor organization, or any other organization or\n         group of persons)\n    - 15 Contribution to political committees (other than Super PACs\n         and Hybrid PACs) from an individual, partnership or\n         limited liability company\n    - 15E Earmarked contributions to political committees\n          (other than Super PACs and Hybrid PACs) from an individual,\n          partnership or limited liability company\n    - 15J Memo - Recipient committee's percentage of contribution\n          from an individual, partnership or limited liability\n          company given to joint fundraising committee\n    - 18J | Memo - Recipient committee's percentage of contribution\n          from a registered committee given to joint fundraising committee\n    - 30, 30T, 31, 31T, 32 Individual party codes\n\nFor receipts under $200:\nWe check the following codes and see if there is \"earmark\" (or a variation) in the `memo_text`\ndescription of the contribution.\n\nLine number with description\n    -11AI The itemized individual contributions from F3 schedule A\n    -12 Nonfederal other receipt - Levin Account (Line 2)\n    -17 Itemized individual contributions from Form 3P\n    -17A Itemized individual contributions from Form 3P\n    -18 Itemized individual contributions from Form 3P\n\nOf those transactions,[under $200, and having \"earmark\" in the memo text OR transactions having the codes 11A, 12, 17, 17A, or 18], we then want to exclude earmarks.\n\n\nAll receipt data is divided in two-year periods, called `two_year_transaction_period`, which\nis derived from the `report_year` submitted of the corresponding form. If no value is supplied, the results\nwill default to the most recent two-year period that is named after the ending,\neven-numbered year.\n\nDue to the large quantity of Schedule A filings, this endpoint is not paginated by\npage number. Instead, you can request the next page of results by adding the values in\nthe `last_indexes` object from `pagination` to the URL of your last request. For\nexample, when sorting by `contribution_receipt_date`, you might receive a page of\nresults with the following pagination information:\n\n```\npagination: {\n    pages: 2152643,\n    per_page: 20,\n    count: 43052850,\n    last_indexes: {\n        last_index: \"230880619\",\n        last_contribution_receipt_date: \"2014-01-01\"\n    }\n}\n```\n\nTo fetch the next page of sorted results, append `last_index=230880619` and\n`last_contribution_receipt_date=2014-01-01` to the URL.  We strongly advise paging through\nthese results by using sort indices (defaults to sort by contribution date), otherwise some resources may be\nunintentionally filtered out.  This resource uses keyset pagination to improve query performance and these indices\nare required to properly page through this large dataset.\n\nNote: because the Schedule A data includes many records, counts for\nlarge result sets are approximate; you will want to page through the records until no records are returned.\n";

	//exit();


	function formatNumber($num){
		$t = "$" . number_format($num, 2);
		if(explode('.', $t)[1] == '00') $t = explode('.', $t)[0];
		return $t;
	}

	function fetch_cfr($committee_id){

		$filePath = "data" . DIRECTORY_SEPARATOR . $committee_id . ".csv";

		if(!file_exists($filePath)) download_cfr($committee_id);
		$csv = file_get_contents($filePath);
		$raw = readFileAsCSV($csv);

		$transactions = array();
		foreach($raw as $t){
			$t['total_display'] = formatNumber($t['Total']);
			if($t['Note'] != ' ') $t['total_display'] = '<a title="' . $t['Note'] . '">* ' . $t['total_display'] . '</a>';

			$t['Total'] = (float) $t['Total'];

			$t['timestamp'] = strtotime($t["Date"]);
			$t['display_date'] = date("M j, Y", $t['timestamp']);

			$transactions[] = $t;
		}


		return $transactions;

	}

	function download_cfr($committee_id){

		$fields = array("contribution_receipt_date","contribution_receipt_amount","contributor_aggregate_ytd","contributor_first_name","contributor_last_name","contributor_employer","contributor_occupation","contributor_street_1","contributor_city","contributor_state", "sub_id");


		$params = array(
			"api_key" => "BvwzFKpIXvuYEWJkx36ZW5a2YuqcY73nqcnHF7iL",
			"two_year_transaction_period" => "2018",
			"per_page" => "100",
			"sort" => "-contribution_receipt_date",
			"committee_id" => $committee_id
		);

		$url = 'https://api.open.fec.gov/v1/schedules/schedule_a/?';


		$donations = array();
		$last_index = false;
		$total_received = 0;

		$total = 0;


		while(true){

			if($last_index) {
				$params['last_index'] = $last_index;
				$params['last_contribution_receipt_date'] = $last_date;
				
			}

			$strs = array();
			foreach($params as $k => $v) $strs[] = $k . '=' . $v;
			$api_url = $url . implode('&', $strs);

			$json = json_decode(file_get_contents($api_url));
		
			// echo "Received " . count($json -> results) . " transactions.\n";
			
			$total_received += count($json -> results);

			// if(count($json -> results) != 100) print_r($json);

			foreach($json -> results as $rawDonation){
				$d = array("Note" => " ");


				// REMOVE "CONDUIT" DUPLICATE RECORDS (ActBlue etc.)
				if($rawDonation -> contributor_occupation == "CONDUIT TOTAL LISTED IN AGG. FIELD") continue;


				// FOR NOW, ONLY COUNT NORMAL CONTRIBUTIONS
				$type = ($rawDonation -> line_number_label == "Contributions From Individuals/Persons Other Than Political Committees") ? 'normal' : 'special';
				if($type == 'special') continue;

				foreach($fields as $f) $d[$f] = $rawDonation -> $f;

				$d['contribution_receipt_date'] = explode('T', $d['contribution_receipt_date'])[0];

				$key = $d["contributor_first_name"] . ' ' . $d["contributor_last_name"] . ', ' . $d["contributor_city"];



				if(!isset($donations[$key])) $donations[$key] = $d;

				if($rawDonation -> fec_election_year != 2018){
					$note = '* ' . formatNumber($rawDonation -> contribution_receipt_amount) . ' - ' . $rawDonation -> receipt_type_full;
					if($donations[$key]['Note'] != ' ') $donations[$key]['Note'] .= "\n" . $note;
					else $donations[$key]['Note'] = $note;
				}

				
			}

			if(count($json -> results) == 0) break;
			$last_index = $json -> pagination -> last_indexes -> last_index;
			$last_date = $json -> pagination -> last_indexes -> last_contribution_receipt_date;

			// print_r($json);
			// exit();
			
		}

		



		//echo $total_received . " received. " . count($donations) . " good donations. " . formatNumber($total) . " recorded.\n";

		//print_r($donations);

		$fields = array(
			"contribution_receipt_date" => "Date",
			"contribution_receipt_amount" => "Amount",
			"contributor_aggregate_ytd" => "Total",
			"contributor_first_name" => "First Name",
			"contributor_last_name" => "Last Name",
			"contributor_employer" => "Employer",
			"contributor_occupation" => "Occupation",
			"contributor_street_1" => "Street",
			"contributor_city" => "City",
			"contributor_state" => "State",
			"sub_id" => "#",
			"Note" => "Note"
		);


		$fileName = "data" . DIRECTORY_SEPARATOR . $committee_id . ".csv";

	    $fh = fopen($fileName, 'w');

	    fputcsv($fh, $fields);

	    $newTotal = 0;
	    foreach($donations as $r){

	    	$row = array();
	    	foreach($fields as $k => $f) $row[$f] = $r[$k];

	      fputcsv($fh, $row);
	      $newTotal += $r["contributor_aggregate_ytd"];
	    }

	    
	 }



function readFileAsCSV($csv, $unique_fields = array(), $primaryKey = ''){
    $data = array();
    $uniques = array();

    $file_str = $csv;
    $rows = explode("\n", $file_str);

    foreach($rows as $k => $row){
      $fields = str_getcsv(trim($row));

      // get field names from the first row
      if($k == 0) {
        foreach($fields as $f) $headers[] = trim($f);
        continue;
      }

      if(count($fields) == 1) continue;

      $rowData = array_combine($headers, $fields);


      // compile a list of unique values ()
      foreach($unique_fields as $u){
        if($rowData[$u] != ''){
          $uniques[$u][$rowData[$u]] = $rowData[$primaryKey];
        }


      }

      $data[] = $rowData;

    }

    if(count($uniques) > 0) print_r($uniques);

    return $data;
  }




