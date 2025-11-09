# プロテクトコード (PCODE) 仕様書

## 概要

プロテクトコード (PCODE) は、掲示板システムで以下の攻撃を防ぐためのセキュリティ機構です：

- **CSRF攻撃** (クロスサイトリクエストフォージェリ)
- **二重投稿** (同じフォームを2回送信)
- **リプレイ攻撃** (古いフォーム送信の再利用)
- **投稿間隔違反** (短時間での連続投稿)

## 構造

PCODEは **12文字の16進数文字列** で、2つの部分から構成されます：

```
[8文字のタイムスタンプ][4文字の暗号コード]
```

### 例
```
675e8a3c4f2a
├─────┬─────┘└─┬─┘
│     │        └─ 暗号コード (4文字)
│     └────────── 16進数タイムスタンプ (8文字)
└──────────────── 合計: 12文字
```

## 生成プロセス

### 1. タイムスタンプ部分 (8文字)

```php
$timestamp = CURRENT_TIME; // Unixタイムスタンプ
$timestamphex = dechex($timestamp); // 16進数に変換 (8文字)
```

### 2. ユーザーキー (オプション - limithost=trueの場合)

```php
$ukey = hexdec(substr(md5($remoteaddr), 0, 8));
// IPアドレスのMD5ハッシュの最初の8文字を10進数に変換
```

### 3. ベースコード

```php
$basecode = dechex($timestamp + $ukey);
// タイムスタンプ + ユーザーキーを16進数に変換
```

### 4. 暗号コード (4文字)

```php
$salt = substr($adminPost, -4) . $basecode;
$cryptcode = crypt($basecode . substr($adminPost, -4), $salt);
$cryptcode = substr(preg_replace("/\W/", '', $cryptcode), -4);
// crypt結果の最後の4文字の英数字
```

### 5. 最終的なPCODE

```php
$pcode = dechex($timestamp) . $cryptcode;
// 8文字のタイムスタンプ + 4文字の暗号コード = 合計12文字
```

## 検証プロセス

フォームが送信されると、PCODEが検証されます：

### 1. 長さチェック
```php
if (strlen($pcode) != 12) {
    return null; // 無効
}
```

### 2. 構成要素の抽出
```php
$timestamphex = substr($pcode, 0, 8);  // 最初の8文字
$cryptcode = substr($pcode, 8, 4);     // 最後の4文字
```

### 3. 再構築と検証
```php
$timestamp = hexdec($timestamphex);
$basecode = dechex($timestamp + $ukey);
$verifycode = crypt($basecode . substr($adminPost, -4), $salt);
$verifycode = substr(preg_replace("/\W/", '', $verifycode), -4);

if ($cryptcode != $verifycode) {
    return null; // 無効
}
return $timestamp; // 有効 - 元のタイムスタンプを返す
```

## セキュリティ機能

### 1. CSRF保護
- PCODEはサーバー側で生成され、フォームに埋め込まれる
- `ADMINPOST`シークレットを知らないと偽造できない
- 各フォーム送信には有効なPCODEが必要

### 2. 二重投稿防止
- PCODEは各投稿と共にログファイルに保存される
- システムは同じPCODEが既に使用されているかチェック
- 誤ったダブルクリックやフォーム再送信を防ぐ

```php
if ($message['PCODE'] == $items[2]) {
    $posterr = 2; // リトライエラー - フォームを再表示
    break;
}
```

### 3. 投稿間隔の強制
- PCODEからタイムスタンプを抽出
- フォーム生成からの経過時間を計算
- 最小投稿間隔 (`MINPOSTSEC`) を強制

```php
if ((CURRENT_TIME - $timestamp) < $this->config['MINPOSTSEC']) {
    $this->prterror(Translator::trans('error.post_interval_too_short'));
}
```

### 4. IPアドレスバインディング (オプション)
- `limithost=true`の場合、PCODEにIPアドレスハッシュを含む
- フォームは生成したIPアドレスからのみ送信可能
- フォーム盗用やネットワーク間攻撃を防ぐ

## フォームでの使用方法

### 生成 (サーバー側)
```php
$pcode = SecurityHelper::generateProtectCode();
```

### HTMLへの埋め込み
```twig
<input type="hidden" name="pc" value="{{ PCODE }}" />
```

### 検証 (サーバー側)
```php
$timestamp = SecurityHelper::verifyProtectCode($this->form['pc'], $limithost);
if (!$timestamp) {
    // 無効なPCODE
}
```

## ログファイルへの保存

PCODEは各ログエントリの3番目のフィールドとして保存されます：

```
timestamp,postid,pcode,thread,host,agent,user,mail,title,message,refid
```

例：
```
1699876860,12345,4f2a,12340,192.168.1.1,Mozilla/5.0,...
                 ^^^^
                 4文字の暗号コードのみ保存
```

注意: **4文字の暗号コード部分のみ** が保存されます（`substr($pcode, 8, 4)`で抽出）。12文字全体ではありません。

## 設定

### MINPOSTSEC
投稿間の最小秒数 (デフォルト: 5)
```php
'MINPOSTSEC' => 5,
```

### IPREC
IPアドレスの記録とチェックを有効化
```php
'IPREC' => 1, // 1=有効, 0=無効
```

### SPTIME
スパム防止時間ウィンドウ (秒)
```php
'SPTIME' => 60, // 同じIPを60秒間ブロック
```

## セキュリティ上の考慮事項

### 強み
1. **暗号学的に安全**: `crypt()`をシークレットソルトと共に使用
2. **時間制限付き**: 古いPCODEは最大投稿間隔後に無効化
3. **IPバインド**: オプションのIPアドレスバインディングでフォーム盗用を防止
4. **フォームごとにユニーク**: 各フォーム生成で新しいPCODEを作成

### 制限事項
1. **セッションベースではない**: PCODEはアクティブなセッションを必要としない
2. **予測可能なタイムスタンプ**: タイムスタンプが16進数形式で見える
3. **弱い暗号化**: DES crypt（レガシー）を使用、モダンなハッシュではない
4. **有効期限なし**: 最小間隔のみ強制、最大期限はない

### 推奨事項
1. `ADMINPOST`シークレットを安全に保管（ソルトとして使用）
2. IPベース保護のため`IPREC`を有効化
3. スパム防止のため適切な`MINPOSTSEC`を設定
4. 将来的にモダンなHMACベーストークンへのアップグレードを検討

## 関連ファイル

- `src/Utils/SecurityHelper.php` - PCODE生成と検証
- `src/Kuzuha/Bbs.php` - 投稿処理でのPCODE使用
- `resources/views/components/form.twig` - フォームへのPCODE埋め込み
- `conf.php` - セキュリティ設定

## 参照

- [クッキー管理](./cookie-management.md)
- [投稿バリデーション](./bbs-refactoring-plan.md)
- [セキュリティベストプラクティス](./security-best-practices.md)
