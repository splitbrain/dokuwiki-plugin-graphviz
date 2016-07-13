<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */


if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_graphviz extends DokuWiki_Syntax_Plugin {

    /**
     * What about paragraphs?
     */
    function getPType(){
        return 'normal';
    }

    /**
     * What kind of syntax are we?
     */
    function getType(){
        return 'substition';
    }

    /**
     * Where to sort in?
     */
    function getSort(){
        return 200;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<graphviz.*?>\n.*?\n</graphviz>',$mode,'plugin_graphviz');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        $info = $this->getInfo();

        // prepare default data
        $return = array(
                        'width'     => 0,
                        'height'    => 0,
                        'layout'    => 'dot',
                        'align'     => '',
                        'version'   => $info['date'], //force rebuild of images on update
                       );

        // prepare input
        $lines = explode("\n",$match);
        $conf = array_shift($lines);
        array_pop($lines);

        // match config options
        if(preg_match('/\b(left|center|right)\b/i',$conf,$match)) $return['align'] = $match[1];
        if(preg_match('/\b(\d+)x(\d+)\b/',$conf,$match)){
            $return['width']  = $match[1];
            $return['height'] = $match[2];
        }
        if(preg_match('/\b(dot|neato|twopi|circo|fdp)\b/i',$conf,$match)){
            $return['layout'] = strtolower($match[1]);
        }
        if(preg_match('/\bwidth=([0-9]+)\b/i', $conf,$match)) $return['width'] = $match[1];
        if(preg_match('/\bheight=([0-9]+)\b/i', $conf,$match)) $return['height'] = $match[1];


        $input = join("\n",$lines);
        $return['md5'] = md5($input); // we only pass a hash around

        // store input for later use
        io_saveFile($this->_cachename($return,'txt'),$input);

        return $return;
    }

    /**
     * Cache file is based on parameters that influence the result image
     */
    function _cachename($data,$ext){
        unset($data['width']);
        unset($data['height']);
        unset($data['align']);
        return getcachename(join('x',array_values($data)),'.graphviz.'.$ext);
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $R, $data) {
        if($format == 'xhtml'){
            $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data);
            $R->doc .= '<img src="'.$img.'" class="media'.$data['align'].'" alt=""';
            if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
            if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
            if($data['align'] == 'right') $R->doc .= ' align="right"';
            if($data['align'] == 'left')  $R->doc .= ' align="left"';
            $R->doc .= ' usemap="#graphviz_'.$data['md5'].'"';
            $R->doc .= "/>\n";
            $R->doc .= io_readFile($this->_imgmap($data), false);
            $R->doc .= '<script type="text/javascript">/*<![CDATA[*/ imageMapResize(); /*!]]>*/</script>'."\n";
            return true;
        }elseif($format == 'odt'){
            $src = $this->_imgfile($data);
            $R->_odtAddImage($src,$data['width'],$data['height'],$data['align']);
            return true;
        }
        return false;
    }

    /**
     * Return path to the rendered image on our local system
     */
    function _imgfile($data){
        $cache  = $this->_cachename($data,'png');

        // create the file if needed
        if(!file_exists($cache)){
            $ok = $this->_run($data);
            if(!$ok) return false;
        }

        // something went wrong, we're missing the file
        if(!file_exists($cache)) return false;

        return $cache;
    }

    /**
     * Return path to the rendered map on our local system
     */
    function _imgmap($data){
        $cache  = $this->_cachename($data,'map');

        // create the file if needed
        if(!file_exists($cache)){
            $ok = $this->_run($data);
            if(!$ok) return false;
        }

        // something went wrong, we're missing the file
        if(!file_exists($cache)) return false;

        return $cache;
    }

    /**
     * Render the graph
     */
    function _run($data){
        $cache_png  = $this->_cachename($data,'png');
        $cache_map  = $this->_cachename($data,'map');
        $in = $this->_cachename($data,'txt');
        if($this->getConf('path')){
            $ok = $this->_run_local($data,$in,$cache_png,$cache_map);
        }else{
            $ok = $this->_run_remote($data,$in,$cache_png,$cache_map);
        }
        if(!$ok) return false;
        clearstatcache();
        return true;
    }

    /**
     * Render the output remotely at google
     */
    function _run_remote($data,$in,$out_png,$out_map){
        if(!file_exists($in)){
            if($conf['debug']){
                dbglog($in,'no such graphviz input file');
            }
            return false;
        }

        $http = new DokuHTTPClient();
        $http->timeout=30;

        $pass = array();
        $pass['cht'] = 'gv:'.$data['layout'];
        $pass['chl'] = io_readFile($in);

        $img = $http->post('http://chart.apis.google.com/chart',$pass,'&');
        if(!$img) return false;

        //Unfortunately, google chart doesn't support image-maps, store an empty map.
        $ok = io_saveFile($out_map, '<map name="graphviz_'.$data['md5'].'"></map>');
        if(!$ok) return false;
        return io_saveFile($out_png,$img);
    }

    /**
     * Run the graphviz program locally
     */
    function _run_local($data,$in,$out_png,$out_map) {
        global $conf;

        if(!file_exists($in)){
            if($conf['debug']){
                dbglog($in,'no such graphviz input file');
            }
            return false;
        }

        $cmd  = $this->getConf('path');
        $cmd .= ' -K'.$data['layout'];
        $cmd .= ' -Tpng';
        $cmd .= ' -o'.escapeshellarg($out_png); //output png image
        $cmd .= ' -Tcmapx';
        $cmd .= ' -o'.escapeshellarg($out_map); //output image map
        $cmd .= ' '.escapeshellarg($in); //input

        exec($cmd, $output, $error);

        if ($error != 0){
            if($conf['debug']){
                dbglog(join("\n",$output),'graphviz command failed: '.$cmd);
            }
            return false;
        }

        // change name of image-map
        $map = io_readFile($out_map,false);
        $map = preg_replace('/<map [^>]*>/', '<map name="graphviz_'.$data['md5'].'">', $map);
        return io_saveFile($out_map,$map);
    }

}



