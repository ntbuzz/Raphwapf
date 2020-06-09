<?php
/* -------------------------------------------------------------
 * PHPフレームワーク
 *  resource:    CSS/JSファイルのリクエストを受付け、定義ファイルの情報に従いファイル結合したものを応答する
 * RewriteRule ^(css|js|images)/(.*)$       vendor/webroot/$1/$2 [END]
 *
 * テンプレートリソース(CSS/画像ファイル)
 *  (appname)/css/res/img/(.*)$     Core/Template/cssimg/$1 [END]
 *   /res/images/(.*)$              Core/Template/images/$1 [END]
 * アプリごとに異なるリダイレクト
 *  (appname)/(module)/css/img/(.*)$    app/$1/webroot/cssimg/$2 [END]
 *  (appname)/(css|js)/(.*)$            app/$1/webroot/$2/$3 [END]
 *  (appname)/images/(.*)$              app/$1/webroot/images/$2 [END]
 *  (appname)/(module)/images/(.*)$     app/$1/webroot/images/$2 [END]
 *      => .htaccess でリライトされるので考慮しない
 *  app/module/(css|js)/xxxx.css|.js
 *      module => module oe res
 *      method => css|js
 *      filter => xxxx.css|.js
 */

// デバッグ用のクラス
require_once('AppDebug.php');
require_once('Common/appLibs.php');
require_once('Class/session.php');
require_once('Base/AppStyle.php');
require_once('Base/LangUI.php');           // static class

APPDEBUG::INIT(0);

date_default_timezone_set('Asia/Tokyo');

list($appname,$app_uri,$module,$q_str) = getRoutingParams(__DIR__);
list($fwroot,$appRoot) = $app_uri;
list($controller,$category,$files) = $module;
// ファイル名を拡張子と分離する
list($filename,$ext) = extractBaseName($files);
// 言語ファイルの対応
$lang = (isset($query['lang'])) ? $query['lang'] : $_SERVER['HTTP_ACCEPT_LANGUAGE'];
// コア用の言語ファイルを読み込む
LangUI::construct($lang,'');
MySession::InitSession();
// モジュール名と拡張子を使いテンプレートを決定する
$AppStyle = new AppStyle($appname,$appRoot, $controller, $filename, $ext);
// ヘッダの出力
$AppStyle->ViewHeader();
// 結合ファイルの出力
$AppStyle->ViewStyle($filename);

MySession::CloseSession();
