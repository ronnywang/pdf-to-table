<?php

class PDFTextFinder 
{
    public function isBlack($rgb)
    {
        return $rgb['red'] == 0 and $rgb['green'] == 0 and $rgb['blue'] == 0;
    }

    /**
     * getWords 取出 PDF 中所有文字的位置
     * 
     * @param string $pdf_file 
     * @access public
     * @return $array[$page] = array('x', 'y', 'text')
     */
    public function getWords($pdf_file, $page = 0)
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_file);
        $pages = $pdf->getPages();
        $page_words = array();

        if ($page) {
            $pages = array($pages[$page - 1]);
        }
        foreach ($pages as $page_id => $page) {
            $content = $page->getHeader()->get('Contents')->getContent();
            $sections = $page->getSectionsText($content);
            $height = floor($page->get('MediaBox')->getContent()[3]->getContent());

            $current_position_td = array('x' => false, 'y' => false);
            $current_position_tm = array('x' => false, 'y' => false);
            $page_words[$page_id] = array();

            foreach ($sections as $section) {
                $commands = $page->getCommandsText($section);
                foreach ($commands as $command) {
                    switch ($command['o']) {
                    case 'Td':
                    case 'TD':
                        $args = preg_split('/\s/s', $command['c']);
                        $y    = $height - array_pop($args);
                        $x    = array_pop($args);
                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    case 'Tm':
                        $args = preg_split('/\s/s', $command['c']);
                        $y    = $height - array_pop($args);
                        $x    = array_pop($args);
                        $current_position_tm = array('x' => $x, 'y' => $y);
                        break;

                    case 'Tf':
                        list($id,) = preg_split('/\s/s', $command['c']);
                        $id           = trim($id, '/');
                        $current_font = $page->getFont($id);
                        break;

                    case "'":
                    case 'Tj':
                        $command['c'] = array($command);
                    case 'TJ':
                        // Skip if not previously defined, should never happened.
                        $sub_text = $current_font->decodeText($command['c']);
                        $page_words[$page_id][] = array(
                            'x' => $current_position_tm['x'],
                            'y' => $current_position_tm['y'],
                            'text' => $sub_text,
                        );
                        break;


                    case 'Do':
                        if (!is_null($page)) {
                            $args = preg_split('/\s/s', $command['c']);
                            $id   = trim(array_pop($args), '/ ');
                            if ($xobject = $page->getXObject($id)) {
                                $page_words[$page_id][] = array(
                                    'x' => $current_position_tm['x'],
                                    'y' => $current_position_tm['y'],
                                    'text' => $xobject->getText($page),
                                );
                            }
                        }
                        break;
                    case 'Tc':
                        //set character spacing
                    case 'g':
                        // setgray (fill).
                    case 'G':
                        // setgray (fill).
                        break;
                    case 'Tw':
                        // set word spacing
                        break;
                    case 'rg':
                        // set background color
                        break;
                    default:
                    }
                }
            }
        }
        return $page_words;
    }

    /**
     * 取得水平和垂直的線
     * 
     * @param string $output_prefix 不含 .pdf 的 prefix 名稱
     * @access public
     * @return array[$page]['verticle or horizon'][$start_point] = array(
     */
    public function getLines($pdf_file, $page = 0)
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_file);
        $pages = $pdf->getPages();
        $page_words = array();

        if ($page) {
            $pages = array($pages[$page - 1]);
        }

        foreach ($pages as $page_id => $page) {
            $width = floor($page->get('MediaBox')->getContent()[2]->getContent());
            $height = floor($page->get('MediaBox')->getContent()[3]->getContent());
            $rotate = $page->getDetails()['Rotate'];
            if (90 == $rotate) {
                $gd = imagecreate($height, $width);
            } else {
                $gd = imagecreate($width, $height);
            }

            $white = imagecolorallocate($gd, 255, 255, 255);
            $green = imagecolorallocate($gd, 0, 0, 0);

            $content = $page->getHeader()->get('Contents')->getContent();
            $current_x = $current_y = null;
            foreach (explode("\n", $content) as $command) {
                if (preg_match('/re$/', trim($command))) {
                    list($x, $y, $w, $h) = explode(' ', trim($command));
                    if ($h > 1 and $w > 1) {
                        continue;
                    }

                    if ($h < 1) {
                        imageline($gd, $x, $height - $y, $x + $w, $height - $y, $green);
                    } elseif ($w < 1) {
                        imageline($gd, $x, $height - $y, $x, $height - $y - $h, $green);
                    }
                } elseif (preg_match('/ m$/', trim($command))) {
                    list($x, $y) = explode(' ', trim($command));
                    $current_x = $x;
                    $current_y = $height - $y;
                } elseif (preg_match('/ l$/', trim($command))) {
                    list($x, $y) = explode(' ', trim($command));
                    $y = $height - $y;
                    imageline($gd, $current_x, $current_y, $x, $y, $green);
                }
            }

            //imagepng($gd, 'test.png');

            $lines[$page_id] = array(
                'verticle' => array(),
                'horizon' => array(),
            );

            $verticles = $horizons = array();
            for ($i = 0; $i < $width; $i ++) {
                $line_start = null;
                // 故意 $j 會到比 $height 多一個 pixel，以處理線條到底的情況
                for ($j = 0; $j <= $height; $j ++) {
                    if ($j != $height) {
                        $rgb = imagecolorat($gd, $i, $j);
                        $colors = imagecolorsforindex($gd, $rgb);
                    }

                    if ($j == $height or !$this->isBlack($colors)) {
                        if (is_null($line_start)) {
                            continue;
                        }
                        // 只有 10 pixel 算是有一條直線
                        if ($j - 1 - $line_start > 10) {
                            if ($i and array_key_exists($i - 1, $lines[$page_id]['verticle'])) {
                                foreach ($lines[$page_id]['verticle'][$i - 1] as $start_point => $end_point) {
                                    if (abs($start_point - $line_start) + abs($end_point - $j + 1) < 5 ) {
                                        $line_start = null;
                                        break 2;
                                    }
                                }
                            }

                            if (!array_key_exists($i, $lines[$page_id]['verticle'])) {
                                $lines[$page_id]['verticle'][$i] = array();
                            }
                            $verticles[$i] = true;
                            $lines[$page_id]['verticle'][$i][$line_start] = $j - 1;
                        }
                        $line_start = null;
                        continue;
                    }

                    if (is_null($line_start)) {
                        $line_start = $j;
                    }
                }
            }

            for ($i = 0; $i < $height; $i ++) {
                $line_start = null;
                // 故意 $j 會到比 $height 多一個 pixel，以處理線條到底的情況
                for ($j = 0; $j <= $width; $j ++) {
                    if ($j != $width) {
                        $rgb = imagecolorat($gd, $j, $i);
                        $colors = imagecolorsforindex($gd, $rgb);
                    }

                    if ($j == $width or !$this->isBlack($colors)) {
                        if (is_null($line_start)) {
                            continue;
                        }
                        // 只有 10 pixel 算是有一條直線
                        if ($j - 1 - $line_start > 10) {
                            if ($i and array_key_exists($i - 1, $lines[$page_id]['horizon'])) {
                                foreach ($lines[$page_id]['horizon'][$i - 1] as $start_point => $end_point) {
                                    if (abs($start_point - $line_start) + abs($end_point - $j + 1) < 5 ) {
                                        $line_start = null;
                                        break 2;
                                    }
                                }
                            }

                            if (!array_key_exists($i, $lines[$page_id]['horizon'])) {
                                $lines[$page_id]['horizon'][$i] = array();
                            }
                            $horizons[$i] = true;
                            $lines[$page_id]['horizon'][$i][$line_start] = $j - 1;
                        }
                        $line_start = null;
                        continue;
                    }

                    if (is_null($line_start)) {
                        $line_start = $j;
                    }
                }
            }
            // TODO: 有可能一頁有多個表格... horizons, verticles 可能要分 group 比較好
            $lines[$page_id]['horizons'] = array_keys($horizons);
            $lines[$page_id]['verticles'] = array_keys($verticles);
        }
        return $lines;
    }

    public function parse($file, $page_id = 0)
    {
        $page_words = $this->getWords($file, $page_id);
        $lines = $this->getLines($file, $page_id);

        $page_divs = array();

        foreach ($page_words as $page_id => $words) {
            $divs = array();

            foreach ($words as $word) {
                if ('' === $word['text']) {
                    continue;
                }
                $word['text'] = iconv('UTF-8', 'UTF-8//IGNORE', $word['text']);

                // 求 y
                $x = $y = 0;

                foreach ($lines[$page_id]['horizons'] as $really_y => $horizon) {
                    if ($word['y'] < $horizon) {
                        break;
                    }
                    foreach ($lines[$page_id]['horizon'][$horizon] as $start => $end) {
                        if ($word['x'] >= $start and $word['x'] <= $end) {
                            $y = $really_y;
                            break;
                        }
                    }
                }

                foreach ($lines[$page_id]['verticles'] as $really_x => $verticle) {
                    if ($word['x'] < $verticle) {
                        break;
                    }
                    foreach ($lines[$page_id]['verticle'][$verticle] as $start => $end) {
                        if ($word['y'] >= $start and $word['y'] <= $end) {
                            $x = $really_x;
                            break;
                        }
                    }
                }

                if (!array_key_exists($x . '-' . $y, $divs)) {
                    $divs[$x . '-' . $y] = array();
                }

                $divs[$x . '-' . $y][] = $word;
            }
            $page_divs[$page_id] = $divs;
            ksort($page_divs[$page_id]);
        }

        return $page_divs;
    }
}

