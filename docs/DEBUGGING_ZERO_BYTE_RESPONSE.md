# 0バイトレスポンス問題のデバッグガイド

## 症状

```bash
curl -s http://localhost:8080/ | wc -c
# 出力: 0
```

ページが完全に空白で、HTMLが一切返ってこない。

## 原因

### 1. 型付きプロパティ宣言の問題（今回のケース）

**問題のコード:**
```php
class Webapp
{
    protected ?BbsLogRepositoryInterface $bbsLogRepo = null;  // ❌ これが原因
}
```

**なぜ0バイトになるか:**
- PHP 8.4で型付きプロパティを宣言すると、そのプロパティは厳密に型チェックされる
- 子クラス（Bbs）で同じプロパティを`private`で宣言すると、親クラスからアクセスできない
- `setBbsLogRepository()`で`$this->bbsLogRepo`に代入しようとすると、プロパティが存在しないか、アクセスできない
- エラーが発生するが、`ob_start()`でバッファリングされているため、エラー出力が捨てられる
- 結果として0バイトのレスポンスになる

**解決策:**
```php
class Webapp
{
    // 型宣言なしの動的プロパティを使用
    // setBbsLogRepository()で動的に作成される
}

public function setBbsLogRepository($repo): void
{
    $this->bbsLogRepo = $repo;  // ✅ 動的プロパティとして作成
}
```

または

```php
class Bbs extends Webapp
{
    protected $bbsLogRepo = null;  // ✅ protected にする
}
```

### 2. その他の一般的な原因

- **Fatal Error:** 構文エラー、クラスが見つからない、メソッドが存在しない
- **例外:** キャッチされていない例外
- **ob_start/ob_get_clean:** バッファリング中のエラーが捨てられる

## デバッグ手順

### ステップ1: エラーログを確認

```bash
# Dockerログを確認
docker-compose logs --tail=50 web | grep -E "Error|Fatal|Exception"

# エラーページの内容を確認
curl -s http://localhost:8080/ 2>&1
```

**今回のケース:**
```
<h1>An error occurred</h1>
<pre>Uncaught Error: Cannot access private property Kuzuha\Bbs::$bbsLogRepo</pre>
```

### ステップ2: 構文エラーをチェック

```bash
php -l src/Kuzuha/Webapp.php
php -l src/Kuzuha/Bbs.php
```

構文エラーがなければ、実行時エラーの可能性が高い。

### ステップ3: 変更を段階的に戻す

```bash
# 最後の動作確認済みの状態に戻す
git stash

# 動作確認
curl -s http://localhost:8080/ | wc -c

# 変更を1つずつ適用
git stash pop
```

### ステップ4: 変更箇所を特定

**今回の手順:**
1. RepositoryFactory変更 → OK (42811バイト)
2. DI登録追加 → OK (42811バイト)
3. Bbs constructor変更 → OK (42811バイト)
4. routes.php変更 → OK (42811バイト)
5. Webapp.php プロパティ追加 → **NG (0バイト)** ← 原因特定！

### ステップ5: プロパティ宣言を確認

**チェックポイント:**
- 型宣言があるか？
- 親クラスと子クラスで同じプロパティを宣言していないか？
- アクセス修飾子（private/protected/public）は適切か？

## 予防策

### 1. エラーハンドリングを追加

```php
// routes.php
try {
    $bbs = new \Kuzuha\Bbs($accessCounterRepo, $participantCounterRepo, $bbsLogRepo);
    $bbs->main();
} catch (\Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    throw $e;  // 再スロー
}
```

### 2. 動的プロパティを使用

型付きプロパティの代わりに動的プロパティを使用する：

```php
// プロパティ宣言なし
class Webapp
{
    public $config;
    public $form;
    // $bbsLogRepo は宣言しない
}

// メソッドで動的に作成
public function setBbsLogRepository($repo): void
{
    $this->bbsLogRepo = $repo;
}
```

### 3. プロパティの可視性を統一

親クラスと子クラスで同じプロパティを使う場合は`protected`にする：

```php
class Webapp
{
    protected $bbsLogRepo = null;
}

class Bbs extends Webapp
{
    protected $bbsLogRepo = null;  // 同じ可視性
}
```

### 4. Dockerを再起動

Opcacheの問題の可能性もあるため、変更後は再起動：

```bash
docker-compose restart web
sleep 3
curl -s http://localhost:8080/ | wc -c
```

## クイックチェックリスト

0バイトレスポンスが発生したら：

- [ ] `curl -s http://localhost:8080/` でエラーメッセージを確認
- [ ] `docker-compose logs --tail=50 web` でログ確認
- [ ] `php -l` で構文チェック
- [ ] 最後の変更を`git stash`で戻して動作確認
- [ ] 変更を1つずつ適用して原因特定
- [ ] プロパティ宣言の型と可視性を確認
- [ ] Dockerを再起動

## 今回の教訓

1. **型付きプロパティは慎重に使う**
   - PHP 8.4では型チェックが厳格
   - 動的プロパティの方が柔軟

2. **エラーハンドリングは必須**
   - try-catchで例外をキャッチ
   - error_logでログ出力

3. **段階的にテスト**
   - 変更を1つずつ適用
   - 各ステップで動作確認

4. **プロパティの可視性に注意**
   - 親クラスからアクセスする場合は`protected`
   - `private`は同じクラス内のみ

## 参考

- PHP 8.4 Typed Properties: https://www.php.net/manual/en/language.oop5.properties.php
- Dynamic Properties: https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.dynamic
