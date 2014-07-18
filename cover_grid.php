<?php
/* 
	Cover Grid

	This class has two different functions
		1. Scrape game covers, front and back, from gamefaqs and save them into specified directories (along with a text list of the games it scraped)
		2. Take the scraped images and combine them into a single larger grid image.
	So basically nothing of useful. These two functions are ran separately so that repeated unnecessary calls to the gamefaqs server are not fired when 
	working on the image generation portion of the script.

	Last Revision July 16, Phillip Gooch (phillip.gooch@gmail.com)
*/
class cover_grid{
	// Some setup items, some of these can be overridden from the constructor.
	var $search_url = 'http://www.gamefaqs.com/search/index.html';
	var $search_settings = array( // This defaults to the first year of NES games in North America
		'platform'	=> '41',	// System, 41=NES
		'game' 		=> '',		//
		'contrib' 	=> '',		//
		'rating' 	=> '',		//
		'genre' 	=> '',		//
		'region'	=> '1',		// Released Region, 1=NA
		'date' 		=> '',	// Year of Release
		'developer' => '',		//
		'publisher' => '',		//
		'dist' 		=> '1',		// Physical Releases Only (not an issue in 1985)
		'sort' 		=> '',		//
		'link' 		=> '',		//
		'res' 		=> '',		//
		'title' 	=> '',		//
		'adv'		=> '1',		// Is advanced search (set to 1)
	);
	var $region_code = 'US'; // The county code thats displayed for release info
	var $filtered_titles = array('NES'); // An array of titles to not get covers of (primary used to filter the console itself)
	var $page_limitor = 100; // Limits the number of pages it will scrape.
	var $games = array(); // This is used several times containing a list of games and some data that is relevant at the time.
	var $img_border = 0; // The size of the blank border around the generated grid images in px
	var $img_resize_fuzzyness = 0.1; // the amount of distortion allowed when resizing images, if it's greater than this it will resize directly and be centered.
	var $save_location = ''; // The location where the images and list will be saved.

	// The constructor allows you to pass a new set of search settings and a new region code to the script, overriding the existing if passed
	function __construct($search_settings=array(),$region_code=''){
		if(count($search_settings)>0){
			foreach($search_settings as $k => $v){
				if($v!=''){
					$this->search_settings[$k] = $v;
				}
			}
		}
		if($region_code!=''){
			$this->region_code = $region_code;
		}
	}

	// Star the scrape, this calls a couple other functions in the process
	function scrape($save_location=''){
		// get the initial URL and start the page scraper
		$url = $this->search_url.'?'.http_build_query($this->search_settings);
		$this->games = $this->scrape_search_page($url);
		// Check the games against the filter
		foreach($this->filtered_titles as $n => $title){
			if(isset($this->games[$title])){
				unset($this->games[$title]);
			}
		}
		// Now if we actually have a game lets continue with creating the directory and getting those images
		if(count($this->games)>0){
			// Lets make sure we have a valid save location before doing anything, make one up if we have to
			if($save_location==''){
				$save_location = './scrape_'.time().'/';
			}
			$dir_path = explode('/',$save_location.'/');
			$previous_path = './';
			foreach($dir_path as $n => $path){
				if(substr($path,0,1)!='.'&&$path!=''){ // your just referencing yourself arn't you
					if(!is_dir($previous_path.$path.'/')){
						if(@!mkdir($previous_path.$path.'/')){
							die('Unable to create missing save directory at "'.$previous_path.'/'.$path.'".');
						}
					}
					$previous_path .= $path.'/';
				}
			}
			$this->save_location = $save_location;
			// Pull the data page and get the thumb urls
			foreach($this->games as $title => $data_url){
				$this->scrape_data_page($title,$data_url);
			}

			// Download the game covers, front and back (if they can be found)
			foreach($this->games as $title => $data_url){
				$this->scrape_box_art($title,$data_url,$save_location);
			}
			// Finally, were going to save a list of the games to a text file for future use. (also output so we see something)
			$this->save_scraped_list();
		}
	}

	// This is what does the search page scraping, it checks for a next link is recursive is necessary.
	function scrape_search_page($url,$page=0,$results=array(),$processed=0){
		// Get the page
		$scrape = file_get_contents($url.'&page='.$page);
		// Find all the game links on that page
		preg_match_all('~<td class="rtitle">[^<]+<a href="([^"]+)"[^>]+>([^<]+)</a></td>~s',$scrape,$games);
		foreach($games[1] as $i => $data){
			$results[htmlspecialchars_decode($games[2][$i],ENT_QUOTES)] = 'http://www.gamefaqs.com'.$data.'/data';// That final /data puts it on the release page
		}
		// Check to see if there is a next button.
		if(preg_match('~<ul class="paginate">.+Next~',$scrape)>0 && $processed<=$this->page_limitor){ // PHP has a function loop limit of 100, but it's unlikely a single search will hit it
			$page++;
			$processed++;
			$results = $this->scrape_search_page($url,$page,$results,$processed);
		}
		// Return the results
		return $results;
	}

	// This will load up the data page and find the appropriate thumbnail image for the desired region (using region code). 
	// From the thumb we can determine what the front and back cover image urls would be.
	function scrape_data_page($title,$url){
		// Get the page, then the table rows
		$scrape = file_get_contents($url);
		preg_match_all('~<tr>.+?</tr>~',$scrape,$rows);
		// To find the row with the image we need to look for the one after it containing the region code
		$target_row = -1;
		foreach($rows[0] as $row => $html){
			if(substr($html,24,2)==$this->region_code){
				// So this is a little tricky, you need to also check if the line has the date in it otherwise legitimate re-releases will pull the 
				// originals box art. Date formats appears to be full year or XX/XX/YY depending on how exact the date is
				if(stripos($html,(string)$this->search_settings['date'])!==false || stripos($html,'/'.substr($this->search_settings['date'],2))!==false ){
					$target_row = $row-1;
				}
			}
		}
		// Once we know the row to look at we can get the thumb, from there we will modify for full
		if($target_row<0){ // Ok, so we don't know...
			$this->games[$title] = 'No '.$this->region_code.' Release Found';
		}else{
			preg_match_all('~<img.+?src="([^"]+)"~',$rows[0][$target_row],$link);
			if(isset($link[1][0])){
				$this->games[$title] = $link[1][0];
			}else{
				$this->games[$title] = 'No Thumbnail Found';// Were going to check for blanks later...
			}
		}
	}

	// Knowing the way the images are stored in their system we can use the thumbnail to check and save the front and back box art.
	function scrape_box_art($title,$thumb){
		// Now we do the final loop, looking for the front and back box-art for each game, and downloading it into a specified folder and save the list in a txt file
		$sides = array('front','back');
		if($thumb!='No '.$this->region_code.' Release Found' && $thumb!='No Thumbnail Found'){
			foreach($sides as $n => $side){
				$image = @file_get_contents(str_ireplace('_thumb.','_'.$side.'.',$thumb));
				if(strlen($image)>0){
					file_put_contents(rtrim($this->save_location,'/').'/'.$this->filename_title_clean($title).'.'.$side.'.jpg',$image);
				}
			}
		}else{
			echo $title.': '.$thumb.'<br/>';
		}
	}

	// Save the list of games we have as well as display it so there is something showing
	function save_scraped_list(){
		$list = '';
		foreach($this->games as $title => $garbage){
			$list .= $title."\n";
		}
		file_put_contents(rtrim($this->save_location,'/').'/_list.txt',$list);
		echo nl2br($list);
	}

	// This is the master class to generate images based on the platform, year, and region given. Pass a blank to grab everything, the sides array 
	// contains what sides of the box you want to include in the image. This returns an array with every possible image, but it does not check if 
	// the image exists, that'll be done on the generation end of things
	function get_possible_images($dir='',$sides=array('front','back')){
		if(is_file($dir.'_list.txt')){
			$games = explode("\n",file_get_contents($dir.'_list.txt'));
			// Generate the potential images
			$images = array();
			foreach($games as $n => $title){
				foreach($sides as $n => $side){
					$images[] = $dir.$this->filename_title_clean($title).'.'.$side.'.jpg';
				}
			}
			// return the list (minus the blank one at the end)
			unset($images[count($images)-1]);
			return $images;
		}
	}

	// This will build the actual image grid.
	function build_grid($images,$save_dir='_generated_image',$cols=0,$img_x=215,$img_y=303){
		// We obviously only need to do this is the save directory is there (were not going to bother making it this time)
		if(is_dir($save_dir)){
			// First lets get some more ram and exec time up in here, were going to need it
			ini_set('memory_limit','1024M');
			set_time_limit(600);// 10 minutes should cover it
			// If the number of cols is 0 were going to try and make a square
			if($cols==0){
				echo count($images).' - '.sqrt(count($images)).' ( '.$img_y.' / '.$img_x.' )';
				$cols = floor( sqrt(count($images) * ($img_y/$img_x)) );
			}
			// Determine how big the image is going to need (including image border).
			$image_w = ($cols*$img_x)+($this->img_border*2);
			$image_h = (ceil(count($images)/$cols)*$img_y)+($this->img_border*2);
			// Create the image and fill the bg
			$image = imagecreatetruecolor($image_w,$image_h);
			// The following was how I was going to do the BG, but the images are to large in PNG (using the imgur limit of 20mb as a baseline)
			#$bg=imagecolorallocatealpha($image,0,255,0,127);
			#imagecolortransparent($image,$bg);
			#$extension = 'png';
			$bg=imagecolorallocate($image,24,24,24);
			$extension = 'jpg';
			imagefill($image,1,1,$bg);
			// Set the starting XY and start looping through all the possible images
			$x=$this->img_border;
			$y=$this->img_border;
			$c=1;// this is the current column position
			$r=1;// this is used just for the possible problem output
			$potential_problems = '';// displayed in the browser after generation
			foreach($images as $n => $box){
				if(is_file($box)){
					// Load up the image.
					$box = imagecreatefromstring(file_get_contents($box));
					// Do the math to determine what we want to do (resize and distort, or resize and center)
					$x_distortion = abs(((($img_x/imagesx($box))*imagesy($box))/$img_y)-1);
					$y_distortion = abs(((($img_y/imagesy($box))*imagesx($box))/$img_x)-1);
					if($x_distortion>$this->img_resize_fuzzyness || $y_distortion>$this->img_resize_fuzzyness){
						$scale = min($img_x/imagesx($box),$img_y/imagesy($box));
						$resize_x = round($scale*imagesx($box));
						$resize_y = round($scale*imagesy($box));
						$offset_x = round(($img_x-$resize_x)/2);
						$offset_y = floor(($img_y-$resize_y)/2);
						$potential_problems .= 'Row '.$r.', Col '.$c.' : Image outside of resize fuzzyness threshold.<br/>';
					}else{
						// It's within the fuzzyness factor, so were going to simply resize it and let it be
						$resize_x = $img_x;
						$resize_y = $img_y;
						$offset_x = 0;
						$offset_y = 0;
					}
					// copy the box image into the main one.
					imagecopyresampled($image,$box,$x+$offset_x,$y+$offset_y,0,0,$resize_x,$resize_y,imagesx($box),imagesy($box));
				}else{
					$potential_problems .= 'Row '.$r.', Col '.$c.' : Could not find image ('.$box.').<br/>';
				}
				// reposition pointer
				if($c==$cols){
					$x = $this->img_border;
					$y += $img_y;
					$c = 1;
					$r++;
				}else{
					$x += $img_x;
					$c++;
				}
			}
			// Save it (the png is not used because the images are huge, jpeg helps this)
			#imagepng($image,'./games/'.$save_dir.'_all.'.$extension);
			imagejpeg($image,$save_dir.'_all.'.$extension,75);
			echo 'A grid image of '.$image_w.'x'.$image_h.' containing <i>up to</i> '.count($images).' images was generated with the file name '.$save_dir.'_all.'.$extension.'.<br/>';
			if($potential_problems!=''){
				echo '<small>';
					echo '<b>The following potential problems occurred during generation:</b><br/>';
					echo $potential_problems;
				echo '</small>';
			}

		}
	}

	// This is used by both sides of the process, it simply cleans the title to something a little more friendly
	static function filename_title_clean($title){
		return preg_replace('~[^a-z0-9-_\.]+~','',preg_replace('~[ ]+~','_',strtolower($title)));
	}

}