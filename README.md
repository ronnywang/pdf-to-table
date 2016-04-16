pdf-to-table
============

** 此專案已停止維護，可改用 https://github.com/ronnywang/pdf-table-extractor 這個新開發 PDF 抓表格功能。

將 PDF 內的 Table 想辦法取出來
這份程式是 2014/2/22 台灣零時政府第柒次自由時代黑客松 為了處理農委會農藥使用手冊所寫的 parser
目標是希望能將用藥手冊的 PDF 轉換成結構化資料
關於用藥手冊需要的特別處理可以參考下面 notice 章節

usage
-----
1. $ composer install  # 安裝需要的套件，其中用到了 smalot/pdfparser 來 parse pdf
2. $ php parse.php rid-01.pdf # 取出這份 PDF 文件內每一格內的文字

notice
------
1. 藥物手冊的表格並沒有分列，而是用跳行做分隔，因次只靠切表格是不夠的，還要計算每個文字的 y 座標位置比較，因此 PDFTextFinder API 才會加上傳回 x, y 座標
2. 藥物手冊內的同一行中文字的 y 座標是不一定有對齊的，因此在 parse.php 的範例中，採用抓到的文字與上一個抓到的 y 座標差距大於 10px 才會視為跳行

license
-------
The MIT license: http://g0v.mit-license.org/
