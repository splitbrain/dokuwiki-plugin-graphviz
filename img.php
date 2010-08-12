<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andreas Gohr <gohr@cosmocode.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',dirname(__FILE__).'/../../../');
define('NOSESSION',true);
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/pageutils.php');
require_once(DOKU_INC.'inc/httputils.php');
require_once(DOKU_INC.'inc/io.php');

$data = $_REQUEST;
$w = (int) $data['width'];
$h = (int) $data['height'];
unset($data['width']);
unset($data['height']);
unset($data['align']);

$cache = getcachename(join('x',array_values($data)),'graphviz.png');

// create the file if needed
if(!file_exists($cache)){
    $plugin = plugin_load('syntax','graphviz');
    $plugin->_run($data,$cache);
    clearstatcache();
}

// resized version
if($w) $cache = media_resize_image($cache,'png',$w,$h);

// something went wrong, we're missing the file
if(!file_exists($cache)){
    header("HTTP/1.0 404 Not Found");
    header('Content-Type: image/png');
    echo io_readFile('broken.png',false);
    exit;
}

header('Content-Type: image/png;');
header('Expires: '.gmdate("D, d M Y H:i:s", time()+max($conf['cachetime'], 3600)).' GMT');
header('Cache-Control: public, proxy-revalidate, no-transform, max-age='.max($conf['cachetime'], 3600));
header('Pragma: public');
http_conditionalRequest($time);
echo io_readFile($cache,false);

//Setup VIM: ex: et ts=4 enc=utf-8 :
