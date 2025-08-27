<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\HTTP\DokuHTTPClient;
use dokuwiki\Logger;

/**
 * graphviz-Plugin: Parses graphviz-blocks
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Carl-Christian Salvesen <calle@ioslo.net>
 * @author     Andreas Gohr <andi@splitbrain.org>
 */
class syntax_plugin_graphviz extends SyntaxPlugin
{
    /**
     * What about paragraphs?
     */
    public function getPType()
    {
        return 'normal';
    }

    /**
     * What kind of syntax are we?
     */
    public function getType()
    {
        return 'substition';
    }

    /**
     * Where to sort in?
     */
    public function getSort()
    {
        return 200;
    }

    /**
     * Connect pattern to lexer
     */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<graphviz.*?>\n.*?\n</graphviz>', $mode, 'plugin_graphviz');
    }

    /**
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $info = $this->getInfo();

        // prepare default data
        $return = [
            'width'     => 0,
            'height'    => 0,
            'layout'    => 'dot',
            'align'     => '',
            'version'   => $info['date']
        ];

        // prepare input
        $lines = explode("\n", $match);
        $conf = array_shift($lines);
        array_pop($lines);

        // match config options
        if (preg_match('/\b(left|center|right)\b/i', $conf, $match)) $return['align'] = $match[1];
        if (preg_match('/\b(\d+)x(\d+)\b/', $conf, $match)) {
            $return['width']  = $match[1];
            $return['height'] = $match[2];
        }
        if (preg_match('/\b(dot|neato|twopi|circo|fdp)\b/i', $conf, $match)) {
            $return['layout'] = strtolower($match[1]);
        }
        if (preg_match('/\bwidth=([0-9]+)\b/i', $conf, $match)) $return['width'] = $match[1];
        if (preg_match('/\bheight=([0-9]+)\b/i', $conf, $match)) $return['height'] = $match[1];


        $input = implode("\n", $lines);
        $return['md5'] = md5($input); // we only pass a hash around

        // store input for later use
        io_saveFile($this->getCachename($return, 'txt'), $input);

        return $return;
    }

    /**
     * Create output
     */
    public function render($format, Doku_Renderer $R, $data)
    {
        if ($format == 'xhtml') {
            $img = DOKU_BASE . 'lib/plugins/graphviz/img.php?' . buildURLparams($data);
            $R->doc .= '<img src="' . $img . '" class="media' . $data['align'] . ' plugin_graphviz" alt=""';
            if ($data['width'])  $R->doc .= ' width="' . $data['width'] . '"';
            if ($data['height']) $R->doc .= ' height="' . $data['height'] . '"';
            if ($data['align'] == 'right') $R->doc .= ' align="right"';
            if ($data['align'] == 'left')  $R->doc .= ' align="left"';
            $R->doc .= '/>';
            return true;
        } elseif ($format == 'odt') {
            /** @var Doku_Renderer_odt $R */
            $src = $this->imgFile($data);
            $R->_odtAddImage($src, $data['width'], $data['height'], $data['align']);
            return true;
        }
        return false;
    }

    /**
     * Cache file is based on parameters that influence the result image
     */
    protected function getCachename($data, $ext)
    {
        unset($data['width']);
        unset($data['height']);
        unset($data['align']);
        return getcachename(implode('x', array_values($data)), '.graphviz.' . $ext);
    }

    /**
     * Return path to the rendered image on our local system
     */
    public function imgFile($data)
    {
        $cache  = $this->getCachename($data, 'svg');

        // create the file if needed
        if (!file_exists($cache)) {
            $in = $this->getCachename($data, 'txt');
            if ($this->getConf('path')) {
                $ok = $this->renderLocal($data, $in, $cache);
            } else {
                $ok = $this->renderRemote($data, $in, $cache);
            }
            if (!$ok) return false;
            clearstatcache();
        }

        // something went wrong, we're missing the file
        if (!file_exists($cache)) return false;

        return $cache;
    }

    /**
     * Render the output remotely at google
     *
     * @param array  $data The graphviz data
     * @param string $in   The input file path
     * @param string $out  The output file path
     */
    protected function renderRemote($data, $in, $out)
    {
        if (!file_exists($in)) {
            Logger::debug("Graphviz: missing input file $in");
            return false;
        }

        $http = new DokuHTTPClient();
        $http->timeout = 30;
        $http->headers['Content-Type'] = 'application/json';

        $pass = [];
        $pass['layout'] = $data['layout'];
        $pass['graph'] = io_readFile($in);
        #if($data['width'])  $pass['width']  = (int) $data['width'];
        #if($data['height']) $pass['height'] = (int) $data['height'];

        $img = $http->post('https://quickchart.io/graphviz', json_encode($pass));
        if (!$img) {
            Logger::debug("Graphviz: remote API call failed", $http->resp_body);
            return false;
        }

        return io_saveFile($out, $img);
    }

    /**
     * Run the graphviz program
     *
     * @param array  $data The graphviz data
     * @param string $in   The input file path
     * @param string $out  The output file path
     */
    public function renderLocal($data, $in, $out)
    {
        if (!file_exists($in)) {
            Logger::debug("Graphviz: missing input file $in");
            return false;
        }

        $cmd  = $this->getConf('path');
        $cmd .= ' -Tsvg';
        $cmd .= ' -K' . $data['layout'];
        $cmd .= ' -o' . escapeshellarg($out); //output
        $cmd .= ' ' . escapeshellarg($in); //input

        exec($cmd, $output, $error);

        if ($error != 0) {
            Logger::debug("Graphviz: command failed $cmd", implode("\n", $output));
            return false;
        }
        return true;
    }
}
