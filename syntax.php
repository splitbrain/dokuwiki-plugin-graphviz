<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Matteo Nastasi <nastasi@alternativeoutput.it>
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
    function handle($match, $state, $pos, &$handler) {
        $info = $this->getInfo();

        // prepare default data
        $return = array(
                        'width'     => 0,
                        'height'    => 0,
                        'layout'    => 'dot',
                        'align'     => '',
                        'dpi'       => 0,
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

        if(preg_match('/\bdpi=([0-9]+)\b/i', $conf,$match)) $return['dpi'] = $match[1];

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
        unset($data['layer_n']);
        unset($data['layer_cur']);

        return getcachename(join('x',array_values($data)),'.graphviz.'.$ext);
    }

    /**
     * Create output
     */
    /* -- code example --
<div style="background-color: lightblue;">
<script type="text/javascript"><!--
function $(id) { return document.getElementById(id); }

function showhide(obj)
{
    // alert("NAME: ["+obj.name+"] value: ["+(obj.checked == true ? "visible" : "hidden")+"]");
    $(obj.name).style.visibility = (obj.checked == true ? "visible" : "hidden");
}
//-->
</script>
<div style="background-color: yellow; position: relative; height: 32px;">
<!-- <img style="height: 32px;" src="onepixel.png"> -->
<img id="one" style="position: absolute; left: 0px; top: 0px; height: 32px;" src="one.png">
<img id="two" style="position: absolute; left: 0px; top: 0px;" src="two.png">

I need: the height and the width of the image to dimension the external div tag
     */
    function _render_layered_xhtml($format, &$R, $data, $layers, $in) {
            $prefix = basename($in, ".graphviz.txt");
            $gvpath = dirname($this->getConf('path'));

            /* get selected layers */
            unset($out);
            exec(sprintf("%s/gvpr -f %s %s", escapeshellarg($gvpath),
                         escapeshellarg(DOKU_PLUGIN.'graphviz/get-layerselect-list.gvp'),
                         escapeshellarg($in)), $out, $retval);
            $selayers = $out;

            /* FIXME: if dimension is explicitly added use it instead of calculated */

            /* get image dimensions */
            $cache  = $this->_imgfile($data);
            $size = getimagesize($cache);

            $width  = ($data['width'] > 0 ? $data['width']   : $size[0]);
            $height = ($data['height'] > 0 ? $data['height'] : $size[1]);
            $R->doc .= sprintf('<div class="media%s" style="width: %dpx; %s background-color: lightblue;">
<script type="text/javascript"><!--
function $(id) { return document.getElementById(id); }

function showhide(obj)
{
    // alert("NAME: ["+obj.name+"] value: ["+(obj.checked == true ? "visible" : "hidden")+"]");
    $(obj.name).style.visibility = (obj.checked == true ? "visible" : "hidden");
}
//-->
</script>
<div style="/* background-color: yellow; */position: relative; width: %dpx; height: %dpx;">'."\n",
                               $data['align'], $width, ($data['align'] == '' ? '' : "align: ".$data['align']."; "),
                               $width, $height);
            $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data);
            // $in = $this->_cachename($data,'txt');
            /*
              $R->doc .= '<img src="'.$img.'" class="media'.$data['align'].'" alt=""';
            // $R->doc .= '<!-- zorro2 --><img src="'.$img.'" class="media'.$data['align'].'" alt=""';
            if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
            if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
            if($data['align'] == 'right') $R->doc .= ' align="right"';
            if($data['align'] == 'left')  $R->doc .= ' align="left"';
            $R->doc .= '/>';
            */
            $layers_n = count($layers);
            $data['layer_n'] = $layers_n;
            for ($i = $layers_n - 1 ; $i >= 0 ; $i--) {
                $force_view = ($i == ($layers_n - 1) ? TRUE : FALSE);
                $data['layer_cur'] = $i;
                $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data);
                $R->doc .= sprintf('<img id="%s_%d" style="position: absolute; visibility: %s; left: 0px; top: 0px;" src="%s">'."\n",
                                   $prefix, $i,
                                   (array_search($layers[$i], $selayers) === FALSE && !$force_view ? "hidden" : "visible"), $img);
            }
            $R->doc .= sprintf('</div><form>');
            for ($i = 0 ; $i < $layers_n ; $i++) {
                if ($i == ($layers_n - 1) && $layers[$i] == '_background_')
                    continue;
                $R->doc .= sprintf('%s<input type="checkbox"%s name="%s_%d" onclick=\'showhide(this);\'> %s '."\n",
                                   ($i > 0 ? ": " : ""), (array_search($layers[$i], $selayers) === FALSE ? "" : " checked"),
                                   $prefix, $i, htmlentities($layers[$i]));
            }

            $R->doc .= sprintf('</form></div>');
            return true;
    }

    function render($format, &$R, $data) {
        if($format == 'xhtml'){
            $in = $this->_cachename($data,'txt');
            $gvpath = dirname($this->getConf('path'));
            $script = 'BEG_G { char* larr[int]; int i; if (!isAttr($,"G","layers")) return; if (isAttr($,"G","layersep")) tokens($.layers,larr,$.layersep); else tokens($.layers,larr," :\t"); for (larr[i]) { printf("%s\n",larr[i]); } }';
            exec(sprintf("%s/gvpr %s %s", escapeshellarg($gvpath), escapeshellarg($script), escapeshellarg($in)),
                 $out, $retval);
            if ($out[0] != "") {
                return $this->_render_layered_xhtml($format, &$R, $data, $out, $in);
            }
            $cache  = $this->_imgfile($data);
            $size = getimagesize($cache);

            $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data);
            $R->doc .= '<img src="'.$img.'" class="media'.$data['align'].'" alt=""';
            if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
            if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
            if($data['align'] == 'right') $R->doc .= ' align="right"';
            if($data['align'] == 'left')  $R->doc .= ' align="left"';
            $R->doc .= '/>';
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
        $txt_data = $data;
        unset($txt_data['layer_n']);
        unset($txt_data['layer_cur']);

        if (isset($data['layer_n']) && isset($data['layer_cur']) &&
            $data['layer_cur'] >= 0 && $data['layer_cur'] < $data['layer_n']) {
            $cache = $this->_cachename($data,sprintf("%d.png", $data['layer_cur']));
        }
        else {
            $cache = $this->_cachename($data,'png');
        }

        // create the file if needed
        if(!file_exists($cache)){
            $in = $this->_cachename($txt_data,'txt');
            if($this->getConf('path')){
                $ok = $this->_run($data,$in,$cache);
            }else{
                $ok = $this->_remote($data,$in,$cache);
            }
            if(!$ok) return false;
            clearstatcache();
        }

        // resized version
        if($data['width']){
            $cache = media_resize_image($cache,'png',$data['width'],$data['height']);
        }

        // something went wrong, we're missing the file
        if(!file_exists($cache)) return false;

        return $cache;
    }

    /**
     * Render the output remotely at google
     */
    function _remote($data,$in,$out){
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

        return io_saveFile($out,$img);
    }

    /**
     * Run the graphviz program
     */
    function _run($data,$in,$out) {
        global $conf;

        if(!file_exists($in)){
            if($conf['debug']){
                dbglog($in,'no such graphviz input file');
            }
            return false;
        }

        $cmd  = $this->getConf('path');
        if (isset($data['layer_n']) && isset($data['layer_cur'])) {
            $gvpath = dirname($this->getConf('path'));
            $script = 'BEG_G { char* larr[int]; int i; if (!isAttr($,"G","layers")) return; if (isAttr($,"G","layersep")) tokens($.layers,larr,$.layersep); else tokens($.layers,larr," :\t"); for (larr[i]) { printf("%s\n",larr[i]); } }';
            exec(sprintf("%s/gvpr %s %s", escapeshellarg($gvpath), escapeshellarg($script), escapeshellarg($in)),
                 $exout, $retval);
            $cmd .= sprintf(" '-Glayerselect=%s'%s", $exout[$data['layer_cur']], ((int)$data['layer_cur'] < (int)$data['layer_n'] - 1 ? ' -Gbgcolor=#00000000' : ''));
        }

        $cmd .= ' -Tpng';
        $cmd .= ' -K'.$data['layout'];
        $cmd .= ' -o'.escapeshellarg($out); //output
        $cmd .= ' '.escapeshellarg($in); //input
        if (isset($data['dpi']) && $data['dpi'] > 0) {
            $cmd .= sprintf(" -Gdpi=%d", $data['dpi']);
        }


        exec($cmd, $output, $error);

        if ($error != 0){
            if($conf['debug']){
                dbglog(join("\n",$output),'graphviz command failed: '.$cmd);
            }
            return false;
        }
        return true;
    }

}



