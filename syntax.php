<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
require_once(DOKU_INC.'inc/init.php');
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
        return 100;
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

        // prepare default data FIXME
        $return = array(
                        'data'      => '',
                        'width'     => 0,
                        'height'    => 0,
                        'layout'    => 'dot',
#                        'antialias' => true,
#                        'edgesep'   => true,
#                        'round'     => false,
#                        'shadow'    => true,
#                        'scale'     => 1,
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
        if(preg_match('/\b(\d+(\.\d+)?)X\b/',$conf,$match)) $return['scale']  = $match[1];
        if(preg_match('/\bwidth=([0-9]+)\b/i', $conf,$match)) $return['width'] = $match[1];
        if(preg_match('/\bheight=([0-9]+)\b/i', $conf,$match)) $return['height'] = $match[1];
        // match boolean toggles
        if(preg_match_all('/\b(no)?(antialias|edgesep|round|shadow)\b/i',$conf,$matches,PREG_SET_ORDER)){
            foreach($matches as $match){
                $return[$match[2]] = ! $match[1];
            }
        }

        $return['data'] = join("\n",$lines);

        return $return;
    }

    /**
     * Create output
     */
    function render($format, &$R, $data) {
        if($format != 'xhtml') return;

        if($this->getConf('path')){
            // run graphviz on our own server
            $img = DOKU_BASE.'lib/plugins/graphviz/img.php?'.buildURLparams($data,'&');
        }else{

#FIXME
            // use ditaa.org for rendering
            $pass = array(
                'grid'  => $data['data'],
                'scale' => $data['scale']
            );
            if(!$data['antialias']) $pass['A'] = 'on';
            if(!$data['shadow'])    $pass['S'] = 'on';
            if($data['round'])      $pass['r'] = 'on';
            if(!$data['edgesep'])   $pass['E'] = 'on';
            $pass['timeout'] = 25;

            $img = 'http://ditaa.org/ditaa/render?'.buildURLparams($pass,'&');
            $img = ml($img,array('w'=>$data['width'],'h'=>$data['height']));
        }

        $R->doc .= '<img src="'.$img.'" class="media'.$data['align'].'" alt=""';
        if($data['width'])  $R->doc .= ' width="'.$data['width'].'"';
        if($data['height']) $R->doc .= ' height="'.$data['height'].'"';
        if($data['align'] == 'right') $ret .= ' align="right"';
        if($data['align'] == 'left')  $ret .= ' align="left"';
        $R->doc .= '/>';
    }


    /**
     * Run the graphviz program
     */
    function _run($data,$cache) {
        global $conf;

        $temp = tempnam($conf['tmpdir'],'graphviz_');
        io_saveFile($temp,$data['data']);

        $cmd  = $this->getConf('path');
        $cmd .= ' -Tpng';
        $cmd .= ' -K'.$data['layout'];
        $cmd .= ' -o'.escapeshellarg($cache); //output
        $cmd .= ' '.escapeshellarg($temp); //input

        exec($cmd, $output, $error);
        @unlink($temp);

        if ($error != 0){
            if($conf['debug']){
                dbglog(join("\n",$output),'graphviz command failed: '.$cmd);
            }
            return false;
        }
        return true;
    }

}


