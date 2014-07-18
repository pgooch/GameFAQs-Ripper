<?php
/*
	This uses the cover_grid class to combine the images into a single large one
*/

// First things first we need to load up that class
require_once('./cover_grid.php');
$cg = new cover_grid(); // Options are irrelevant because generation doesn't use them.

// This will keep track of all the images so we don't have to re-loop through the directory
$all_images = array();

// Create an image for every year