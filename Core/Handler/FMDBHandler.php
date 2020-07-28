<?php
//require_once('app/Config/config.php');

// FileMaker.phpの読み込み
require_once('vendor/vendor/FileMaker.php');

class FMDBHandler extends FileMaker {
	private $LayoutName;	// アクセスレイアウト
	private $skip_rec;		// 部分リストアップ用
	private $r_pos;			// 現在のレコードポインタ
	private $r_fetched;		// 取り出したレコード数
	private $fetchCount;	// 一度に取り出すレコード数
	// エラー情報
	public	$errCode;		// エラーコード
	public	$errMessage;	// エラーメッセージ
	// リスト配列
	private	$FetchRecords;	// 部分レコード
	public	$recordMax;		// 検索したレコード最大数
	private	$fields;		// フィールド
	private	$Finds;			// 検索フィールド
	private	$SortBy;		// ソート順配列
	private	$omit;			// 検索条件の反転
	private	$fmtconv;		// 日付変換対象フィールド
	public	$columns;		// フィールド情報
	public	$recordId;		// 書き込み用ID
	public	$modifyId;		// 修正ID
	private	$startrec;		// 開始レコード番号
	private	$limitrec;		// 取得レコード数
	private $onetime;		// デバッグ用：メッセージを1回に
//==============================================================================
// コンストラクタでデータベースに接続
	function __construct($dbtable) {
		parent::__construct(); // 継承元クラスのコンストラクターを呼ぶ
		// DB_FILE:WEB-Layout
		list($dbname,$table) = explode(':',$dbtable);
		// クラスユニークなパラメータ
		$this->setProperty('database', $dbname);
		$this->LayoutName = $table;
		$this->Database = $dbname;
		// FileMakerへのアクセスに共通
		foreach( DatabaseParameter['Filemaker'] as $key => $val ){
			$this->setProperty($key, $val);
		}
		$this->startrec = 0;		// 開始レコード番号
		$this->limitrec = 0;		// 取得レコード数
		$this->fetchCount = 20;
		$this->Finds = array();
		$this->Connect($this->LayoutName);
		APPDEBUG::MSG(13, DatabaseParameter['Filemaker']);
	}
//==============================================================================
//	Connect: テーブル名
//	fields[] 連想配列にフィールド名をセットする
//==============================================================================
function Connect($layout) {
	APPDEBUG::MSG(13, $this->LayoutName, 'レイアウト');
	$this->fields = array();
    // 先頭のレコードをひとつダミーで読み込む
    $findCommand = $this->newFindAllCommand($this->LayoutName);
	$findCommand->setRange(0,1);
	$result = $findCommand->execute();
	if (FileMaker::isError($result)) {	// エラー処理..
		$this->errCode = $result->getCode();
		$this->errMessage= $result->getMessage();
		debug_dump(1,[ 'Connect ERROR' => [
			'Layout' => $this->LayoutName,
			'Result' => $result,
			'errCode' => $this->errCode,
			'errMessage' => $this->errMessage,
			]]);
		throw new Exception('ExecError');
	}
    $fmfields = $result->getFields();
	foreach($fmfields as $key) {
		$this->columns[$key] = $key;
	}
	APPDEBUG::MSG(3, $this->columns, "Columns @ {$this->Database}({$this->LayoutName})");
	// フィールド型を記憶する
	$this->fmtconv = array();
    $layoutObj = $result->getLayout();
	foreach( $layoutObj->getFields() as $fieldObj ) {
		// タイプが「日付」「タイムスタンプ」のみを記憶
		$typ = $fieldObj->getResult();
		if(in_array($typ,['date','timestamp'])) {
			$this->fmtconv[$fieldObj->getName()] = $typ;
		}
    }
    unset($fieldObj);
//	var_dump($this->fmtconv);
}
public function isTableExist() {
	return TRUE;
}
//==============================================================================
// 日付変換
	private function SetDateTimeField($record) {
		foreach($this->columns as $key => $val ) {
			$this->fields[$key] = $record->getField($key);
		}
		foreach($this->fmtconv as $key => $val) {
			switch($val) {
			case 'date':
				$dt = explode('/',$this->fields[$key]);
				$dt = (count($dt)==3) ? $dt[2].'/'.$dt[0].'/'.$dt[1] : '';
				$this->fields[$key] = $dt;
				break;
			case 'timestamp':
				$vv = explode(' ',$this->fields[$key]);
				$dt = explode('/',$vv[0]);
				$vv[0] = (count($dt)==3) ? $dt[2].'/'.$dt[0].'/'.$dt[1] : '';
				$this->fields[$key] = implode(' ',$vv);
				break;
			}
		}
	}
//==============================================================================
// 指定の番号を持つレコードひとつだけを読み込む
public function doQueryBy($fn,$recno) {
	$findCommand = $this->newFindCommand($this->LayoutName);
    $findCommand->addFindCriterion($fn, '==' . $recno);
	$findCommand->setRange(0,1);
	debug_dump(0,["検索コマンド" => ['キー' => $fn,'値' => $recno]]);
	$result = $findCommand->execute();
	// 10. FileMaker::isErrorでエラー判定
	if (FileMaker::isError($result)) {	// エラー処理..
		$this->errCode = $result->getCode();
		$this->errMessage= $result->getMessage();
		if($this->errCode != 401) {	// レコードが見つからないとき以外
			throw new Exception('ExecError::' . $this->errCode );
		}
		foreach($this->columns as $key => $val) {
			$this->fields[$key] = '?';
		}
	} else {
	   	$this->recordMax = $result->getFoundSetCount();
	    $record = $result->getFirstRecord();
		$this->SetDateTimeField($record);			// 日付フィールドの変換 YYYY/MM/DD
		$this->recordId = $record->getRecordId();		// 書き込み用ID
		$this->modifyId = $record->getModificationId();	// 修正ID
		$this->fields['recordId'] = $this->recordId;
	}
	return $this->fields;
}
//==============================================================================
// ページングでレコードを読み込むためのパラメータ
// pagenum は１以上になることを呼び出し側で保証する
public function SetPaging($pagesize, $pagenum) {
	$this->startrec = $pagesize * ($pagenum - 1);		// 開始レコード番号
	$this->limitrec = $pagesize;		// 取得レコード数
}
//==============================================================================
// 複数条件を指定してレコードを読み込む
// FileMaker では relations を使わない
public function findRecord($row, $relations = NULL,$sort = []) {
	if(empty($row)) {
		$row = array(reset($this->columns) => '*');	// 先頭カラムを代行検索
	}
	// AND/OR 配列を整形する
	$expr_array = function($op,$row) use(&$expr_array) {
		$result = [[]];
		foreach($row as $key => $val) {
			if(is_array($val)) {
				$res = $expr_array($key,$val);
				if($op === 'OR') {
					foreach($res as $vv) $result[] = $vv;
				} else {
					$base = $result; $result = [];
					foreach($res as $vv)
						foreach($base as $zz) $result[] = array_merge($zz,$vv);
				}
			} else if($op === 'OR') {
				if(empty($result[0])) $result[0] = [ $key => $val];
				else $result[] = [ $key => $val];
			} else {
				$result[0] += [$key => $val];
			}
		}
		return $result;
	};
	// 検索条件を記録する
/*
	$n = array_depth($row);
	if($n == 1) {
		$row = array($row);
	}
	$this->Finds = $row;
*/
	$this->Finds = $expr_array('AND',$row);
	$this->SortBy = $sort;
	debug_dump(0,[
		'Columns'	=> $this->columns,
		'FindBy'	=> $this->Finds,
		'SortBy'	=> $this->SortBy,
	]);
    $this->skip_rec = $this->startrec;
    $this->recordMax = 0;
	$this->r_pos = 0;
	$this->r_fetched = 0;		// 取り出したレコード数
	APPDEBUG::MSG(13, $this->Finds, "検索設定");
	$this->onetime = 3;
}
//==============================================================================
// 複数条件を指定してレコードを読み込む
//public function fetchDB($sortby = [], $order=FILEMAKER_SORT_ASCEND) {
public function fetchDB() {
		if($this->r_fetched == 0) {
		if($this->recordMax > 0 && $this->skip_rec >= $this->recordMax) return 0;
		if($this->limitrec > 0 && $this->skip_rec >= ($this->startrec + $this->limitrec)) return 0;
		
		APPDEBUG::DebugDump($this->onetime,[
		    'Param' => [
        		"skip_rec" => $this->skip_rec,
	        	"startrec" => $this->startrec,
    	    	"limitrec" => $this->limitrec,
    		],
    		"検索設定" => $this->Finds
		]);
		//複合検索クラスのインスタンスを作成
		$compoundFind = $this->newCompoundFindCommand($this->LayoutName);
		//検索条件クラスのインスタンスを作成する
		// cond = [ [And条件１], [And条件2] ,... ]
		$n = 1;
		foreach($this->Finds as $opr => $andval ) {
			$findInst = $this->newFindRequest($this->LayoutName);
    		foreach($andval as $key => $val) {
				list($key,$op) = keystr_opr($key);	// キー名の最後に関係演算子
				$findInst->addFindCriterion($key, "{$op}{$val}");	// FMDBは比較文字列に演算子を付加する
	    	}
//			$findInst->setOmit((substr($opr,0,3) == 'NOT'));		// NOT は使用不可
			$compoundFind->add($n++,$findInst);
		}
		//ソート順の設定
		$kn = 1;
//		foreach($sortby as $akey) {
//			$compoundFind->addSortRule($akey, $kn++, $order);
//		}
		foreach($this->SortBy as $akey => $aval) {
			$order = ($aval === SORTBY_DESCEND) ? FILEMAKER_SORT_DESCEND : FILEMAKER_SORT_ASCEND;
			$compoundFind->addSortRule($akey, $kn++, $order);
		}
		$maxcount = ($this->limitrec == 0) ? $this->fetchCount : $this->limitrec;
		$compoundFind->setRange($this->skip_rec,$maxcount);
		//検索実行
		$result = $compoundFind->execute();
		//エラー処理
		if (FileMaker::isError($result)) {
			$this->errCode = $result->getCode();
			$this->errMessage= $result->getMessage();
            if ($this->errCode !== '401') {
				APPDEBUG::MSG(3, $this->errMessage, "エラー");
				// Check Find Condition
				debug_dump(4,[
					'FindBy'	=> $this->Finds,
					'SortBy'	=> $this->SortBy,
				]);
				throw new Exception('ExecError');
            }
		    $this->recordMax = 0;
			$this->r_fetched = 0;
		    $this->FetchRecords = array();
			$this->r_pos = 0;
			return NULL;
		} else {
		    $this->recordMax = $result->getFoundSetCount();
			$this->r_fetched = $result->getFetchCount();
			$this->skip_rec += $this->r_fetched;		// 次の読み込み位置
		    $this->FetchRecords = $result->getRecords();
			$this->r_pos = 0;
		}
		APPDEBUG::MSG(13, $this->r_fetched, "Fetch: ");
	}
	APPDEBUG::DebugDump($this->onetime,[
		'fetched' => [
			"recordMax" => $this->recordMax,
			"r_fetched" => $this->r_fetched,
			"r_pos" => $this->r_pos ,
		],
	]);
	if($this->r_fetched == 0) {
		return;				// 検索結果がゼロ
	}
	$this->r_fetched--;
	$record = $this->FetchRecords[$this->r_pos++];
	$this->recordId = $record->getRecordId();		// 書き込み用ID
	$this->modifyId = $record->getModificationId();	// 修正ID
	foreach($this->columns as $key => $val ) {
		$this->fields[$key] = $record->getField($key);
	}
	$this->fields['recordId'] = $this->recordId;
	$this->SetDateTimeField($record);			// 日付フィールドの変換 YYYY/MM/DD
	$this->onetime = 13;
	return $this->fields;
}

//==============================================================================
//	レコードの更新 $wh[Primary] = recordID
// レコードIDをプライマリキーに設定するバージョン
//==============================================================================
	public function replaceRecord($wh,$row) {
		APPDEBUG::MSG(13, $row );
		$recordId = reset($wh);			// 先頭の値がPrimaryKey = recordId
		if(empty($recordId)) {					// ID指定が無いときは空レコード生成
			// 空のレコードを生成
			$record = $this->newAddCommand($this->LayoutName);
			$recordId = $record->getRecordId();		// 書き込み用ID
		}
		// フィールドを書き換えて書き込み
		$edit = $this->newEditCommand($this->LayoutName,$recordId);
		foreach($row as $key => $val) {
			$val = preg_replace("/\r\n|\r|\n/", "\r", $val);		// APIが勝手にLFコードを付加する模様
			$edit->setField($key , $val);		// フィールドキーの存在は上位クラスで検証済み
		}
		$result = $edit->execute();			// 書き込み
		if(FileMaker::isError($result)) {		// エラー処理
			$this->errCode = $result->getCode();
			$this->errMessage = $result->getMessage();
			if($this->errCode !== 401) {		// レコードが見つからない時以外
				throw new Exception("ExecError::", $this->errCode);
			}
		}
	}
//==============================================================================
//	未定義ファンクション
//==============================================================================
/*
public function insertRecord($row)
public function deleteRecord($wh)
public function getRecordValue($row,$relations)
public function getValueLists($table,$ref,$id) 
public function getLastError() 
*/

}
