<?php
/*
	This uses the cover_grid class to scrape the image
*/

// First things first we need to load up that class
require_once('./cover_grid.php');

// For this I'm going to loop through all the NES release years
for($date=1980;$date<=2015;$date++){
	$cg = new cover_grid(array('date'=>$date));
	$cg->scrape('./NES Games by Year (2)/'.$date);
}
?>