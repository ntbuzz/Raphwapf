<?php
/* -------------------------------------------------------------
 * Biscuitフレームワーク
 *  LangUI:  言語ファイルを操作するstaticクラス
 * 
 */
//==================================================================================================
// 言語ファイルの操作クラス
class LangUI {
    public  static $STRINGS;        // 翻訳言語配列
    private static $LangDir;        // 言語ファイルのパス
    private static $Locale;         // 言語識別子

//==================================================================================================
// HTTP_ACCEPT_LANGUAGE を元にデフォルトの言語を決定する
    public static function construct($lang,$appname) {
        APPDEBUG::MSG(5,$lang, "言語リスト");
        $_ = 'constant';                                    // 定数を取り出す関数
        $langs = array_shift(               // 先頭の要素を取り出す
                  array_unique(             // 重複行を取り除く
                    array_filter(           // strlen を使って空行を取り除く
                        array_map(          // 各要素に有効識別子の取り出し関数を適用
                            function($a) {
                                if(($n=strpos($a,'-')) !== FALSE)       return substr($a,0,$n);     // en-US => en
                                else if(($n=strpos($a,';')) !== FALSE)  return substr($a,0,$n);     // en;q=0.9 => en
                                else return $a;
                            },
                            explode(',', $lang)  // 言語受け入れリスト
                        ),
                        'strlen')));
        self::$Locale = ".{$langs}";            // 言語識別文字を付加
        self::$STRINGS = [];
        // フレームワークの言語リソースを読込む
        self::$LangDir = 'Core/Template/lang/';
        self::LoadLang('core');
        // アプリケーションの言語リソースパス
        self::$LangDir = "{$appname}/View/lang/";
//        APPDEBUG::MSG(5,self::$Locale, "ロケール");
    }
//==================================================================================================
//  言語ファイルの読み込み
    public static function LangFiles($files) {
        if(is_array($files)) {          // 配列引数の時は各要素を言語ファイルとして処理
            $msg = '';
            foreach($files as $lang_file) {
                self::LoadLang($lang_file);
                $msg .= "[{$lang_file}]";
            }
        } else {
            self::LoadLang($files);        // スカラー引数は単独読み込み
            $msg = $files;
        }
        $msg = self::$Locale . " / " . trim($msg,' /');
        APPDEBUG::MSG(5, self::$STRINGS, "ロケール({$msg})");
    }
//==================================================================================================
//  セクション要素から空配列を削除する
    private static function emptyDelete($arr) {
        foreach($arr as $key => $val) {
            if(is_array($val)) {
                $val = self::emptyDelete($val);        // 子要素の配列を整理する
                if(empty($val)) unset($arr[$key]);      // 空配列なら削除
            }
        }
        return $arr;
    }
//==================================================================================================
//  言語ファイルの読み込み
    private static function LoadLang($lang_file) {
        $is_global = ($lang_file[0] == '#');
        if($is_global) {
            $lang_file = mb_substr($lang_file,1);
        }
        if(isset(self::$STRINGS[$lang_file])) return TRUE;     // 連想キーが定義済なら処理しない
        $fullpath = self::$LangDir . "{$lang_file}.lng";
        if(file_exists($fullpath)) {
            $parser = new SectionParser($fullpath);
            $section = $parser->getSectionDef();
            $import = [];           // インポートリスト
            $values = [];           // ロケール定義
            foreach($section as $key => $val) {
                if(is_numeric($key)) {          // 配列番号の要素なら外部読み込み指示かチェック
                    if($val[0] == '@') {
                        // @以降の文字列をインポートリストに記憶しておき後で処理する
                        $import[] = mb_substr($val,1);
                    }
                } else if($key[0] == '.') {        // ロケール定義
                    if($key == self::$Locale) {         // モジュール名の直下に定義された言語
                        if(is_array($val)) {                           // ロケール定義配列になっていれば不要な言語を削除する
                            $values = array_override($values,$val);   // 最上位にマージ
                        }
                    }
                } else {
                    if(is_array($val)) {                           // ロケール定義配列になっていれば不要な言語を削除する
                        $zz = [];                                   // ロケールが無い識別子をマージするための配列
                        foreach($val as $kk => $vv) {              // 識別子の子要素に言語キーがあるか探索する
                            if($kk[0] == '.') {
                                if($kk == self::$Locale) {        // 識別子の下に定義された言語
                                    $zz = (is_array($vv)) ? array_override($zz,$vv) : $vv;  // 配列ならマージスカラー要素ならそのまま
                                }
                            } else {
                                $zz[$kk] = $vv;                     // 言語依存しない定義
                            }
                        }
                    } else $zz = $val;
                    if($key[0] == '#') {       // グローバルIDの登録
                        $kk = mb_substr($key,1);
                        if(!empty($zz)) self::$STRINGS[$kk] = $zz;
                    } else {
                        $values[$key] = $zz;
                    }
                }
            }
            $values = self::emptyDelete($values);      // 空の要素を削除する
            if($is_global) {        // ファイル名がグローバル宣言ならトップレベルにマージする
                self::$STRINGS = array_override(self::$STRINGS,$values);   // 同じIDは上書き
            } else {
                self::$STRINGS[$lang_file] = $values;       // ファイル名をキーに言語配列
            }
            // インポート宣言されたファイルを読み込む
            foreach($import as $val) {                      // インポートリストの読み込み処理
                if(! isset(self::$STRINGS[$val])) {          // ループ回避のため未定義のものだけ処理する
                    self::LoadLang($val);                  // 再帰呼出
                }
            }
            unset($parser);
            unset($section);
            return TRUE;
        }
//        echo "LoadLocale not Found {$fullpath}\n";
        return FALSE;
    }
//==================================================================================================
// ネストされた配列変数を取得する、要素名はピリオドで連結したキー名
//  ex. Menu.File.OPEN  大文字小文字は区別される
    public static function get_value($mod, $id, $allow) {
        //-----------------------------------------
        // 無名関数を定義して配列内の探索を行う
        $array_finder = function ($lst, $arr, $allow) {
                foreach($lst as $val) {
                    if(array_key_exists($val, $arr)) {
                        $arr = $arr[$val];
                    } else return FALSE;        // 見つからなかった時
                }
                if(is_array($arr)) {          // 見つけた要素が配列なら
                    return ($allow) ? $arr :    // 配列を要求されていれば配列で返す
                        ( (isset($arr[0])) ? $arr[0] :     // 0番目の要素があれば値を返す
                                            FALSE);         // そうでなければエラー
                }
                return $arr;        // スカラー値はそのまま返す
            };
        //-----------------------------------------
        if($id[0] == '.') {        // 相対検索ならモジュール名を使う
            $lst = explode('.', "{$mod}{$id}");
            if( ($a=$array_finder($lst,self::$STRINGS,$allow)) !== FALSE) {
                return $a;
            }
            array_shift($lst);      // 先頭のモジュール名要素を消す
        } else $lst = explode('.', $id);    // 絶対検索
        if( ($a=$array_finder($lst,self::$STRINGS,$allow)) !== FALSE) {     // 
            return $a;
        }
        return array_pop($lst);     // 見つからなければ識別子の末尾要素を返す
    }
//==================================================================================================
// ネストされた配列変数を取得する、要素名はピリオドで連結したキー名
//  ex. Menu.File.OPEN  大文字小文字は区別される
    public static function get_array($arr, $mod, $var) {
        $element = self::get_value($mod,$var,FALSE);
        return $arr[$element];
    }
}
