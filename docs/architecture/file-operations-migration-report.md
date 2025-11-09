# ファイル操作のRepository移行レポート

## 現在のファイル操作箇所

### 1. Webapp::loadmessage() - ログ読み込み
**場所:** `src/Kuzuha/Webapp.php:323-336`

**現在のコード:**
```php
public function loadmessage($logfilename = '')
{
    if ($logfilename) {
        preg_match("/^([\w.]*)$/", $logfilename, $matches);
        $logfilename = $this->config['OLDLOGFILEDIR'].'/'.$matches[1];
    } else {
        $logfilename = $this->config['LOGFILENAME'];
    }
    if (!file_exists($logfilename)) {
        $this->prterror(Translator::trans('error.failed_to_read'));
    }
    $logdata = file($logfilename);
    return $logdata;
}
```

**置き換え方法:**
```php
public function loadmessage($logfilename = '')
{
    if ($logfilename) {
        // アーカイブログの場合 - 将来的にArchiveRepositoryで処理
        preg_match("/^([\w.]*)$/", $logfilename, $matches);
        $logfilename = $this->config['OLDLOGFILEDIR'].'/'.$matches[1];
        if (!file_exists($logfilename)) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
        $logdata = file($logfilename);
        return $logdata;
    } else {
        // メインログの場合 - BbsLogRepositoryを使用
        if ($this->bbsLogRepo) {
            return $this->bbsLogRepo->getAll();
        }
        // フォールバック（リポジトリがない場合）
        $logfilename = $this->config['LOGFILENAME'];
        if (!file_exists($logfilename)) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
        return file($logfilename);
    }
}
```

**影響範囲:** 低（読み込みのみ、既存の戻り値と互換性あり）

---

### 2. Bbs::msgsearchlist() - アーカイブログ検索
**場所:** `src/Kuzuha/Bbs.php:715-760`

**現在のコード:**
```php
$fh = @fopen($this->config['OLDLOGFILEDIR'] . $this->form['ff'], 'rb');
if (!$fh) {
    $this->prterror(...);
}
flock($fh, 1);
while (($logline = FileHelper::getLine($fh)) !== false) {
    // 検索処理
}
fclose($fh);
```

**置き換え方法:**
```php
// ArchiveRepository使用（将来実装）
if ($this->archiveRepo) {
    $archiveKey = $this->form['ff'];
    $messages = $this->archiveRepo->getArchive($archiveKey);
    foreach ($messages as $logline) {
        // 検索処理
    }
} else {
    // 既存のファイル操作（フォールバック）
    $fh = @fopen($this->config['OLDLOGFILEDIR'] . $this->form['ff'], 'rb');
    // ...
}
```

**影響範囲:** 中（アーカイブログ専用、ArchiveRepository実装が必要）

---

### 3. Bbs::putmessage() - メッセージ投稿
**場所:** `src/Kuzuha/Bbs.php:1345-1475`

**現在のコード:**
```php
$fh = @fopen($this->config['LOGFILENAME'], 'rb+');
flock($fh, 2);
fseek($fh, 0, 0);

// ログ全体を読み込み
$logdata = [];
while (($logline = FileHelper::getLine($fh)) !== false) {
    $logdata[] = $logline;
}

// 重複チェック、スレッド検索など

// 新しいメッセージを先頭に追加
$msgdata = implode(',', [...]) . "\n";
$logdata = $msgdata . implode('', $logdata);

// ファイルに書き込み
fseek($fh, 0, 0);
ftruncate($fh, 0);
fwrite($fh, $logdata);
fclose($fh);

// アーカイブログにも追加
$fh = @fopen($oldlogfilename, 'ab');
fwrite($fh, $msgdata);
fclose($fh);
```

**置き換え方法:**
```php
// BbsLogRepositoryを使用
if ($this->bbsLogRepo) {
    // 既存ログ取得
    $logdata = $this->bbsLogRepo->getAll();
    
    // 重複チェック、スレッド検索など（既存ロジック）
    
    // 新しいメッセージを追加
    $message['POSTID'] = $this->getNextPostId();
    $messageArray = [
        CURRENT_TIME,
        $message['POSTID'],
        $message['PCODE'],
        // ...
    ];
    $this->bbsLogRepo->append($messageArray);
    
    // アーカイブログにも追加
    if ($this->archiveRepo) {
        $archiveKey = date('Ymd', CURRENT_TIME); // or 'Ym'
        $this->archiveRepo->archive($messageArray, $archiveKey);
    }
} else {
    // 既存のファイル操作（フォールバック）
}
```

**影響範囲:** 高（書き込み処理、慎重な移行が必要）

---

## 移行戦略

### フェーズ1: 読み込み操作の移行（低リスク）

**対象:**
- `Webapp::loadmessage()` - メインログ読み込み

**手順:**
1. `Webapp`に`$bbsLogRepo`プロパティを追加
2. `loadmessage()`でリポジトリがあれば使用、なければフォールバック
3. テスト実行
4. 問題なければフォールバックコード削除

**リスク:** 低（読み込みのみ、既存動作と互換性あり）

---

### フェーズ2: 書き込み操作の移行（高リスク）

**対象:**
- `Bbs::putmessage()` - メッセージ投稿

**課題:**
1. **ファイルロック:** 現在は`flock()`で排他制御
   - Repository側でロック機構を実装する必要あり
   
2. **トランザクション:** 読み込み→検証→書き込みが一連の処理
   - Repository側でトランザクション的な処理が必要
   
3. **重複チェック:** ログ全体を読み込んで重複チェック
   - Repository側で効率的な検索メソッドが必要

**手順:**
1. `BbsLogRepositoryInterface`に以下を追加:
   ```php
   public function beginTransaction(): void;
   public function commit(): void;
   public function rollback(): void;
   public function findDuplicate(string $message, int $checkCount): ?string;
   public function getNextPostId(): int;
   ```

2. `putmessage()`をリファクタリング:
   - ビジネスロジックとファイル操作を分離
   - Repository経由で操作

3. 段階的テスト:
   - ローカル環境で十分にテスト
   - 本番環境では並行運用（旧コードと新コードの結果を比較）

**リスク:** 高（データ破損の可能性、慎重な移行が必要）

---

### フェーズ3: アーカイブログの移行

**対象:**
- `Bbs::msgsearchlist()` - アーカイブログ検索
- `Bbs::putmessage()` - アーカイブログ書き込み

**前提条件:**
- `ArchiveRepositoryInterface`の実装
- `ArchiveFileRepository`の実装

**手順:**
1. ArchiveRepository作成
2. アーカイブログ読み込みを移行
3. アーカイブログ書き込みを移行

**リスク:** 中（アーカイブは参照のみが多い）

---

## 推奨される実装順序

### ステップ1: Webapp::loadmessage()の移行（今すぐ実施可能）

```php
// Webapp.php
protected ?BbsLogRepositoryInterface $bbsLogRepo = null;

public function setBbsLogRepository(?BbsLogRepositoryInterface $repo): void
{
    $this->bbsLogRepo = $repo;
}

public function loadmessage($logfilename = '')
{
    if ($logfilename) {
        // アーカイブログ（既存処理）
        preg_match("/^([\w.]*)$/", $logfilename, $matches);
        $logfilename = $this->config['OLDLOGFILEDIR'].'/'.$matches[1];
        if (!file_exists($logfilename)) {
            $this->prterror(Translator::trans('error.failed_to_read'));
        }
        return file($logfilename);
    }
    
    // メインログ（Repository使用）
    if ($this->bbsLogRepo) {
        return $this->bbsLogRepo->getAll();
    }
    
    // フォールバック
    $logfilename = $this->config['LOGFILENAME'];
    if (!file_exists($logfilename)) {
        $this->prterror(Translator::trans('error.failed_to_read'));
    }
    return file($logfilename);
}
```

**メリット:**
- リスクが低い
- 既存コードと完全互換
- すぐに実装可能

---

### ステップ2: BbsLogRepositoryの拡張（慎重に実施）

**必要なメソッド追加:**
```php
interface BbsLogRepositoryInterface
{
    // 既存メソッド
    public function append(array $message): void;
    public function getAll(): array;
    public function getRange(int $start, int $limit): array;
    public function findById(int $postId): ?string;
    public function deleteById(int $postId): bool;
    public function count(): int;
    
    // 追加メソッド
    public function getNextPostId(): int;
    public function findDuplicateMessage(string $message, int $checkCount): ?array;
    public function findByRefId(int $refId): ?array;
    public function lock(): void;
    public function unlock(): void;
}
```

---

### ステップ3: putmessage()の段階的移行

1. **ビジネスロジックの抽出**
   - 重複チェックロジックを別メソッドに
   - スレッド検索ロジックを別メソッドに

2. **Repository経由の書き込み**
   - 既存コードと並行運用
   - ログで結果を比較

3. **既存コード削除**
   - 十分なテスト後に削除

---

## まとめ

### 即座に実施可能（低リスク）
- ✅ `Webapp::loadmessage()` - メインログ読み込み

### 慎重に実施（中リスク）
- ⚠️ `Bbs::msgsearchlist()` - アーカイブログ検索
- ⚠️ ArchiveRepository実装

### 段階的に実施（高リスク）
- 🔴 `Bbs::putmessage()` - メッセージ投稿
- 🔴 ファイルロック機構
- 🔴 トランザクション処理

### 次のアクション
1. `Webapp::loadmessage()`をRepository化（今すぐ）
2. 動作確認とテスト
3. `BbsLogRepositoryInterface`の拡張検討
4. `putmessage()`のリファクタリング計画
