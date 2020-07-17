<?php
require_once('Core/Common/coreLibs.php');
require_once('Core/Common/appLibs.php');
//==============================================================================
// テストデータの生成
function __expr($OPR,$items) {
    $arr = [];
    foreach ($items as $val) $arr += $val;
    return [$OPR => $arr];
}
function _AND_(...$items) { return __expr('AND',$items); };
function _OR_(...$items) { return __expr('OR',$items); };
function _NOT_(...$items) { return __expr('NOT',$items); };

$row = _AND_(
        _OR_(['name1=' => 'val1'], ['name2<' => 'val2'], ['name3!=' => 'val3']),
        _AND_(['name_a=' => 'val_a', 'name_b>' => 'val_b','name_x' => 'val_x'], 
            _NOT_([ 'name_z<>' => 'val_z' ]))
        );
$row = [
    "1行目",
    [
        "ネスト1-1行目",
        [
            "ネスト2-1行目",
            "ネスト2-1行目",
        ],
        "ネスト1-2行目",
    ],
    "2行目",
];

//==============================================================================
// デバッグ
debug_dump(5,[
    "配列" => $row,
    "implode" => implode("\n",$row),
    "変換" => test_case_function($row),
    "ライブラリ" => array_to_text($row),
    ]);

exit;
//==============================================================================
// ライブラリのテストケース
function test_case_function($array) {
    $dump_text = function ($indent, $items)  use (&$dump_text)  {
        $txt = ''; $spc = str_repeat(' ', $indent);
        foreach($items as $key => $val) {
            $txt .= (is_array($val)) ? $dump_text($indent+2, $val) : "{$spc}{$val}\n";
        }
        return $txt;
    };
    return $dump_text(0,$array);
}
//******************************************************************************
//==============================================================================
// クラスメソッドのテストケース
$test = new TestClass();
echo $test->debug_method($row);

class TestClass {
//==============================================================================
// デバッグソッド
function debug_method($row) {
    debug_dump(1,[ "論理式" => $row]);
    echo $this->test_method($row);
    echo "\n";
    }
//==============================================================================
// テストメソッド
function test_method($row) {
    }
//==============================================================================

}
