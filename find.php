<?php

/**
 * BIG WARNING!  Take a backup first, and carefully test the results of this code.
 * If you don't, and you ruin your data then you only have yourself to blame.
 * Seriously.  And if you're English is bad and you don't fully understand the
 * instructions then STOP.  Right there.  Yes.  Before you do any damage.
 *
 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK.  I/We accept no liability from its use.
*/

@include 'wp-config.php';

if (defined('DB_NAME')) {
	$database = array(
		'host'     => DB_HOST,
		'user'     => DB_USER,
		'name'     => DB_NAME,
		'password' => DB_PASSWORD,
	);
}else {
	$database = array(
		'host'     => 'localhost',
		'user'     => '',
		'name'     => '',
		'password' => '',
	);
}

/**
 * Text you want to find on the left
 * Replacement on the right.
 * Duplicate the '' => '', line for as many entries as you need
 * That's it! Load this script in a browser to run it.
*/
$find_replace = array(
	'' => '',
);

// Undo if needed
// -- Note: This may be A Bad Idea™
// -- e.g., {page_home_title} will always match to "Home", but "Home" may not always match to {page_home_title}, like one client: Home Matters
// $find_replace = array_flip( $find_replace );

#  ==========================
#	Output
#  ========================={
?>
	<html>
		<head>
			<style>
				body {font-family:"Panic Sans", Courier, monospace;}
				h1,h2,h3 {font-weight:normal;}
				.sql {display:block; margin-top:1em;margin-left:3em;margin-bottom:2em;background-color:#ffaaaa;}
				.report {margin:auto; text-align:center;background-color:#ccc;}
			</style>
		</head>
		<body>
			
			<?php new Storm_Find_Replace( $find_replace, $database ); ?>

		</body>
	</html>
<?php
	
#}end Output


class Storm_Find_Replace {

	/**
	 * This script is to solve the problem of doing database search and replace
	 * when developers have only gone and used the non-relational concept of
	 * serializing PHP arrays into single database columns.  It will search for all
	 * matching data on the database and change it, even if it's within a serialized
	 * PHP array.
 	 *
	 * The big problem with serialised arrays is that if you do a normal DB
	 * style search and replace the lengths get mucked up.  This search deals with
	 * the problem by unserializing and reserializing the entire contents of the
	 * database you're working on.  It then carries out a search and replace on the
	 * data it finds, and dumps it back to the database.  So far it appears to work
	 * very well.  It was coded for our WordPress work where we often have to move
	 * large databases across servers, but I designed it to work with any database.
	 * Biggest worry for you is that you may not want to do a search and replace on
	 * every damn table - well, if you want, simply add some exclusions in the table
	 * loop and you'll be fine.  If you don't know how, you possibly shouldn't be
	 * using this script anyway.
 	 *
	 * To use, simply configure the settings below and off you go.  I wouldn't
	 * expect the script to take more than a few seconds on most machines.
 	 *
	 * BIG WARNING!  Take a backup first, and carefully test the results of this code.
	 * If you don't, and you vape your data then you only have yourself to blame.
	 * Seriously.  And if you're English is bad and you don't fully understand the
	 * instructions then STOP.  Right there.  Yes.  Before you do any damage.
 	 *
	 * USE OF THIS SCRIPT IS ENTIRELY AT YOUR OWN RISK.  I/We accept no liability from its use.
 	 *
 	 * @license WTFPL: {@link http://sam.zoy.org/wtfpl/} (WARNING: it's a little rude, if you're sensitive)
	 * 	ie, do what ever you want with the code, and I take no responsibility for it OK?
	 * 	If you don't wish to take responsibility, hire me through Interconnect IT Ltd
	 * 	on +44 (0)151 709 7977 and we will do the work for you, but at a cost, minimum 1hr
 	 *
 	 * @author David Coveney of Interconnect IT Ltd (UK) -- Original version written 20090525
	 * 	{@link http://www.davesgonemental.com} or {@link http://www.interconnectit.com} or
	 * 	{@link http://spectacu.la}
 	 *
	 * @author moz667 at gmail.com
	 * 	recursive_array_replace posted at http://uk.php.net
	 * 	which saved me a little time - a perfect sample for me and seems to work in all cases.
	 *
	 * @author pdclark at brainstormmedia.com
	 *		Revisions, warnings, objectification
	 *
	 * @version 2.0
	 **/

	var $db;

	var $cid; // MySQL connection ID

	function __construct( $find_replace, $database ) {
		if ( strpos($_SERVER['DOCUMENT_ROOT'], '/Users/') !== false && empty($_GET['verify']) ) {
			?>
			<p>It looks like you are on a live server. Are you sure you want to continue?</p>
			<p><a href="<?php echo $_SERVER['REQUEST_URI'] ?>?verify=1">Yes, I've made a backup of this database</a>.</p>
			<?php
			exit;
		}

		$this->db = $database;

		set_time_limit(0);
		error_reporting(E_ERROR);

		foreach ($find_replace as $find => $replace) {

			echo "<h3>Find <strong>$find</strong>, replace with <strong>$replace</strong></h3>";
			echo $this->super_replace($find, $replace);

		}
		
		mysql_close($this->cid);
	}

	function super_replace($search_for, $replace_with) {
		$stimer = $this->timer_start();

		$tables_list = $this->get_tables();
		
		// Loop through the tables
		$count_items_changed = 0;
		$count_tables_list = mysql_num_rows($tables_list);
		while ($this_table = mysql_fetch_array($tables_list)) {

			$count_tables_checked++;

			$table = $this->get_table_schema($this_table);
			$out .= $table['name'];
			if ($count_tables_checked !== $count_tables_list) {
				$out .= ', ';
			}
			
			// now let's get the data and do search and replaces on it...
		    while ($row = mysql_fetch_array($table['data'])) {

				// Initialise the UPDATE string we're going to build
				// Only Update columns that require a change
				$need_to_update = false;
				$UPDATE_SQL = 'UPDATE '.$table['name']. ' SET ';
				$WHERE_SQL = ' WHERE ';

				$j = 0;
				foreach ($table['column_names'] as $current_column) {
					$j++;
					$count_items_checked++;
					
					$data_to_fix = $row[$current_column];
					$edited_data = $data_to_fix;            // set the same now - if they're different later we know we need to update

					$unserialized = unserialize($data_to_fix);  // unserialise - if false returned we don't try to process it as serialised

					if ($unserialized) {
						$this->recursive_array_replace($search_for, $replace_with, $unserialized);
						$edited_data = serialize($unserialized);

 					}else if (is_string($data_to_fix)) {
						$edited_data = str_replace($search_for,$replace_with,$data_to_fix) ;
					}

 					if ($data_to_fix != $edited_data) {   // If they're not the same, we need to add them to the update string
						$count_items_changed++;

						if ($need_to_update != false) {
							$UPDATE_SQL = $UPDATE_SQL.',';  // if this isn't our first time here, add a comma
						}

						$UPDATE_SQL = $UPDATE_SQL.' '.$table['name'].'.'.$current_column.' = "'.mysql_real_escape_string($edited_data).'"' ;
						$need_to_update = true; // only set if we need to update - avoids wasted UPDATE statements
					}

					if ($table['index'][$j]){
						$WHERE_SQL = $WHERE_SQL.$current_column.' = "'.$row[$current_column].'" AND ';
					}
				}

				if ($need_to_update) {
					$WHERE_SQL = substr($WHERE_SQL,0,-4); // strip off the excess AND - the easiest way to code this without extra flags, etc.

					$UPDATE_SQL = $UPDATE_SQL.$WHERE_SQL;
					if (!empty($UPDATE_SQL)) {
						$out .= '<p class="sql">'.strip_tags(substr($UPDATE_SQL, 0, 200)).'</p>';
					}
					$result = mysql_db_query($this->db['name'],$UPDATE_SQL,$this->cid);

 					if (!$result) {
						echo("ERROR: " . mysql_error() . "<br/>$UPDATE_SQL<br/>");
					} 

				} // end $need_to_update
		    } // end row loop
		} // End table loop
		
		// Report
		$out .=	'<p class="report">'
					.$count_tables_checked.' tables checked; '.$count_items_checked.' items checked; '.$count_items_changed.' items changed;<br/>'
					.$this->timer_out($stimer)
				.'</p>';
		
		return $out;
	}
	
	function timer_start() {
		$stimer = explode( ' ', microtime() );
		$stimer = $stimer[1] + $stimer[0];
		return $stimer;
	}
	
	function timer_end($stimer) {
		$etimer = explode( ' ', microtime() );
		$etimer = $etimer[1] + $etimer[0];
		return $etimer;
	}
	
	function timer_out($stimer) {
		$etimer = $this->timer_end($stimer);
		return sprintf( "Script timer: <b>%f</b> seconds.", ($etimer-$stimer) );
	}
	
	function db_connect() {
		$this->cid = mysql_connect($this->db['host'],$this->db['user'],$this->db['password']); 
		if (!$this->cid) { echo("Connecting to DB Error: " . mysql_error() . "<br/>"); }
	}
	
	function get_tables() {
		if (empty($this->cid)) { $this->db_connect(); }
		
		// Get Tables
		$SQL = "SHOW TABLES";
		$tables_list = mysql_db_query($this->db['name'], $SQL, $this->cid);
		if (!$tables_list) { echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>"); }
		
		return $tables_list;
	}
	
	function get_table_schema($this_table) {
		// Name
		$table['name'] = $this_table['Tables_in_'.$this->db['name']];

		// Columns names & indexes
		$SQL = 'DESCRIBE '.$table['name'] ;    // fetch the table description so we know what to do with it
	    $fields_list = mysql_db_query($this->db['name'], $SQL, $this->cid);
		$i = 0;
	    while ($field_rows = mysql_fetch_array($fields_list)) {
	        $table['column_names'][$i++] = $field_rows['Field'];

	        if ($field_rows['Key'] == 'PRI') {
				$table['index'][$i] = true ;
    		}
	    }
		
		// Data
		$SQL = 'SELECT * FROM '.$table['name'];     // fetch the table contents
	    $table['data'] = mysql_db_query($this->db['name'], $SQL, $this->cid);
	    if ( !$table['data'] ) {
			echo("ERROR: " . mysql_error() . "<br/>$SQL<br/>");
		}
		
		return $table;
	}
	

	function recursive_array_replace($find, $replace, &$data) {
    
	    if (is_array($data)) {
	        foreach ($data as $key => $value) {
	            if (is_array($value)) {
	                $this->recursive_array_replace($find, $replace, $data[$key]);
	            } else {
	                // have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
	                if (is_string($value)) $data[$key] = str_replace($find, $replace, $value);
	            }
	        }
	    } else {
	        if (is_string($data)) $data = str_replace($find, $replace, $data);
	    }
    
	}
}