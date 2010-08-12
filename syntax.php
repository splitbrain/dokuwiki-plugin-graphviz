<?php
/**
 * graphviz-Plugin: Parses graphviz-blocks
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @version    0.1.20050525
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
require_once(DOKU_INC.'inc/init.php');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_graphviz extends DokuWiki_Syntax_Plugin {
 
 
  function getInfo(){
    return array(
		 'author' => 'Carl-Christian Salvesen',
		 'email'  => 'calle@ioslo.net',
		 'date'   => '2007-02-11',
		 'name'   => 'graphviz Plugin',
		 'desc'   => 'Parses graphviz-blocks',
		 'url'    => 'http://wiki.ioslo.net/dokuwiki/graphviz',
		 );
  }
 
  /**
   * What kind of syntax are we?
   */
  function getType(){
    return 'protected';
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
    $this->Lexer->addEntryPattern('<graphviz(?=.*\x3C/graphviz\x3E)',$mode,'plugin_graphviz');
  }
 
  function postConnect() {
    $this->Lexer->addExitPattern('</graphviz>','plugin_graphviz');
  }
 
  /**
   * Handle the match
   */
 
 
  function handle($match, $state, $pos) {
    if ( $state == DOKU_LEXER_UNMATCHED ) {
      $matches = preg_split('/>/u',$match,2);
      $matches[0] = trim($matches[0]);
      if ( trim($matches[0]) == '' ) {
	$matches[0] = NULL;
      }
      return array($matches[1],$matches[0]);
    }
    return TRUE;
  }
  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $conf;
    if($mode == 'xhtml' && strlen($data[0]) > 1) {
      if ( !is_dir($conf['mediadir'] . '/graphviz') ) 
	io_mkdir_p($conf['mediadir'] . '/graphviz'); //Using dokuwiki framework
      $hash = md5(serialize($data));
      $filename = $conf['mediadir'] . '/graphviz/'.$hash.'.png';
      $url = ml('graphviz:'.$hash.'.png'); //Using dokuwiki framework
 
      if ( is_readable($filename) ) {
	// cached.
	$renderer->doc .= '<img src="'.$url.'" class="media" title="Graph" alt="Graph" />';
	//						$renderer->doc .= $renderer->internalmedialink('graphviz:'.$hash.'.png');
	return true;
      }
 
      if (!$this->createImage($filename, $data[0], $data[1])) {
	$renderer->doc .= '<img src="'.$url.'" class="media" title="Graph" alt="Graph" /> ';
	//					$renderer->doc .= $renderer->internalmedialink('graphviz:'.$hash.'.png');
      } else {
	$renderer->doc .= '**ERROR RENDERING GRAPHVIZ**';
      }
      return true;
    }
    elseif($mode == 'odt' && strlen($data[0])>1){
	list($state, $datae) = $data;	

        $hash = md5(serialize($data));
	$imfilename=$conf['mediadir'] . '/graphviz/'.$hash.'.png';
	
	if (is_readable($filename)){
		     // Content
		      $renderer->p_close();
		      $renderer->doc .= '<text:p text:style-name="Table_20_Contents">';
		      $renderer->_odtAddImage($imfilename);
		      $renderer->doc .= '</text:p>';
		      $renderer->p_open();
		}
		elseif (!$this->createImage($imfilename, $data[0], $data[1])) {
			// Content
	              $renderer->p_close();

		      $renderer->doc .= '<text:p text:style-name="Table_20_Contents">';
	              $renderer->_odtAddImage($imfilename);
		      $renderer->doc .= '</text:p>';
        	      $renderer->p_open();
		}
		else{
			$renderer->doc .= "UNABLE TO ADD GRAPHVIZ GRAPH";
		}
	return true;
    }
    elseif($mode == 'latex' && strlen($data[0]) > 1) { //Latex mode for dokuTeXit
      global $TeXitImage_glob;
      global $_dokutexit_conf;
      $hash = md5(serialize($data));
      if (isset($_dokutexit_conf) && $_dokutexit_conf['mode'] == 'pdflatex') {
	$filename = $conf['mediadir'] . '/graphviz/'.$hash.'.png';
      } else {
	$filename = $conf['mediadir'] . '/graphviz/'.$hash.'.ps';
      }
      //Saving filename for zipfile
      $TeXitImage_glob['plugin_list'][$hash] = $filename; 
      if (is_readable($filename) ) {
	// cached.
	$renderer->doc .= "\\begin{figure}[h]\n";
	$renderer->doc .= "\t\\begin{center}\n";
	$renderer->doc .= "\t\t\\includegraphics{";
	$renderer->doc .= $filename;
	$renderer->doc .= "}\n";
	$renderer->doc .= "\t\\end{center}\n";
	$renderer->doc .= "\\end{figure}\n";
	return true;
      }
      if (!$this->createImageLatex($filename, $data[0], $data[1])) {
	$renderer->doc .= "\\begin{figure}[h]\n";
	$renderer->doc .= "\t\\begin{center}\n";
	$renderer->doc .= "\t\t\\includegraphics{";
	$renderer->doc .= $filename;
	$renderer->doc .= "}\n";
	$renderer->doc .= "\t\\end{center}\n";
	$renderer->doc .= "\\end{figure}\n";
      } else {
	$renderer->putent('**ERROR RENDERING GRAPHVIZ**');
      }
      return true;
    }
    return false;
  }
 
  function createImageLatex($filename, &$data, $graphcmd='dot') { //Latex mode have better rendering with ps
    if (isset($_dokutexit_conf) && $_dokutexit_conf['mode'] == 'pdflatex') {
      return $this->createImage($filename, $data, $graphcmd);
    }
    $cmds = array('dot','neato','twopi','circo','fdp');
    if ( !in_array($graphcmd, $cmds) ) $graphcmd = 'dot';
 
    $tmpfname = tempnam("/tmp", "dokuwiki.graphviz");
    io_saveFile($tmpfname, $data);
    $retval = exec('/usr/bin/'.$graphcmd.' -Gsize="5,4" -Tps ' .
		   $tmpfname.' -o '. $filename);
    unlink($tmpfname);
    return $retval;
  }
 
  function createImage($filename, &$data, $graphcmd='dot') {
 
    $cmds = array('dot','neato','twopi','circo','fdp');
    if ( !in_array($graphcmd, $cmds) ) $graphcmd = 'dot';
 
    $tmpfname = tempnam("/tmp", "dokuwiki.graphviz");
    io_saveFile($tmpfname, $data); //Using dokuwiki framework
    //    file_put_contents($tmpfname, $data); 
    //$retval = exec('/usr/bin/'.$graphcmd.' -Tps '.$tmpfname.'|/usr/bin/convert ps:- png:'.$filename);
    // Comment out the line over this and uncomment the line below to NOT use ImageMagick for antialiazing.
    // Comment out the line over this and uncomment the line below to NOT use ImageMagick for antialiazing.
 
 
     $retval = exec('/usr/bin/'.$graphcmd.' -Tpng -o '.$filename .' '.$tmpfname);
    /* WINDOWS VERSION */
    // change     $tmpfname = tempnam("C:\temp", "dokuwiki.graphviz");
    //change $retval = exec('C:\grapviz\bin\'.$graphcmd.' -Tpng -o '.$filename .' '.$tmpfname);
    unlink($tmpfname);
    return $retval;
  }
 
}
 
?>
