<?php 

require_once 'markdown.php';
// get markdown.php from http://michelf.com/projects/php-markdown/
// or mirror at http://www.catnapgames.com/media/src/markdown.php


/*
Tempo 1.1
Author: Tomas Andrle
Website: http://www.catnapgames.com/blog/2011/10/13/tempo-php-static-site-generator.html

New in 1.1:

Markdown support. Instead of the [img ] tags and HTML you can use Markdown. Just specify
"format markdown" in the text file header.

*/

if ( $argc != 2 ) {
	echo "Usage: tempo.php site_directory\n";
	exit(0);
}

chdir( $argv[1] . '/..' );

include $argv[1].'/config/tempo-config.php';
extract( $config );

function resizeimage($filename, $width) {
	global $site_dir;
	global $media_dir;
	global $cache_dir;
	global $output_dir;
	
    $height = 3000;

    list($width_orig, $height_orig) = getimagesize($filename);

    if ( $width_orig > $width || $height_orig > $height ) {

        $ratio_orig = $width_orig/$height_orig;

        if ($width/$height > $ratio_orig) {
           $width = $height*$ratio_orig;
        } else {
           $height = round($width/$ratio_orig);
        }

        echo "  Resizing image $filename to ${width}x${height}\n";

        $info = pathinfo($filename);
        $dir = str_replace( "${site_dir}/${media_dir}", "${output_dir}/${cache_dir}", $info['dirname'] );
        $output_filename = $dir .'/' . stem($filename) . '-' . $width . '.'. $info['extension'];
        
		@mkdir( dirname( $output_filename ), 0777, true ); // recursive

        $image_p = imagecreatetruecolor($width, $height);
        if ( $info['extension'] == 'jpg' ) {
	        $image = imagecreatefromjpeg($filename);
	    } else {
	    	$image = iamgecreatefrompng($filename);
	    }
	    imagecopyresampled($image_p, $image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
                
        if ( $info['extension'] == 'jpg' ) {
            imagejpeg($image_p, $output_filename, 85);
        } else {
            imagepng( $image_p, $output_filename );
        }
                
        return $output_filename;
    } else {
        return $filename;
    }
}

// from comment at http://php.net/manual/en/function.copy.php
function rrmdir($dir) {
  if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file)
    if ($file != "." && $file != "..") rrmdir("$dir/$file");
    rmdir($dir);
  }
  else if (file_exists($dir)) unlink($dir);
} 

// from comment at http://php.net/manual/en/function.copy.php
function rcopy($src, $dst) {
  if (file_exists($dst)) rrmdir($dst);
  if (is_dir($src)) {
    mkdir($dst);
    $files = scandir($src);
    foreach ($files as $file)
    if ($file != "." && $file != "..") rcopy("$src/$file", "$dst/$file"); 
  }
  else if (file_exists($src)) copy($src, $dst);
}

function endswith($string, $end) {
    $strlen = strlen($string);
    $testlen = strlen($end);
    if ($testlen > $strlen) return false;
    return substr_compare($string, $end, -$testlen) === 0;
}

function stem( $file ) {
    $info = pathinfo($file);
    $file_name =  basename($file,'.'.$info['extension']);
    return $file_name;
}

function slug( $string, $extension ) {
    return strtolower( preg_replace( array( '/-/', '/[^a-zA-Z0-9\s\/\.]/', '/[\s]/', '/\.txt$/' ), array( '/', '', '-', '.'.$extension ), $string ) );
}

// lists txt files in a directory. skips filenames that start with #
function dirlist( &$list, $path ) {
    $dh = @opendir( $path ); 

    while( false !== ( $file = readdir( $dh ) ) ) {
		if ( !is_dir( "$path/$file" ) && endswith( $file, ".txt" ) && strpos($file, '#' ) !== 0 ) {
			$list[] = array( 'file' => $file, );
		}
    }

    closedir( $dh );
}

function filter_make_url( $var ) {
	$pattern = '/([^\"])(http:\/\/)([\w\.\/\-\:\+;_?=&%@$#~]*[\w\/\-\+;_?=&@%$#~])([\s]?)/';
	
	if ( preg_match_all( $pattern, $var, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		$offset_shift = 0;
		for ( $i=0; $i < count( $matches ); $i++ ) {
			$original = $matches[$i][0][0];
			$offset = $matches[$i][0][1] + $offset_shift;
			$part0 = $matches[$i][1][0]; // stuff just before the url; anything but " (which means it's probably already inside an <a src=".."> tag.
			$part1 = $matches[$i][2][0]; // http://
			$part2 = $matches[$i][3][0]; // www.something.com/path/path/
			$part3 = $matches[$i][4][0]; // stuff that's just behind the url but probably not part of it

			$link = $part0.'<a class="external" href="'.$part1.htmlspecialchars($part2).'" target="_blank">'.$part2.'</a>'.$part3;
			
			$var = substr_replace( $var, $link, $offset, strlen( $original ) );
			$offset_shift += strlen( $link ) - strlen( $original );
		}
		return $var;
	} else {
		return $var;
	}
}

function filter_img( $var, $root_url ) {
	global $site_dir;
	global $site_url;
	global $image_dimensions;
	global $output_dir;
	
	$href = '$2.$3';
	$pattern = '/\[(imgleft|imgright|imgfull)\s+([^\]]*)\]/'; // example: [imgleft media/123.png]
	$matches = array();
	if ( preg_match_all( $pattern, $var, $matches, PREG_SET_ORDER ) ) {
		for ( $i=0; $i < count( $matches ); $i++ ) {
			$placement = $matches[$i][1];
			$src = $matches[$i][2];
			
			$width = $image_dimensions[ $placement ];
			$new = resizeimage( $site_dir . '/' . $src, $width );
			$new = str_replace( $output_dir .'/', '', $new ); // image was resized, links to thumbnail
			$new = str_replace( $site_dir .'/', '', $new ); // image was not resized, links to original
			
			$new = $root_url . $new;
			
			$replacement = '<img class="'.$placement.'" alt="" src="'.$new.'"/>'; 
			$var = preg_replace( $pattern, $replacement, $var, 1 );
		}
	}
	return $var;
}

function generate_page( $item ) {
	global $template_dir;
	global $site_url;
	global $blog_rss;
	global $blog_files;
	
	extract( $item );
	
	$template_file = $template_dir . '/' . $template . '.php';	
	$template_code = file_get_contents( $template_file );

	ob_start();
	eval( '?>' . $template_code );
	$contents = ob_get_contents();
	ob_end_clean();
	
	return $contents;
}

function parse_text($file, $lines) {
	global $site_url;

	$reading_header = true;
	$body = array();
	$result = array();
	$result['menuitem'] = '';
	$result['extension'] = 'html';

	foreach ( $lines as $line ) {
		if ( $reading_header ) {
			$line = trim( $line );
			$matches = array();
			if ( preg_match( "/^(rss|extension|template|format|title|menuitem)\s+(.+)$/", $line, $matches ) ) {
				$result[ $matches[ 1 ] ] = $matches[ 2 ];
			}
			
			if ( $line == '--' ) {
				$reading_header = false;
			}
		} else {
			// accumulate into buffer
			$body[] = rtrim($line);
		}
	}
	
	$result['slug'] = slug( $file, $result['extension'] );
	$result['url'] = $site_url . '/' . $result[ 'slug' ];
	$result['rss'] = in_array( strtolower($result['rss']), array( 'yes', 'true', 'on', 'enable', 'enabled' ) );

	if ( $result['rss'] ) {
		echo "  XML feed mode enabled\n";
		$result['root_url'] = $site_url . '/';
	} else {
		$num_parent_dirs = count( explode( '/', $result['slug'] ) ) - 1;
		$result['root_url'] = str_repeat( '../', $num_parent_dirs );
	}
	
	$result['body'] = join( $body, "\n" );
	
	if ( $result['format'] == 'markdown' ) {
		$result['body'] = Markdown( $result['body'] );
	} else {
		$result['body'] = filter_make_url( $result['body'] );
		$result['body'] = filter_img( $result['body'], $result['root_url'] );
	}

	return $result;
}

// delete previous version
echo "Cleaning old output\n";
rrmdir( $output_dir );

// get list of pages, each with filename and slug
$all_files = array();
dirlist( &$all_files, $pages_dir ); 

// parse titles, template names and other meta information, add it to $all_files
for ( $i=0; $i < count( $all_files ); $i++ ) {
	extract( $all_files[ $i ] );
	$lines = file( $pages_dir . '/' . $file );
	$vars = parse_text( $file, $lines );
	$all_files[ $i ] = array_merge( $all_files[ $i ], $vars );
}

// blog

$blog_pattern = '/^blog-(?P<year>[0-9]{4})-(?P<month>[0-9]{2})-(?P<day>[0-9]{2})-(.*)\.txt$/';
function blog_sort_descending($a, $b) { return strcmp($b["file"], $a["file"]); }

// filter blog posts

$blog_files = array();
usort( $all_files, "blog_sort_descending" );

for ( $i=0; $i < count( $all_files ); $i++ ) {
	if ( preg_match( $blog_pattern, $all_files[ $i ]['file'] ) ) {
		$blog_files[] = $all_files[ $i ];
	}
}

// extract blog post dates (year, month, day), save back into $blog_files
for ( $i=0; $i < count( $blog_files ); $i++ ) {
	$matches = array();
	preg_match( $blog_pattern, $blog_files[ $i ]['file'], $matches );
	$blog_files[ $i ] = array_merge( $blog_files[ $i ], $matches );
}

//$blog_latest = array_slice( $blog_files, 0, min( $blog_num_latest, count( $blog_files ) ) );
$blog_rss = array_slice( $blog_files, 0, min( $blog_num_rss, count( $blog_files ) ) );

// generate html
for ( $i=0; $i < count( $all_files ); $i++ ) {
	echo "Processing ".$all_files[ $i ]['file']."\n";

	$contents = generate_page( $all_files[$i] );
	
	$destination = $output_dir . '/' . $all_files[$i]['slug'];
	
	@mkdir( dirname( $destination ), 0777, true );
	
	file_put_contents( $destination, $contents );
}

// copy static resources
echo "Copying media\n";
rcopy( $site_dir . '/'. $media_dir, $output_dir . '/'.$media_dir );

?>