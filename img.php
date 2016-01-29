<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('NOSESSION',true);
require_once(DOKU_INC.'inc/init.php');

// let the syntax plugin do the work
$data = $_REQUEST;
$plugin = plugin_load('syntax','graphviz');
$cache  = $plugin->_imgfile($data);
// Update: show svg or png depending on format.
if(!$cache) _fail($data['format']);
// Update: support both png and svg
$img_format = ($data['format'] == 'png' ? 'png' : 'svg');
if ($img_format == 'svg'){
	header('Content-Type: image/svg+xml');
}
else
{
	header('Content-Type: image/png');
}

header('Expires: '.gmdate("D, d M Y H:i:s", time()+max($conf['cachetime'], 3600)).' GMT');
header('Cache-Control: public, proxy-revalidate, no-transform, max-age='.max($conf['cachetime'], 3600));
header('Pragma: public');
http_conditionalRequest($time);
echo io_readFile($cache,false);


function _fail($format){
	if ($format == 'svg'){
		header("HTTP/1.0 404 Not Found");
		header('Content-Type: image/svg+xml');
		echo io_readFile('broken.svg',false);
	}else{
		header("HTTP/1.0 404 Not Found");
		header('Content-Type: image/png');
		echo io_readFile('broken.png',false);
	}
    exit;
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
