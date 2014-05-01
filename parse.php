<?php

include 'vendor/autoload.php';
include 'PDFTextFinder.php';
date_default_timezone_set('Asia/Taipei');
ini_set('memory_limit', '2048m');

if (!preg_match('#\.pdf$#i', $_SERVER['argv'][1])) {
    throw new Exception("Usage: php Parse.php [PDF FILE]");
}

$p = new PDFTextFinder;
// parser 傳回的是一個三維的陣列
// 第一維是分頁，從 0 開始
// 第二維是在表格內的第幾格， key 的格式是 3-2 ，表示第三欄第二列
// 第三維是格子內所有文字，包含 x, y (這個文字在整份文件的絕對位置), text (文字內容)
$pages_cells_texts = $p->parse($_SERVER['argv'][1], $_SERVER['argv'][2] ?: 0);
foreach ($pages_cells_texts as $page => $cells_texts) {
    echo("第 " . ($page + 1) . " 頁\n");
    foreach ($cells_texts as $row_col => $textboxes) {
        echo("[" . $row_col . "]\n");
        $prev_y = null;
        foreach ($textboxes as $textbox) {
            if (!is_null($prev_y) and $textbox['y'] - $prev_y > 10) {
                echo "\n";
            }
            $prev_y = $textbox['y'];
            echo trim(str_replace(" ", "", $textbox['text']));
        }
        echo "\n";
    }
}
