<?php
/* -------------------------------------------------------------
 * PHPフレームワーク
 *  PostgreHandler: PostgreSSQLデータベースの入出力
 */
//==============================================================================
//	PostgreSQL用の抽象メソッドを実装する
class PostgreHandler extends SQLHandler {
//==============================================================================
//	コンストラクタ：　データベースのテーブルに接続する
	function __construct($table) {
		parent::__construct($table,'Postgre');
	}
//==============================================================================
//	Connect: テーブルに接続し、columns[] 配列にフィールド名をセットする
protected function Connect($table) {
	// テーブル属性を取得
	$sql = "SELECT * FROM information_schema.columns WHERE table_name = '{$table}' ORDER BY ordinal_position;";
	$result = pg_query($this->dbb, $sql);
	if(!$result) {
		die('Postgres QUERY失敗' . pg_last_error());
	}
	$columns = array();
	while ($row = pg_fetch_array($result,NULL,PGSQL_ASSOC)) {
		if(!$row) {
			die('Postgres QUERY失敗' . pg_last_error());
		}
		$columns[$row['column_name']] = $row['column_name'];
	}
	return $columns;
}
//==============================================================================
//	field concatiname
public function fieldConcat($sep,$arr) {
	return "concat_ws('{$sep}'," . implode($arr,',') . ")";
}
//==============================================================================
//	DROP TABLE/VIEW CASCADE
public function drop_sql($kind,$table) {
	return "DROP {$kind} IF EXISTS {$table} CASCADE;";
}
//==============================================================================
//	TRUNCATE TABLE
public function truncate_sql($table) {
	return "TRUNCATE TABLE {$table};";
}
//==============================================================================
//	doQuery: 	SQLを発行する
public function doQuery($sql) {
//	debug_log(DBMSG_HANDLER,['SQL' => $sql]);
	$this->rows = pg_query($this->dbb, $sql);
	if(!$this->rows) {
		$res1 = pg_get_result($this->dbb);
		debug_log(DBMSG_DIE,[
			"ERROR" => pg_result_error($res1),
			"SQL" => $sql,
			"COND" => $this->LastCond,
			"BUILD" => $this->LastBuild,
			'QUERY失敗' => pg_last_error(),
		]);
	}
	return $this->rows;
}
//==============================================================================
//	fetch_array: 	レコードを取得してカラム配列を返す
public function fetch_array() {
	return pg_fetch_array($this->rows,NULL,PGSQL_ASSOC);
}
//==============================================================================
//	getLastError: 	レコードを取得してカラム配列を返す
public function getLastError() {
	return pg_last_error($this->dbb);
}
//==============================================================================
//	INSERT or UPDATE
// INSERT INTO test_table (id, name) VALUES (val_id, val_name)
// ON CONFLICT (id) DO UPDATE SET name = val_name；
// pg_update($this->dbb,$this->raw_table,$row,$wh);
//==============================================================================
public function updateRecord($wh,$row) {
	$row = array_merge($wh,$row);			// INSERT 用にプライマリキー配列とデータ配列をマージ
	$this->sql_safequote($row);

	// PostgreSQLのデータ型に変換
	$aa = pg_convert($this->dbb,$this->raw_table,$row,PGSQL_CONV_FORCE_NULL );
	if($aa === FALSE) {
		$res1 = pg_get_result($this->dbb);
		debug_log(DBMSG_DIE,[
			"ERROR:" => pg_result_error($res1),
			"DBB" => $this->dbb,
			"TABLE" => $this->raw_table,
			"ROW" => $row,
			'Postgres CONVERT失敗' => pg_last_error(),
		]);
	}
	$primary = '"' . key($wh) . '"';		// プライマリキー名を取得
	$kstr = implode(',', array_keys($aa));	// フィールド名リストを作成
	$vstr = implode(',', $aa);				// VALUES リストを作成
	$set = " SET"; $sep = " ";				// UPDATE する時の代入文
	foreach($aa as $key => $val) {
		$set .= "{$sep}{$key}={$val}";
		$sep = ",";
	}
	// UPSERT 文を生成
	$sql = "INSERT INTO \"{$this->raw_table}\" ({$kstr}) VALUES ({$vstr}) ON CONFLICT ({$primary}) DO UPDATE {$set} RETURNING *;";
	$res = $this->doQuery($sql);
	return $this->fetchDB();
}
//==============================================================================
//	INSERT
// INSERT INTO test_table (id, name) VALUES (val_id, val_name)
// ON CONFLICT (id) DO UPDATE SET name = val_name；
// pg_update($this->dbb,$this->raw_table,$row,$wh);
//==============================================================================
public function insertRecord($row) {
	$this->sql_safequote($row);
	// PostgreSQLのデータ型に変換
	$aa = pg_convert($this->dbb,$this->raw_table,$row,PGSQL_CONV_FORCE_NULL);
	if($aa === FALSE) {
		$res1 = pg_get_result($this->dbb);
		debug_log(DBMSG_DIE,[
			"ERROR:" => pg_result_error($res1),
			"DBB" => $this->dbb,
			"TABLE" => $this->raw_table,
			"ROW" => $row,
			'Postgres CONVERT失敗' => pg_last_error(),
		]);
	}
	$kstr = implode(',', array_keys($aa));	// フィールド名リストを作成
	$vstr = implode(',', $aa);				// VALUES リストを作成
	$set = " SET"; $sep = " ";				// UPDATE する時の代入文
	foreach($aa as $key => $val) {
		$set .= "{$sep}{$key}={$val}";
		$sep = ",";
	}
	// UPSERT 文を生成
	$sql = "INSERT INTO \"{$this->raw_table}\" ({$kstr}) VALUES ({$vstr}) RETURNING *;";
	$res = $this->doQuery($sql);
	return $this->fetchDB();
}

}
