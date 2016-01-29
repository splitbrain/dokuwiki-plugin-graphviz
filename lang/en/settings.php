<?php

$lang['path'] = 'The path to your local graphviz dot binary (eg. <code>/usr/bin/dot</code>). Leave empty to use remote rendering at google.com.';
$lang['use_svg'] = 'Use embeded svg objects. Svg objects support clickable dokuwiki links in the graphs. This is automatically disabled if "path" is left empty.';
$lang['styles'] = 'A block of DOT codes, that can be included to the graphs. Meant to include styling. To set a style put it inside &ltstyle name="stylename"&gt style code here lgt;/style&gt;. The stylename can only contain alphanumeric and underscore characters.'.
					'E.g. <code>'.htmlspecialchars('<style name="fixed_green_circle">node [width=.9, shape=circle, style=filled, fillcolor=green, fixedsize=true, fontcolor=white]</style>').'</code>';
